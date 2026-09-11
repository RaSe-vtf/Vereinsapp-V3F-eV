<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (currentMitglied() !== null) {
    header('Location: bereich/home.php');
    exit;
}

$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $fehler = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $passwort = (string) ($_POST['passwort'] ?? '');

        $stmt = getPdo()->prepare('SELECT * FROM mitglieder WHERE email = :email AND aktiv = 1');
        $stmt->execute(['email' => $email]);
        $mitglied = $stmt->fetch();

        if (!$mitglied || $mitglied['passwort_hash'] === null) {
            $fehler = 'E-Mail oder Passwort falsch, oder es wurde noch kein Passwort für dieses Konto vergeben. Bitte wende dich an den Vorstand.';
        } elseif (!password_verify($passwort, $mitglied['passwort_hash'])) {
            $fehler = 'E-Mail oder Passwort falsch.';
        } else {
            session_regenerate_id(true);
            $_SESSION['mitglied_id'] = (int) $mitglied['id'];
            issueRememberToken((int) $mitglied['id']);
            header('Location: bereich/home.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mitglieder-Login &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <header class="top-header top-header--race">
        <div class="top-header__inner">
            <img class="top-header__logo" src="assets/img/brand/logo-wing-v-blue.svg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <div class="top-header__title"><?= e(APP_NAME) ?></div>
        </div>
    </header>
    <img class="race-line-strip" src="assets/img/brand/race-line-bg-checkin.svg" alt="" aria-hidden="true">

    <main class="container">
        <div class="card" style="max-width:360px; margin:0 auto;">
            <span class="kicker">01 Check-in</span>
            <h2 style="margin-top:0;">Mitglieder-Login</h2>

            <?php if ($fehler !== ''): ?>
                <div class="alert alert-error"><?= e($fehler) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <label class="required" for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" required autofocus>
                <label class="required" for="passwort">Passwort</label>
                <input type="password" id="passwort" name="passwort" required>
                <div style="margin-top:18px;">
                    <button type="submit" class="btn">Anmelden</button>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <a href="index.php">Zur Startseite</a>
        <a href="antrag.php">Aufnahmeantrag stellen</a>
    </footer>
</body>
</html>
