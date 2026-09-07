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
        }
    }
    header('Location: mitglieder.php');
    exit;
}

$mitgliederListe = $pdo->query('SELECT * FROM mitglieder ORDER BY nachname, vorname')->fetchAll();
$flash = takeFlash();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mitgliederverwaltung &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php
    $tiefe = '../';
    $seitenUntertitel = 'Geschäftsstelle';
    $aktivReiter = 'geschaeftsstelle';
    $zurueck = '../home.php';
    require __DIR__ . '/../../../includes/kopf.php';
    ?>

    <main class="container" style="max-width:1040px;">
        <nav class="subnav">
            <a href="antraege.php">Aufnahmeanträge</a>
            <a href="mitglieder.php" class="active">Mitgliederverwaltung</a>
            <a href="verteiler.php">E-Mail-Verteiler</a>
            <a href="kassenwart/bankverbindungen.php">Kassenwart</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Mitgliederverwaltung</h2>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
            <?php endif; ?>

            <?php if (empty($mitgliederListe)): ?>
                <p>Noch keine Mitglieder angelegt.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="tabelle-einzeilig">
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
                            <th>Status</th>
                            <th>Passwort</th>
                            <th>Mitglied seit</th>
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
                                <td><?= $m['aktiv'] ? '<span class="badge badge-angenommen">aktiv</span>' : '<span class="badge badge-abgelehnt">inaktiv</span>' ?></td>
                                <td><?= $m['passwort_hash'] !== null ? 'gesetzt' : '<em>nicht gesetzt</em>' ?></td>
                                <td><?= e((new DateTime($m['erstellt_am']))->format('d.m.Y')) ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                        <input type="hidden" name="aktion" value="aktiv_umschalten">
                                        <button type="submit" class="btn btn-secondary"><?= $m['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
                                    </form>
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
