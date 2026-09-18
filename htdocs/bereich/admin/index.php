<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireAdmin('../login.php', '../index.php');

$tiefe = '../';
$seitenUntertitel = 'Admin';
$aktivReiter = 'admin';
$zurueck = '../home.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css?v=9">
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
            <img class="seiten-titel-block__icon" src="../../assets/img/brand/bereich-admin.svg" alt="">
            <div>
                <span class="kicker">Race Control</span>
                <h1 class="seiten-titel-block__titel">Admin</h1>
                <p class="seiten-titel-block__untertitel">Vereinsverwaltung und Einstellungen.</p>
                <span class="seiten-titel-block__akzent" style="background:#5b9bd5;"></span>
            </div>
        </div>

        <div class="kachel-grid">
            <a href="konten.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../assets/img/brand/icon-admin-konten.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">Konten</span>
                    <span class="kachel__subtitle">Passwort zurücksetzen und Admin-Rechte vergeben</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
            <a href="bilder.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../assets/img/brand/icon-admin-bilder.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">Bilder</span>
                    <span class="kachel__subtitle">Bildqualität prüfen und optimieren</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
        </div>

        <div class="seiten-footer-foto" style="background-image:url('../../assets/img/brand/footer-admin.jpg');" aria-hidden="true"></div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
