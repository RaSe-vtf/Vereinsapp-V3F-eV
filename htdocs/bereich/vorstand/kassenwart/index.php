<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/auth.php';

$mitglied = requireVorstand('../../../login.php', '../../index.php');

$tiefe = '../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = '../index.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kassenwart &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../../assets/css/style.css?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body class="seite-mit-hintergrundfoto">
    <?php require __DIR__ . '/../../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">

        <div class="seiten-titel-block">
            <img class="seiten-titel-block__icon" src="../../../assets/img/brand/icon-kassenwart.svg" alt="">
            <div>
                <span class="kicker">Race Control</span>
                <h1 class="seiten-titel-block__titel">Kassenwart</h1>
                <p class="seiten-titel-block__untertitel">Finanzen und SEPA.</p>
                <span class="seiten-titel-block__akzent" style="background:#ff3399;"></span>
            </div>
        </div>

        <div class="kachel-grid" style="grid-template-columns:1fr;">
            <a href="bankverbindungen.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../../assets/img/brand/icon-kw-bankverbindungen.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">Bankverbindungen</span>
                    <span class="kachel__subtitle">Konten und IBANs verwalten</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
            <a href="beitraege.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../../assets/img/brand/icon-kw-beitraege.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">Beiträge</span>
                    <span class="kachel__subtitle">Mitgliedsbeiträge verwalten</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
            <a href="export.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../../assets/img/brand/icon-kw-sepa-export.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">SEPA-Export</span>
                    <span class="kachel__subtitle">Lastschriften vorbereiten</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
            <a href="kassenbuecher/index.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../../assets/img/brand/icon-kw-kassenbuecher.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">Kassenbücher</span>
                    <span class="kachel__subtitle">Ein- und Ausgaben erfassen</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
            <a href="kassenbericht.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../../assets/img/brand/icon-kw-kassenbericht.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">Kassenbericht</span>
                    <span class="kachel__subtitle">Übersicht und Auswertung</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
            <a href="beitragsrechner.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../../assets/img/brand/icon-kw-beitragsrechner.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">Beitragsrechner</span>
                    <span class="kachel__subtitle">Beiträge automatisch berechnen</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
            <a href="loeschprotokoll.php" class="kachel kachel--rich kachel--horizontal">
                <img class="bereich-grafik" src="../../../assets/img/brand/icon-kw-loeschprotokoll.png" alt="">
                <span class="kachel__text">
                    <span class="kachel__title">Löschprotokoll</span>
                    <span class="kachel__subtitle">Daten sicher löschen</span>
                    <span class="kachel__akzent-dreifarbig"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                </span>
            </a>
        </div>

        <div class="seiten-footer-foto" style="background-image:url('../../../assets/img/brand/footer-kassenwart.jpg');" aria-hidden="true"></div>
    </main>
    <script src="../../../assets/js/menue.js" defer></script>
</body>
</html>
