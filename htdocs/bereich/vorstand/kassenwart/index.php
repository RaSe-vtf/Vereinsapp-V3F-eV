<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/auth.php';

$mitglied = requireVorstand('../../../login.php', '../../index.php');

$tiefe = '../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = '../../home.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kassenwart &ndash; <?= e(APP_NAME) ?></title>
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

        <h2 style="margin-top:0;">Kassenwart</h2>

        <div class="kachel-grid">
            <a href="bankverbindungen.php" class="kachel">
                <span class="kachel-icon">🏦</span>
                <span>Bankverbindungen</span>
            </a>
            <a href="beitraege.php" class="kachel">
                <span class="kachel-icon">🧾</span>
                <span>Beiträge</span>
            </a>
            <a href="export.php" class="kachel">
                <span class="kachel-icon">📤</span>
                <span>SEPA-Export</span>
            </a>
            <a href="kassenbuecher/index.php" class="kachel">
                <span class="kachel-icon">📚</span>
                <span>Kassenbücher</span>
            </a>
            <a href="beitragsrechner.php" class="kachel">
                <span class="kachel-icon">🧮</span>
                <span>Beitragsrechner</span>
            </a>
        </div>
    </main>
    <script src="../../../assets/js/menue.js" defer></script>
</body>
</html>
