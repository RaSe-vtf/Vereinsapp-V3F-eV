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
    <link rel="stylesheet" href="../../assets/css/style.css?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#5b9bd5">
</head>
<body>
    <?php require __DIR__ . '/../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">
        <h2 style="margin-top:0;">Geschäftsstelle</h2>

        <div class="kachel-grid">
            <a href="antraege.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../../assets/img/brand/icon-aufnahmeantraege.svg" alt="">
                <span class="kachel__title">Aufnahmeanträge</span>
                <span class="kachel__subtitle">Neue Mitgliedsanträge prüfen.</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
            <a href="mitglieder.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../../assets/img/brand/icon-mitglieder.svg" alt="">
                <span class="kachel__title">Mitglieder</span>
                <span class="kachel__subtitle">Mitgliederdaten verwalten.</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
            <a href="verteiler.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../../assets/img/brand/icon-email-verteiler.svg" alt="">
                <span class="kachel__title">E-Mail-Verteiler</span>
                <span class="kachel__subtitle">Newsletter und Verteiler verwalten.</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
            <a href="kassenwart/index.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../../assets/img/brand/icon-kassenwart.svg" alt="">
                <span class="kachel__title">Kassenwart</span>
                <span class="kachel__subtitle">Finanzen und SEPA.</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
            <a href="vereinsdokumente.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../../assets/img/brand/icon-vereinsdokumente.svg" alt="">
                <span class="kachel__title">Vereinsdokumente</span>
                <span class="kachel__subtitle">Satzung, Ordnungen und wichtige Unterlagen.</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
            <a href="protokolle.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../../assets/img/brand/icon-protokolle.svg" alt="">
                <span class="kachel__title">Protokolle</span>
                <span class="kachel__subtitle">Sitzungsprotokolle und Beschlüsse.</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
            <a href="notizen.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../../assets/img/brand/icon-notizen.svg" alt="">
                <span class="kachel__title">Notizen</span>
                <span class="kachel__subtitle">Ideen, Aufgaben und To-dos.</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
        </div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
