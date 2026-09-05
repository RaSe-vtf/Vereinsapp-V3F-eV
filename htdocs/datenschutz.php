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
    <title>Datenschutzerklärung &ndash; <?= e(APP_NAME) ?></title>
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
        <div class="card legal">
            <h1>Datenschutzerklärung</h1>

            <p><em>Hinweis: Diese Vorlage deckt die wichtigsten Punkte für den Aufnahmeantrag ab, ersetzt aber
            keine rechtliche Prüfung. Bitte vom Vorstand vervollständigen und ggf. rechtlich prüfen lassen.</em></p>

            <h2>1. Verantwortlicher</h2>
            <p>
                [Vereinsname] e.V.<br>
                [Straße, PLZ Ort]<br>
                E-Mail: [E-Mail-Adresse]
            </p>

            <h2>2. Welche Daten wir bei deinem Aufnahmeantrag verarbeiten</h2>
            <p>Beim Ausfüllen des Aufnahmeantrags erheben wir folgende Daten:</p>
            <ul>
                <li>Vor- und Nachname</li>
                <li>Geburtsdatum und Geburtsort</li>
                <li>Adresse</li>
                <li>Telefonnummer und E-Mail-Adresse</li>
                <li>Foto</li>
                <li>optional: Instagram-Benutzername</li>
                <li>deine abgegebenen Einverständniserklärungen</li>
            </ul>

            <h2>3. Zweck und Rechtsgrundlage der Verarbeitung</h2>
            <p>
                Die Verarbeitung erfolgt zur Bearbeitung deines Antrags auf Mitgliedschaft sowie – im Falle der
                Aufnahme – zur Verwaltung deiner Mitgliedschaft (Art. 6 Abs. 1 lit. b DSGVO, Vertragsanbahnung/-erfüllung).
            </p>
            <p>
                Die Verwendung von Fotos/Videos aus dem Vereinsleben für Social-Media-Kanäle des Vereins erfolgt nur,
                wenn du hierzu gesondert deine freiwillige Einwilligung erteilt hast (Art. 6 Abs. 1 lit. a DSGVO). Diese
                Einwilligung ist unabhängig von deiner Mitgliedschaft und kann jederzeit mit Wirkung für die Zukunft
                widerrufen werden, z.B. per E-Mail an [E-Mail-Adresse].
            </p>

            <h2>4. Speicherdauer</h2>
            <p>
                Deine Daten werden für die Dauer der Bearbeitung deines Antrags sowie – im Falle der Aufnahme – für die
                Dauer deiner Mitgliedschaft zuzüglich gesetzlicher Aufbewahrungsfristen gespeichert. Wird dein Antrag
                abgelehnt, löschen wir deine Daten, sofern keine gesetzliche Aufbewahrungspflicht entgegensteht.
            </p>

            <h2>5. Empfänger</h2>
            <p>
                Zugriff auf deine Daten hat ausschließlich der Vorstand von [Vereinsname] e.V. Eine Weitergabe an Dritte
                erfolgt nicht, außer wir sind gesetzlich dazu verpflichtet.
            </p>

            <h2>6. Hosting</h2>
            <p>
                Diese Anwendung wird bei [Hosting-Anbieter, z.B. all-inkl.com] gehostet. Mit dem Hoster besteht ggf. ein
                Auftragsverarbeitungsvertrag gemäß Art. 28 DSGVO.
            </p>

            <h2>7. Deine Rechte</h2>
            <p>
                Du hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit
                sowie Widerspruch gegen die Verarbeitung deiner Daten. Wende dich hierzu an [E-Mail-Adresse]. Außerdem hast
                du das Recht, dich bei einer Datenschutzaufsichtsbehörde zu beschweren.
            </p>
        </div>
    </main>

    <footer>
        <a href="index.php">Startseite</a>
        <a href="impressum.php">Impressum</a>
    </footer>
</body>
</html>
