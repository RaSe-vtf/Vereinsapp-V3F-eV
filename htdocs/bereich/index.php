<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$mitglied = requireMemberLogin('../login.php');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meine Daten &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=4">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#5b9bd5">
</head>
<body>
    <!-- Race-Konzept (Version 2): eigener Header statt includes/kopf.php,
         damit alle anderen Seiten unveraendert bleiben. Menue-Funktion
         1:1 aus kopf.php uebernommen. Foto-Hero analog zu login.php und
         home.php. Siehe Master-Prompt in CLAUDE.md.

         Diese Seite ist bewusst nur noch ein Hub mit Kacheln zu den
         Unterseiten (persoenliche-daten.php, passwort.php,
         bankverbindung.php) - vorher war das eine einzige, sehr lange
         Seite mit allen drei Formularen untereinander. -->
    <div class="home-hero" style="background-image:url('../assets/img/brand/meine-daten-hero.jpg');">
        <div class="home-hero__topbar">
            <a href="home.php" class="home-hero__brand" aria-label="Startseite">
                <img class="home-hero__logo" src="../assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
                <span class="home-hero__title"><?= e(APP_NAME) ?></span>
            </a>
            <a class="home-hero__back" href="home.php" aria-label="Zurück" title="Zurück">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 6 9 12 15 18"></polyline></svg>
            </a>
            <div class="menu-wrapper" style="margin-left:8px;">
                <button type="button" class="menu-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="hauptmenue">&#9776;</button>
                <nav class="menu-dropdown" id="hauptmenue" hidden>
                    <a href="index.php" class="active">Meine Daten</a>
                    <a href="sportlerprofile.php">Sportlerprofile</a>
                    <?php if (!empty($mitglied['vorstandsamt']) || !empty($mitglied['ist_admin'])): ?>
                        <a href="vorstand/index.php">Geschäftsstelle</a>
                    <?php endif; ?>
                    <?php if (!empty($mitglied['ist_admin'])): ?>
                        <a href="admin/index.php">Admin</a>
                    <?php endif; ?>
                    <hr>
                    <a href="../logout.php" class="menu-logout">Abmelden</a>
                </nav>
            </div>
        </div>

        <div class="home-hero__content">
            <h2>Meine Daten</h2>
            <p class="home-hero__claim">Persönliche Daten und Mitgliedschaft.</p>
            <div class="home-hero__accent">
                <span style="background:#5b9bd5;"></span>
                <span style="background:#ff3399;"></span>
                <span style="background:#8e44ad;"></span>
                <span style="background:#fadd06;"></span>
            </div>
        </div>
    </div>

    <main class="container">
        <div class="kachel-grid">
            <a href="persoenliche-daten.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../assets/img/brand/icon-persoenliche-daten.svg" alt="">
                <span class="kachel__title">Persönliche Daten</span>
                <span class="kachel__subtitle">Adresse, Kontakt, Foto und Porträt</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
            <a href="passwort.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../assets/img/brand/icon-passwort-aendern.svg" alt="">
                <span class="kachel__title">Passwort ändern</span>
                <span class="kachel__subtitle">Zugangsdaten für dein Konto</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
            <?php if ($mitglied['sepa_erteilt_am'] !== null): ?>
                <a href="bankverbindung.php" class="kachel kachel--rich">
                    <img class="bereich-grafik" src="../assets/img/brand/icon-bankverbindung.svg" alt="">
                    <span class="kachel__title">Bankverbindung</span>
                    <span class="kachel__subtitle">SEPA-Mandat und Kontodaten</span>
                    <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
                </a>
            <?php endif; ?>
        </div>
    </main>
    <script src="../assets/js/menue.js" defer></script>
</body>
</html>
