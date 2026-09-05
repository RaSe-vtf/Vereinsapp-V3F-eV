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
    <title>Antrag eingegangen &ndash; <?= e(APP_NAME) ?></title>
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
        <div class="card" style="text-align:center;">
            <h2>Vielen Dank für deinen Aufnahmeantrag!</h2>
            <p>Dein Antrag ist bei uns eingegangen. Der Vorstand von <?= e(VEREIN_NAME) ?> prüft deine Angaben und meldet sich zeitnah bei dir.</p>
            <a class="btn btn-secondary" href="index.php">Zurück zur Startseite</a>
        </div>
    </main>

    <footer>
        <a href="impressum.php">Impressum</a>
        <a href="datenschutz.php">Datenschutz</a>
    </footer>
</body>
</html>
