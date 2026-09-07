<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/auth.php';

$mitglied = requireVorstand('../../../login.php', '../../index.php');
$pdo = getPdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $aktion = $_POST['aktion'] ?? '';

        if ($aktion === 'anlegen') {
            $bezeichnung = trim((string) ($_POST['bezeichnung'] ?? ''));
            $betragRoh = str_replace(',', '.', trim((string) ($_POST['betrag'] ?? '')));
            $rolleRoh = (string) ($_POST['rolle'] ?? '');
            $rolle = $rolleRoh !== '' && array_key_exists($rolleRoh, ROLLEN_LABELS) ? $rolleRoh : null;

            if ($bezeichnung === '') {
                setFlash('error', 'Bitte gib eine Bezeichnung an.');
            } elseif (!is_numeric($betragRoh) || (float) $betragRoh <= 0) {
                setFlash('error', 'Bitte gib einen gültigen Betrag größer als 0 an.');
            } else {
                $pdo->prepare('INSERT INTO beitragsposten (bezeichnung, betrag, rolle) VALUES (:bezeichnung, :betrag, :rolle)')
                    ->execute([
                        'bezeichnung' => $bezeichnung,
                        'betrag' => number_format((float) $betragRoh, 2, '.', ''),
                        'rolle' => $rolle,
                    ]);
                setFlash('success', 'Beitragsposten wurde angelegt.');
            }
        } elseif ($aktion === 'umschalten' && isset($_POST['id'])) {
            $pdo->prepare('UPDATE beitragsposten SET aktiv = NOT aktiv WHERE id = :id')->execute(['id' => (int) $_POST['id']]);
            setFlash('success', 'Status wurde aktualisiert.');
        } elseif ($aktion === 'loeschen' && isset($_POST['id'])) {
            $pdo->prepare('DELETE FROM beitragsposten WHERE id = :id')->execute(['id' => (int) $_POST['id']]);
            setFlash('success', 'Beitragsposten wurde gelöscht.');
        }
    }
    header('Location: beitragsposten.php');
    exit;
}

$postenListe = $pdo->query('SELECT * FROM beitragsposten ORDER BY aktiv DESC, bezeichnung')->fetchAll();
$flash = takeFlash();

$tiefe = '../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = '../../home.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beitragsposten &ndash; Kassenwart &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php require __DIR__ . '/../../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">
        <nav class="subnav">
            <a href="../antraege.php">Aufnahmeanträge</a>
            <a href="../mitglieder.php">Mitgliederverwaltung</a>
            <a href="../verteiler.php">E-Mail-Verteiler</a>
            <a href="bankverbindungen.php" class="active">Kassenwart</a>
        </nav>

        <nav class="subnav">
            <a href="bankverbindungen.php">Bankverbindungen</a>
            <a href="beitragsposten.php" class="active">Beitragsposten</a>
            <a href="export.php">SEPA-Export</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Beitragsposten</h2>
            <p class="text-muted">Mitgliedsbeiträge je Rolle und weitere Kostenpunkte (z.B. Startpassgebühr der DTU). Ein Posten ohne Rolle gilt für alle aktiven Mitglieder mit erteiltem SEPA-Mandat. Beim SEPA-Export wählst du aus, welche aktiven Posten in den jeweiligen Lauf einfließen.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
            <?php endif; ?>

            <?php if (empty($postenListe)): ?>
                <p>Noch keine Beitragsposten angelegt.</p>
            <?php else: ?>
                <div style="overflow-x:auto; margin-bottom:20px;">
                <table class="tabelle-karten">
                    <thead>
                        <tr>
                            <th>Bezeichnung</th>
                            <th>Betrag</th>
                            <th>Rolle</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($postenListe as $p): ?>
                            <tr>
                                <td data-label="Bezeichnung"><?= e($p['bezeichnung']) ?></td>
                                <td data-label="Betrag" class="nowrap-wert"><?= number_format((float) $p['betrag'], 2, ',', '.') ?> €</td>
                                <td data-label="Rolle"><?= $p['rolle'] !== null ? e(rollenLabel($p['rolle'])) : 'Alle' ?></td>
                                <td data-label="Status"><?= $p['aktiv'] ? '<span class="badge badge-angenommen">aktiv</span>' : '<span class="badge badge-abgelehnt">inaktiv</span>' ?></td>
                                <td data-label="Aktionen">
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="aktion" value="umschalten">
                                        <button type="submit" class="btn btn-secondary"><?= $p['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
                                    </form>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="aktion" value="loeschen">
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Beitragsposten &quot;<?= e($p['bezeichnung']) ?>&quot; wirklich löschen?');">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

            <h3>Neuen Posten anlegen</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="anlegen">

                <label class="required" for="bezeichnung">Bezeichnung</label>
                <input type="text" id="bezeichnung" name="bezeichnung" placeholder="z.B. Mitgliedsbeitrag Vollmitglied" required>

                <label class="required" for="betrag">Betrag (€)</label>
                <input type="text" id="betrag" name="betrag" inputmode="decimal" placeholder="z.B. 60,00" required>

                <label for="rolle">Rolle (optional)</label>
                <select id="rolle" name="rolle">
                    <option value="">Alle aktiven Mitglieder</option>
                    <?php foreach (ROLLEN_LABELS as $wert => $label): ?>
                        <option value="<?= e($wert) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Ohne Auswahl gilt der Posten für alle aktiven Mitglieder mit Mandat.</div>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn">Posten anlegen</button>
                </div>
            </form>
        </div>
    </main>
    <script src="../../../assets/js/menue.js" defer></script>
</body>
</html>
