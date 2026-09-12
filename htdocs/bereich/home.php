<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$mitglied = requireMemberLogin('../login.php');

$tiefe = '';
$seitenUntertitel = 'Start';
$aktivReiter = null;
$zurueck = null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Start &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#5b9bd5">
</head>
<body>
    <!-- Race-Konzept (Version 2): eigener Header statt includes/kopf.php,
         damit alle anderen Seiten unveraendert bleiben. Menue-Funktion
         1:1 aus kopf.php uebernommen. Foto-Hero statt flacher Kopfzeile,
         analog zu login.php. Siehe Master-Prompt in CLAUDE.md. -->
    <div class="home-hero" style="background-image:url('../assets/img/brand/transition-hero.jpg');">
        <div class="home-hero__topbar">
            <a href="home.php" class="home-hero__brand" aria-label="Startseite">
                <img class="home-hero__logo" src="../assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
                <span class="home-hero__title"><?= e(APP_NAME) ?></span>
            </a>
            <div class="menu-wrapper">
                <button type="button" class="menu-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="hauptmenue">&#9776;</button>
                <nav class="menu-dropdown" id="hauptmenue" hidden>
                    <a href="index.php">Meine Daten</a>
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
            <span class="kicker">My Transition</span>
            <h2>Willkommen, <?= e($mitglied['vorname']) ?></h2>
            <p class="home-hero__claim">Dein Platz im Verein.</p>
            <div class="home-hero__accent">
                <span style="background:#5b9bd5;"></span>
                <span style="background:#ff3399;"></span>
                <span style="background:#fadd06;"></span>
            </div>
        </div>
    </div>

    <main class="container">
        <div class="kachel-grid">
            <a href="index.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../assets/img/brand/bereich-meine-daten.svg" alt="">
                <span class="kachel__accent"><span style="background:#5b9bd5;"></span><span style="background:#ff3399;"></span></span>
                <span class="kachel__title">Meine Daten</span>
                <span class="kachel__subtitle">Persönliche Daten und Mitgliedschaft</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
            <a href="sportlerprofile.php" class="kachel kachel--rich">
                <img class="bereich-grafik" src="../assets/img/brand/bereich-sportlerprofile.svg" alt="">
                <span class="kachel__accent"><span style="background:#ff3399;"></span><span style="background:#fadd06;"></span></span>
                <span class="kachel__title">Sportlerprofile</span>
                <span class="kachel__subtitle">Unsere Athletinnen und Athleten</span>
                <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
            </a>
            <?php if (!empty($mitglied['vorstandsamt']) || !empty($mitglied['ist_admin'])): ?>
                <a href="vorstand/index.php" class="kachel kachel--rich">
                    <img class="bereich-grafik" src="../assets/img/brand/bereich-geschaeftsstelle.svg" alt="">
                    <span class="kachel__accent"><span style="background:#fadd06;"></span><span style="background:#5b9bd5;"></span></span>
                    <span class="kachel__title">Geschäftsstelle</span>
                    <span class="kachel__subtitle">Informationen, Formulare und Service</span>
                    <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
                </a>
            <?php endif; ?>
            <?php if (!empty($mitglied['ist_admin'])): ?>
                <a href="admin/index.php" class="kachel kachel--rich">
                    <img class="bereich-grafik" src="../assets/img/brand/bereich-admin.svg" alt="">
                    <span class="kachel__accent"><span style="background:#ff3399;"></span><span style="background:#5b9bd5;"></span></span>
                    <span class="kachel__title">Admin</span>
                    <span class="kachel__subtitle">Vereinsverwaltung und Einstellungen</span>
                    <span class="kachel__chevron" aria-hidden="true">&rsaquo;</span>
                </a>
            <?php endif; ?>
        </div>
    </main>
    <script src="../assets/js/menue.js" defer></script>
</body>
</html>
