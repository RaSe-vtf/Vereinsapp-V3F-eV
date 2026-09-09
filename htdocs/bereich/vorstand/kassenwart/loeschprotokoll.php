<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');
$pdo = getPdo();

$eintraege = $pdo->query(
    'SELECT l.*, m.vorname, m.nachname
     FROM finanz_loeschprotokoll l
     LEFT JOIN mitglieder m ON m.id = l.mitglied_id
     ORDER BY l.geloescht_am DESC, l.id DESC'
)->fetchAll();

$quelleLabels = ['barkasse_buchung' => 'Barkasse'];

$tiefe = '../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = 'index.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Löschprotokoll &ndash; Kassenwart &ndash; <?= e(APP_NAME) ?></title>
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
            <a href="index.php" class="active">Kassenwart</a>
            <a href="../vereinsdokumente.php">Vereinsdokumente</a>
            <a href="../protokolle.php">Protokolle</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Löschprotokoll</h2>
            <p class="text-muted">Dauerhafte, unveränderliche Dokumentation jeder Löschung in der Barkasse - wer, wann, welche Buchung und warum. Für eine Kassenprüfung oder Revision.</p>

            <?php if (empty($eintraege)): ?>
                <p>Bisher wurden keine Buchungen gelöscht.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="tabelle-karten">
                    <thead>
                        <tr>
                            <th>Gelöscht am</th>
                            <th>Von</th>
                            <th>Bereich</th>
                            <th>Gelöschte Buchung</th>
                            <th>Grund</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eintraege as $e): ?>
                            <tr>
                                <td data-label="Gelöscht am"><?= e((new DateTime($e['geloescht_am']))->format('d.m.Y H:i')) ?></td>
                                <td data-label="Von"><?= $e['vorname'] !== null ? e($e['vorname'] . ' ' . $e['nachname']) : '&ndash;' ?></td>
                                <td data-label="Bereich"><?= e($quelleLabels[$e['quelle']] ?? $e['quelle']) ?></td>
                                <td data-label="Gelöschte Buchung"><?= e($e['beschreibung']) ?></td>
                                <td data-label="Grund"><?= e($e['grund']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="../../../assets/js/menue.js" defer></script>
</body>
</html>
