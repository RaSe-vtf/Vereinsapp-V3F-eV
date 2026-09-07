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
        <h2 style="margin-top:0;">Willkommen, <?= e($mitglied['vorname']) ?></h2>

        <div class="kachel-grid">
            <a href="index.php" class="kachel">
                <span class="kachel-icon">👤</span>
                <span>Meine Daten</span>
            </a>
            <?php if ($mitglied['rolle'] === 'vorstandsmitglied'): ?>
                <a href="vorstand/antraege.php" class="kachel">
                    <span class="kachel-icon">🏢</span>
                    <span>Geschäftsstelle</span>
                </a>
            <?php endif; ?>
            <?php if (!empty($mitglied['ist_admin'])): ?>
                <a href="admin/konten.php" class="kachel">
                    <span class="kachel-icon">⚙️</span>
                    <span>Admin</span>
                </a>
            <?php endif; ?>
        </div>
    </main>
    <script src="../assets/js/menue.js" defer></script>
</body>
</html>
