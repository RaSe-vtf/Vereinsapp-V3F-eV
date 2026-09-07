<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="top-header">
        <div class="top-header__inner">
            <img class="top-header__logo" src="assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <div>
                <div class="top-header__title"><?= e(APP_NAME) ?></div>
                <div class="top-header__subtitle"><?= e(VEREIN_NAME) ?></div>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="hero">
            <img src="assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <h1>Willkommen bei der <?= e(APP_NAME) ?></h1>
            <p>Hier kannst du deinen Aufnahmeantrag für <?= e(VEREIN_NAME) ?> ausfüllen. Der Vorstand bearbeitet deinen Antrag anschließend.</p>
            <a class="btn" href="antrag.php">Zum Aufnahmeantrag</a>
        </div>
    </main>

    <footer>
        <a href="impressum.php">Impressum</a>
        <a href="datenschutz.php">Datenschutz</a>
        <a href="login.php">Mitglieder-Login</a>
    </footer>
</body>
</html>
