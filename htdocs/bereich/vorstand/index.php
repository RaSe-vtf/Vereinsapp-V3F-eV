<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireVorstand('../login.php', '../index.php');

$tiefe = '../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = '../home.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Geschäftsstelle &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php require __DIR__ . '/../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">
        <h2 style="margin-top:0;">Geschäftsstelle</h2>

        <div class="kachel-grid">
            <a href="antraege.php" class="kachel">
                <span class="kachel-icon">📋</span>
                <span>Aufnahmeanträge</span>
            </a>
            <a href="mitglieder.php" class="kachel">
                <span class="kachel-icon">👥</span>
                <span>Mitgliederverwaltung</span>
            </a>
            <a href="verteiler.php" class="kachel">
                <span class="kachel-icon">📧</span>
                <span>E-Mail-Verteiler</span>
            </a>
            <a href="kassenwart/index.php" class="kachel">
                <span class="kachel-icon">💰</span>
                <span>Kassenwart</span>
            </a>
            <a href="vereinsdokumente.php" class="kachel">
                <span class="kachel-icon">📄</span>
                <span>Vereinsdokumente</span>
            </a>
            <a href="protokolle.php" class="kachel">
                <span class="kachel-icon">🗒️</span>
                <span>Protokolle</span>
            </a>
        </div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
