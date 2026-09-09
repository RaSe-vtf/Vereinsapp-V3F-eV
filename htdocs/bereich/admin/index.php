<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireAdmin('../login.php', '../index.php');

$tiefe = '../';
$seitenUntertitel = 'Admin';
$aktivReiter = 'admin';
$zurueck = '../home.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php require __DIR__ . '/../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">

        <h2 style="margin-top:0;">Admin</h2>

        <div class="kachel-grid">
            <a href="konten.php" class="kachel">
                <span class="kachel-icon">🔑</span>
                <span>Konten</span>
            </a>
            <a href="bilder.php" class="kachel">
                <span class="kachel-icon">🖼️</span>
                <span>Bilder</span>
            </a>
        </div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
