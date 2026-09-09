<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$vorsitzende = holeAktuelleVorsitzende(getPdo());
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impressum &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <header class="top-header">
        <div class="top-header__inner">
            <img class="top-header__logo" src="assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <div class="top-header__title"><?= e(APP_NAME) ?></div>
        </div>
    </header>

    <main class="container">
        <div class="card legal">
            <h1>Impressum</h1>

            <p><em>Hinweis: Dieser Text ist eine Vorlage und muss vom Vorstand mit den tatsächlichen
            Vereinsangaben ausgefüllt und rechtlich geprüft werden (z.B. gemäß § 5 TMG).</em></p>

            <h2>Angaben gemäß § 5 TMG</h2>
            <p>
                [Vereinsname] e.V.<br>
                [Straße und Hausnummer]<br>
                [PLZ Ort]
            </p>

            <h2>Vertreten durch</h2>
            <p><?= $vorsitzende !== null ? e($vorsitzende) . ', 1. Vorsitzende/r' : '[Vorname Nachname, 1. Vorsitzende/r]' ?></p>

            <h2>Kontakt</h2>
            <p>
                Telefon: [Telefonnummer]<br>
                E-Mail: [E-Mail-Adresse]
            </p>

            <h2>Registereintrag</h2>
            <p>
                Eintragung im Vereinsregister.<br>
                Registergericht: [Amtsgericht]<br>
                Registernummer: [VR-Nummer]
            </p>

            <h2>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h2>
            <p>[Vorname Nachname, Anschrift]</p>
        </div>
    </main>

    <footer>
        <a href="index.php">Startseite</a>
        <a href="datenschutz.php">Datenschutz</a>
    </footer>
</body>
</html>
