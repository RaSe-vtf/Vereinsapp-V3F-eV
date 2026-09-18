<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');

$tiefe = '../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = 'index.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Protokolle &ndash; Geschäftsstelle &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css?v=4">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body class="seite-mit-hintergrundfoto">
    <?php require __DIR__ . '/../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">

        <div class="seiten-titel-block">
            <img class="seiten-titel-block__icon" src="../../assets/img/brand/icon-protokolle.svg" alt="">
            <div>
                <span class="kicker">Race Control</span>
                <h1 class="seiten-titel-block__titel">Protokolle</h1>
                <p class="seiten-titel-block__untertitel">Sitzungsprotokolle und Beschlüsse.</p>
                <span class="seiten-titel-block__akzent" style="background:#fadd06;"></span>
            </div>
        </div>

        <div class="card">
            <p class="text-muted">Hier werden künftig Sitzungsprotokolle und Vorstandsentscheidungen abgelegt. Diese Seite ist aktuell noch leer.</p>
        </div>

        <div class="seiten-footer-foto" style="background-image:url('../../assets/img/brand/footer-protokolle.jpg');" aria-hidden="true"></div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
