<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../../includes/db.php';
require_once __DIR__ . '/../../../../../includes/functions.php';
require_once __DIR__ . '/../../../../../includes/auth.php';

$mitglied = requireVorstand('../../../../login.php', '../../../index.php');

$tiefe = '../../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = '../index.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kassenbücher &ndash; Kassenwart &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../../../assets/css/style.css?v=<?= cacheV('assets/css/style.css') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body class="seite-mit-hintergrundfoto">
    <?php require __DIR__ . '/../../../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">

        <div class="seiten-titel-block">
            <img class="seiten-titel-block__icon" src="../../../../assets/img/brand/icon-kw-kassenbuecher.png" alt="">
            <div>
                <span class="kicker">Race Control</span>
                <h1 class="seiten-titel-block__titel">Kassenbücher</h1>
                <p class="seiten-titel-block__untertitel">Ein- und Ausgaben erfassen.</p>
                <span class="seiten-titel-block__akzent" style="background:#5b9bd5;"></span>
            </div>
        </div>

        <div class="kachel-grid">
            <a href="vereinskonto.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../../../assets/img/brand/icon-kw-bankverbindungen.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">Vereins&shy;konto</span>
                    <span class="kachel__subtitle">Kontobewegungen buchen</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
            <a href="barkasse.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../../../assets/img/brand/icon-kb-barkasse.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">Barkasse</span>
                    <span class="kachel__subtitle">Bargeld-Ein- und -Ausgaben</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
        </div>

        <div class="seiten-footer-foto" style="background-image:url('../../../../assets/img/brand/footer-kassenbuecher.jpg?v=<?= cacheV('assets/img/brand/footer-kassenbuecher.jpg') ?>');" aria-hidden="true"></div>
    </main>
    <script src="../../../../assets/js/menue.js" defer></script>
</body>
</html>
