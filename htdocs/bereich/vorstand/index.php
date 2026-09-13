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
    <!-- Race-Konzept (Version 2): eigener Header statt includes/kopf.php,
         damit alle anderen Seiten unveraendert bleiben. Menue-Funktion
         1:1 aus kopf.php uebernommen. Foto-Hero analog zu den anderen
         umgestellten Seiten. Siehe Master-Prompt in CLAUDE.md. -->
    <div class="home-hero" style="background-image:url('../../assets/img/brand/geschaeftsstelle-hero.jpg');">
        <div class="home-hero__topbar">
            <a href="../home.php" class="home-hero__brand" aria-label="Startseite">
                <img class="home-hero__logo" src="../../assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
                <span class="home-hero__title"><?= e(APP_NAME) ?></span>
            </a>
            <a class="home-hero__back" href="../home.php" aria-label="Zurück" title="Zurück">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 6 9 12 15 18"></polyline></svg>
            </a>
            <div class="menu-wrapper" style="margin-left:8px;">
                <button type="button" class="menu-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="hauptmenue">&#9776;</button>
                <nav class="menu-dropdown" id="hauptmenue" hidden>
                    <a href="../index.php">Meine Daten</a>
                    <a href="../sportlerprofile.php">Sportlerprofile</a>
                    <a href="index.php" class="active">Geschäftsstelle</a>
                    <?php if (!empty($mitglied['ist_admin'])): ?>
                        <a href="../admin/index.php">Admin</a>
                    <?php endif; ?>
                    <hr>
                    <a href="../../logout.php" class="menu-logout">Abmelden</a>
                </nav>
            </div>
        </div>

        <div class="home-hero__content">
            <span class="kicker">Race Control</span>
            <h2>Geschäftsstelle</h2>
            <p class="home-hero__claim">Alles, was den Verein organisiert.</p>
            <div class="home-hero__accent">
                <span style="background:#5b9bd5;"></span>
                <span style="background:#ff3399;"></span>
                <span style="background:#8e44ad;"></span>
                <span style="background:#fadd06;"></span>
            </div>
        </div>
    </div>

    <main class="container" style="max-width:1040px;">
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
