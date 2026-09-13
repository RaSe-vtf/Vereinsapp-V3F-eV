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

$tiefe = '';
$seitenUntertitel = 'Sportlerprofile';
$aktivReiter = 'sportlerprofile';
$zurueck = 'home.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sportlerprofile &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#5b9bd5">
</head>
<body>
    <?php require __DIR__ . '/../../includes/kopf.php'; ?>

    <main class="container">
        <h2 style="margin-top:0;">Sportlerprofile</h2>
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
