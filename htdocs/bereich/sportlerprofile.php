<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$mitglied = requireMemberLogin('../login.php');
$pdo = getPdo();

$mitgliederListe = $pdo->query(
    'SELECT id, vorname, nachname, foto_dateiname FROM mitglieder WHERE aktiv = 1 ORDER BY vorname, nachname'
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
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php require __DIR__ . '/../../includes/kopf.php'; ?>

    <main class="container">
        <h2 style="margin-top:0;">Sportlerprofile</h2>
        <p class="text-muted">Alle aktiven Mitglieder von <?= e(vereinNameNowrap()) ?></p>

        <?php if (empty($mitgliederListe)): ?>
            <p>Noch keine aktiven Mitglieder vorhanden.</p>
        <?php else: ?>
            <div class="sportler-grid">
                <?php foreach ($mitgliederListe as $m): ?>
                    <a href="sportlerprofil.php?id=<?= (int) $m['id'] ?>" class="sportler-kachel">
                        <?php if ($m['foto_dateiname']): ?>
                            <img class="foto-thumb" style="width:72px;height:72px;" src="foto.php?typ=mitglied&id=<?= (int) $m['id'] ?>" alt="Foto von <?= e($m['vorname'] . ' ' . $m['nachname']) ?>">
                        <?php else: ?>
                            <span class="foto-thumb foto-thumb--platzhalter" style="width:72px;height:72px;"><?= e(mb_substr($m['vorname'], 0, 1) . mb_substr($m['nachname'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <span class="name"><?= e($m['vorname']) ?><br><?= e($m['nachname']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <script src="../assets/js/menue.js" defer></script>
</body>
</html>
