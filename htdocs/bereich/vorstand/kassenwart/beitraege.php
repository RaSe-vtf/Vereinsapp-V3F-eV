<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/auth.php';

$mitglied = requireVorstand('../../../login.php', '../../index.php');
$pdo = getPdo();

// Feste Positionen sicherstellen: ein Mitgliedsbeitrag je Rolle plus die
// Startpasskosten. Alles andere (z.B. frueher frei angelegte Posten) wird
// deaktiviert, damit nur noch diese Positionen im SEPA-Export erscheinen
// koennen.
foreach (array_keys(ROLLEN_LABELS) as $rolle) {
    $stmt = $pdo->prepare('SELECT id FROM beitragsposten WHERE rolle = :rolle AND ist_startpass = 0');
    $stmt->execute(['rolle' => $rolle]);
    if (!$stmt->fetch()) {
        $pdo->prepare('INSERT INTO beitragsposten (bezeichnung, betrag, rolle, ist_startpass) VALUES (:bezeichnung, 0, :rolle, 0)')
            ->execute(['bezeichnung' => 'Mitgliedsbeitrag ' . ROLLEN_LABELS[$rolle], 'rolle' => $rolle]);
    }
}
$stmtStartpass = $pdo->query('SELECT id FROM beitragsposten WHERE ist_startpass = 1');
if (!$stmtStartpass->fetch()) {
    $pdo->exec("INSERT INTO beitragsposten (bezeichnung, betrag, rolle, ist_startpass) VALUES ('Startpasskosten', 0, NULL, 1)");
}
$pdo->exec(
    "UPDATE beitragsposten SET aktiv = 0
     WHERE (ist_startpass = 0 AND (rolle IS NULL OR rolle NOT IN ('vollmitglied', 'trainingsmitglied', 'vorstandsmitglied', 'ehrenmitglied', 'foerdermitglied')))
        OR (ist_startpass = 1 AND rolle IS NOT NULL)"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $id = (int) ($_POST['id'] ?? 0);
        $betragRoh = str_replace(',', '.', trim((string) ($_POST['betrag'] ?? '')));

        if (!is_numeric($betragRoh) || (float) $betragRoh < 0) {
            setFlash('error', 'Bitte gib einen gültigen Betrag an.');
        } else {
            $pdo->prepare('UPDATE beitragsposten SET betrag = :betrag WHERE id = :id AND aktiv = 1')
                ->execute(['betrag' => number_format((float) $betragRoh, 2, '.', ''), 'id' => $id]);
            setFlash('success', 'Betrag wurde gespeichert.');
        }
    }
    header('Location: beitraege.php');
    exit;
}

$mitgliedsbeitraege = [];
$stmtBeitraege = $pdo->query("SELECT * FROM beitragsposten WHERE ist_startpass = 0 AND aktiv = 1");
foreach ($stmtBeitraege->fetchAll() as $p) {
    $mitgliedsbeitraege[$p['rolle']] = $p;
}
$startpass = $pdo->query('SELECT * FROM beitragsposten WHERE ist_startpass = 1 AND aktiv = 1 LIMIT 1')->fetch();

$flash = takeFlash();

$tiefe = '../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = 'index.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beiträge &ndash; Kassenwart &ndash; <?= e(APP_NAME) ?></title>
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
            <a href="bankverbindungen.php">Bankverbindungen</a>
            <a href="beitraege.php" class="active">Beiträge</a>
            <a href="export.php">SEPA-Export</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Mitgliedsbeiträge</h2>
            <p class="text-muted">Monatlicher Beitrag je Mitgliederart. Nur diese Beträge sind im SEPA-Mandat abgedeckt und dürfen per Lastschrift eingezogen werden.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
            <?php endif; ?>

            <div style="overflow-x:auto; margin-bottom:20px;">
            <table class="tabelle-einzeilig">
                <thead>
                    <tr>
                        <th>Mitgliederart</th>
                        <th>Betrag (€, monatlich)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (ROLLEN_LABELS as $rolleWert => $rolleLabel): $posten = $mitgliedsbeitraege[$rolleWert] ?? null; ?>
                        <tr>
                            <td><?= e($rolleLabel) ?></td>
                            <td colspan="2">
                                <?php if ($posten): ?>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $posten['id'] ?>">
                                        <input type="text" name="betrag" inputmode="decimal" style="width:120px; display:inline-block;" value="<?= e(number_format((float) $posten['betrag'], 2, ',', '')) ?>">
                                        <button type="submit" class="btn btn-secondary">Speichern</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <h2>Startpässe</h2>
            <p class="text-muted">Jährliche Startpassgebühr (z.B. DTU). Wird nur einmal im Jahr je Mitglied fällig und separat im SEPA-Export ausgewählt.</p>

            <div style="overflow-x:auto;">
            <table class="tabelle-einzeilig">
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Betrag (€, jährlich)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Startpasskosten</td>
                        <td colspan="2">
                            <?php if ($startpass): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $startpass['id'] ?>">
                                    <input type="text" name="betrag" inputmode="decimal" style="width:120px; display:inline-block;" value="<?= e(number_format((float) $startpass['betrag'], 2, ',', '')) ?>">
                                    <button type="submit" class="btn btn-secondary">Speichern</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>

            <p class="text-muted" style="margin-top:20px;">Sollen andere Gelder eingezogen werden, ist das über diese Seite nicht möglich &ndash; das erfolgt dann immer als direkte Überweisung außerhalb der App.</p>
        </div>
    </main>
    <script src="../../../assets/js/menue.js" defer></script>
</body>
</html>
