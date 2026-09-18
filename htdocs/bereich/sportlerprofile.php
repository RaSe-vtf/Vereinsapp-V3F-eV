<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$mitglied = requireMemberLogin('../login.php');
$pdo = getPdo();

$mitgliederListe = $pdo->query(
    'SELECT id, vorname, nachname, foto_dateiname, ort, lieblingsdisziplin FROM mitglieder WHERE aktiv = 1 ORDER BY vorname, nachname'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sportlerprofile &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=10">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#5b9bd5">
</head>
<body>
    <!-- Race-Konzept (Version 2): eigener Header statt includes/kopf.php,
         damit alle anderen Seiten unveraendert bleiben. Menue-Funktion
         1:1 aus kopf.php uebernommen. Foto-Hero analog zu login.php,
         home.php und der Meine-Daten-Seite. Siehe Master-Prompt in
         CLAUDE.md. -->
    <div class="home-hero" style="background-image:url('../assets/img/brand/sportlerprofile-hero.jpg');">
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
                    <a href="index.php">Meine Daten</a>
                    <a href="sportlerprofile.php" class="active">Sportlerprofile</a>
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
            <span class="kicker">The Team</span>
            <h2>Sportlerprofile</h2>
            <p class="home-hero__claim">Gemeinsam am Start. Gemeinsam im Ziel.</p>
            <div class="home-hero__accent">
                <span style="background:#5b9bd5;"></span>
                <span style="background:#ff3399;"></span>
                <span style="background:#8e44ad;"></span>
                <span style="background:#fadd06;"></span>
            </div>
        </div>
    </div>

    <main class="container">
        <p class="text-muted">Alle aktiven Mitglieder von <?= e(vereinNameNowrap()) ?></p>

        <?php if (empty($mitgliederListe)): ?>
            <p>Noch keine aktiven Mitglieder vorhanden.</p>
        <?php else: ?>
            <div class="sportler-suche">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <input type="text" id="sportler-suchfeld" placeholder="Mitglieder suchen ..." aria-label="Mitglieder suchen">
            </div>

            <div class="sportler-grid" id="sportler-grid">
                <?php foreach ($mitgliederListe as $m): ?>
                    <a href="sportlerprofil.php?id=<?= (int) $m['id'] ?>" class="sportler-kachel" data-name="<?= e(mb_strtolower($m['vorname'] . ' ' . $m['nachname'])) ?>">
                        <div class="sportler-kachel__accent">
                            <span style="background:#ff3399;"></span>
                            <span style="background:#fadd06;"></span>
                            <span style="background:#5b9bd5;"></span>
                        </div>
                        <?php if ($m['foto_dateiname']): ?>
                            <img class="foto-thumb" style="width:72px;height:72px;" src="foto.php?typ=mitglied&id=<?= (int) $m['id'] ?>" alt="Foto von <?= e($m['vorname'] . ' ' . $m['nachname']) ?>">
                        <?php else: ?>
                            <span class="foto-thumb foto-thumb--platzhalter" style="width:72px;height:72px;"><?= e(mb_substr($m['vorname'], 0, 1) . mb_substr($m['nachname'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <span class="name"><?= e($m['vorname']) ?><br><?= e($m['nachname']) ?></span>
                        <div class="disziplin-icons">
                            <img class="<?= $m['lieblingsdisziplin'] === 'schwimmen' ? 'ist-aktiv' : '' ?>" src="../assets/img/brand/icon-schwimmen.png" alt="Schwimmen">
                            <img class="<?= $m['lieblingsdisziplin'] === 'radfahren' ? 'ist-aktiv' : '' ?>" src="../assets/img/brand/icon-radfahren.png" alt="Radfahren">
                            <img class="<?= $m['lieblingsdisziplin'] === 'laufen' ? 'ist-aktiv' : '' ?>" src="../assets/img/brand/icon-laufen.png" alt="Laufen">
                        </div>
                        <?php if ($m['ort']): ?>
                            <span class="ort">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                <?= e($m['ort']) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <script src="../assets/js/menue.js" defer></script>
    <script>
        (function () {
            var feld = document.getElementById('sportler-suchfeld');
            var grid = document.getElementById('sportler-grid');
            if (!feld || !grid) return;
            var karten = grid.querySelectorAll('.sportler-kachel');
            feld.addEventListener('input', function () {
                var suche = feld.value.trim().toLowerCase();
                karten.forEach(function (karte) {
                    karte.hidden = suche !== '' && karte.dataset.name.indexOf(suche) === -1;
                });
            });
        })();
    </script>
</body>
</html>
