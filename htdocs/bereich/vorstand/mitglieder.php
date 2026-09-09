<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');
$pdo = getPdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aktion'], $_POST['id'])) {
    if (checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $zielId = (int) $_POST['id'];
        $rolleInput = $_POST['rolle'] ?? null;

        if ($_POST['aktion'] === 'rolle_aendern' && $rolleInput !== null && array_key_exists($rolleInput, ROLLEN_LABELS)) {
            if ($zielId === (int) $mitglied['id'] && $rolleInput !== 'vorstandsmitglied') {
                setFlash('error', 'Du kannst dir nicht selbst die Rolle Vorstandsmitglied entziehen. Bitte ein anderes Vorstandsmitglied bitten.');
            } else {
                $pdo->prepare('UPDATE mitglieder SET rolle = :rolle WHERE id = :id')
                    ->execute(['rolle' => $rolleInput, 'id' => $zielId]);
                setFlash('success', 'Rolle wurde aktualisiert.');
            }
        } elseif ($_POST['aktion'] === 'aktiv_umschalten') {
            if ($zielId === (int) $mitglied['id']) {
                setFlash('error', 'Du kannst dein eigenes Konto nicht deaktivieren.');
            } else {
                $pdo->prepare('UPDATE mitglieder SET aktiv = NOT aktiv WHERE id = :id')->execute(['id' => $zielId]);
                $nochAktiv = $pdo->prepare('SELECT aktiv FROM mitglieder WHERE id = :id');
                $nochAktiv->execute(['id' => $zielId]);
                if ((int) $nochAktiv->fetchColumn() === 0) {
                    loescheAlleRememberTokens($zielId);
                }
                setFlash('success', 'Status wurde aktualisiert.');
            }
        } elseif ($_POST['aktion'] === 'kuendigung_erfassen') {
            $eingegangenAm = trim((string) ($_POST['eingegangen_am'] ?? ''));
            $grund = trim((string) ($_POST['grund'] ?? ''));
            $eingang = DateTime::createFromFormat('Y-m-d', $eingegangenAm);
            if (!$eingang || $eingang > new DateTime()) {
                setFlash('error', 'Bitte gib ein gültiges Eingangsdatum (nicht in der Zukunft) an.');
            } else {
                $austrittsdatum = berechneAustrittsdatumNachSatzung($eingang);
                $pdo->prepare(
                    'UPDATE mitglieder SET kuendigung_eingegangen_am = :eingegangen_am, austrittsdatum = :austrittsdatum, kuendigungsgrund = :grund WHERE id = :id'
                )->execute([
                    'eingegangen_am' => $eingang->format('Y-m-d'),
                    'austrittsdatum' => $austrittsdatum->format('Y-m-d'),
                    'grund' => $grund !== '' ? $grund : null,
                    'id' => $zielId,
                ]);
                setFlash('success', 'Kündigung erfasst. Satzungsgemäßes Austrittsdatum (§ 6 Abs. 2 Satzung): ' . $austrittsdatum->format('d.m.Y') . '.');
            }
        } elseif ($_POST['aktion'] === 'kuendigung_zurueckziehen') {
            $pdo->prepare('UPDATE mitglieder SET kuendigung_eingegangen_am = NULL, austrittsdatum = NULL, kuendigungsgrund = NULL WHERE id = :id')
                ->execute(['id' => $zielId]);
            setFlash('success', 'Kündigung wurde zurückgezogen.');
        } elseif ($_POST['aktion'] === 'austrittsdatum_anpassen') {
            $neuesDatum = trim((string) ($_POST['austrittsdatum'] ?? ''));
            $datum = DateTime::createFromFormat('Y-m-d', $neuesDatum);
            if (!$datum) {
                setFlash('error', 'Bitte gib ein gültiges Datum an.');
            } else {
                $pdo->prepare('UPDATE mitglieder SET austrittsdatum = :austrittsdatum WHERE id = :id')
                    ->execute(['austrittsdatum' => $datum->format('Y-m-d'), 'id' => $zielId]);
                setFlash('success', 'Austrittsdatum wurde manuell auf ' . $datum->format('d.m.Y') . ' angepasst.');
            }
        }
    }
    header('Location: mitglieder.php');
    exit;
}

verarbeiteFaelligeAustritte($pdo);

$mitgliederListe = $pdo->query(
    'SELECT m.*, a.einverstaendnis_bildnutzung
     FROM mitglieder m
     LEFT JOIN antraege a ON a.id = m.antrag_id
     ORDER BY m.nachname, m.vorname'
)->fetchAll();
$flash = takeFlash();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mitgliederverwaltung &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
    <style>
        /* Mitgliederverwaltung hat viele Spalten - kompakter als die
           App-weite Tabellen-Grundschrift, damit mehr ohne horizontales
           Scrollen direkt lesbar ist. */
        .mv-tabelle { font-size: 0.78rem; }
        .mv-tabelle th,
        .mv-tabelle td { padding: 5px 7px; }
        .mv-tabelle .btn,
        .mv-tabelle .btn-secondary { padding: 3px 7px; font-size: 0.72rem; }
        .mv-tabelle .badge { padding: 2px 7px; font-size: 0.7rem; }
        .mv-tabelle .role-select { font-size: 0.72rem; padding: 3px 5px; }
    </style>
</head>
<body>
    <?php
    $tiefe = '../';
    $seitenUntertitel = 'Geschäftsstelle';
    $aktivReiter = 'geschaeftsstelle';
    $zurueck = 'index.php';
    require __DIR__ . '/../../../includes/kopf.php';
    ?>

    <main class="container" style="max-width:1040px;">

        <div class="card">
            <h2 style="margin-top:0;">Mitgliederverwaltung</h2>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
            <?php endif; ?>

            <?php if (empty($mitgliederListe)): ?>
                <p>Noch keine Mitglieder angelegt.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="tabelle-einzeilig mv-tabelle">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Vorname</th>
                            <th>Nachname</th>
                            <th>Geburtsdatum</th>
                            <th>Geburtsort</th>
                            <th>Adresse</th>
                            <th>Telefon</th>
                            <th>E-Mail</th>
                            <th>Instagram</th>
                            <th>Rolle</th>
                            <th>Mitglied seit</th>
                            <th>Bildnutzung</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mitgliederListe as $m): ?>
                            <tr>
                                <td>
                                    <a href="mitglied_ansehen.php?id=<?= (int) $m['id'] ?>" title="Datenblatt von <?= e($m['vorname'] . ' ' . $m['nachname']) ?> ansehen">
                                        <?php if ($m['foto_dateiname']): ?>
                                            <img class="foto-thumb" src="../foto.php?typ=mitglied&id=<?= (int) $m['id'] ?>" alt="Foto von <?= e($m['vorname'] . ' ' . $m['nachname']) ?>">
                                        <?php else: ?>
                                            <span class="foto-thumb foto-thumb--platzhalter"><?= e(mb_substr($m['vorname'], 0, 1) . mb_substr($m['nachname'], 0, 1)) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td><?= e($m['vorname']) ?></td>
                                <td><?= e($m['nachname']) ?></td>
                                <td><?= e((new DateTime($m['geburtsdatum']))->format('d.m.Y')) ?></td>
                                <td><?= e($m['geburtsort']) ?></td>
                                <td><?= e($m['strasse_hausnummer']) ?>, <?= e($m['plz'] . ' ' . $m['ort']) ?></td>
                                <td><?= e($m['telefon']) ?></td>
                                <td><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></td>
                                <td><?= $m['instagram'] ? '@' . e($m['instagram']) : '&ndash;' ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                        <input type="hidden" name="aktion" value="rolle_aendern">
                                        <select name="rolle" class="role-select" onchange="this.form.submit()">
                                            <?php foreach (ROLLEN_LABELS as $wert => $label): ?>
                                                <option value="<?= e($wert) ?>" <?= $m['rolle'] === $wert ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td><?= e((new DateTime($m['erstellt_am']))->format('d.m.Y')) ?></td>
                                <td><?= $m['einverstaendnis_bildnutzung'] ? '<span class="badge badge-angenommen">ja</span>' : '<span class="badge badge-abgelehnt">nein</span>' ?></td>
                                <td>
                                    <?php if ($m['aktiv'] && $m['austrittsdatum']): ?>
                                        <span class="badge badge-neu" title="Kündigung eingegangen am <?= e((new DateTime($m['kuendigung_eingegangen_am']))->format('d.m.Y')) ?>, § 6 Abs. 2 Satzung">gekündigt zum <?= e((new DateTime($m['austrittsdatum']))->format('d.m.Y')) ?></span>
                                    <?php elseif ($m['aktiv']): ?>
                                        <span class="badge badge-angenommen">aktiv</span>
                                    <?php elseif ($m['ausgetreten_am']): ?>
                                        <span class="badge badge-abgelehnt">ausgetreten am <?= e((new DateTime($m['ausgetreten_am']))->format('d.m.Y')) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-abgelehnt">inaktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                        <input type="hidden" name="aktion" value="aktiv_umschalten">
                                        <button type="submit" class="btn btn-secondary"><?= $m['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
                                    </form>

                                    <?php if ($m['aktiv'] && !$m['austrittsdatum']): ?>
                                        <details>
                                            <summary class="btn btn-secondary">Kündigung erfassen</summary>
                                            <form method="post" style="margin-top:6px; min-width:200px;">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                                <input type="hidden" name="aktion" value="kuendigung_erfassen">
                                                <label class="required" style="margin-top:6px;">Eingegangen am</label>
                                                <input type="date" name="eingegangen_am" value="<?= e((new DateTime())->format('Y-m-d')) ?>" required>
                                                <label>Grund (optional)</label>
                                                <textarea name="grund" rows="2" style="width:100%; padding:6px 8px; border:1px solid var(--farbe-border); border-radius:6px; font-family:inherit; font-size:inherit;"></textarea>
                                                <div class="hint">Austrittsdatum wird automatisch nach § 6 Abs. 2 der Satzung berechnet (6 Wochen zum Quartalsende).</div>
                                                <button type="submit" class="btn btn-secondary" style="margin-top:6px;">Speichern</button>
                                            </form>
                                        </details>
                                    <?php elseif ($m['aktiv'] && $m['austrittsdatum']): ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                            <input type="hidden" name="aktion" value="kuendigung_zurueckziehen">
                                            <button type="submit" class="btn btn-secondary" onclick="return confirm('Kündigung von <?= e($m['vorname']) ?> wirklich zurückziehen?');">Kündigung zurückziehen</button>
                                        </form>
                                        <details>
                                            <summary class="btn btn-secondary">Austrittsdatum anpassen</summary>
                                            <form method="post" style="margin-top:6px; min-width:180px;">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                                <input type="hidden" name="aktion" value="austrittsdatum_anpassen">
                                                <label class="required" style="margin-top:6px;">Neues Austrittsdatum</label>
                                                <input type="date" name="austrittsdatum" value="<?= e($m['austrittsdatum']) ?>" required>
                                                <div class="hint">Nur für begründete Sonderfälle - Standard ist die automatische Berechnung.</div>
                                                <button type="submit" class="btn btn-secondary" style="margin-top:6px;">Speichern</button>
                                            </form>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="../../assets/js/lightbox.js" defer></script>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
