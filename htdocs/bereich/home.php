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
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <!-- Race-Konzept (Version 2): eigener Header statt includes/kopf.php,
         damit alle anderen Seiten unveraendert bleiben. Menue-Funktion
         1:1 aus kopf.php uebernommen. Siehe Master-Prompt in CLAUDE.md. -->
    <header class="top-header top-header--race">
        <div class="top-header__inner">
            <a href="home.php" class="top-header__logo-link" aria-label="Startseite">
                <img class="top-header__logo" src="../assets/img/brand/logo-wing-v-blue.svg" alt="Logo <?= e(VEREIN_NAME) ?>">
            </a>
            <div class="top-header__title"><?= e(APP_NAME) ?></div>
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
    </header>
    <img class="race-line-strip" src="../assets/img/brand/race-line-bg-transition.svg" alt="" aria-hidden="true">

    <main class="container">
        <span class="kicker">02 My Transition</span>
        <h2 style="margin-top:0;">Willkommen, <?= e($mitglied['vorname']) ?></h2>

        <div class="kachel-grid">
            <a href="index.php" class="kachel">
                <img class="bereich-grafik" src="../assets/img/brand/bereich-meine-daten.svg" alt="">
                <span>Meine Daten</span>
            </a>
            <a href="sportlerprofile.php" class="kachel">
                <img class="bereich-grafik" src="../assets/img/brand/bereich-sportlerprofile.svg" alt="">
                <span>Sportlerprofile</span>
            </a>
            <?php if (!empty($mitglied['vorstandsamt']) || !empty($mitglied['ist_admin'])): ?>
                <a href="vorstand/index.php" class="kachel">
                    <img class="bereich-grafik" src="../assets/img/brand/bereich-geschaeftsstelle.svg" alt="">
                    <span>Geschäftsstelle</span>
                </a>
            <?php endif; ?>
            <?php if (!empty($mitglied['ist_admin'])): ?>
                <a href="admin/index.php" class="kachel">
                    <img class="bereich-grafik" src="../assets/img/brand/bereich-admin.svg" alt="">
                    <span>Admin</span>
                </a>
            <?php endif; ?>
        </div>
    </main>
    <script src="../assets/js/menue.js" defer></script>
</body>
</html>
