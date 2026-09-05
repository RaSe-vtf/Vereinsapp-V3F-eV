<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!empty($_SESSION['admin_eingeloggt'])) {
    header('Location: index.php');
    exit;
}

$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $fehler = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.';
    } else {
        $passwort = (string) ($_POST['passwort'] ?? '');
        if (password_verify($passwort, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['admin_eingeloggt'] = true;
            header('Location: index.php');
            exit;
        }
        $fehler = 'Falsches Passwort.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vorstand-Login &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="top-header">
        <div class="top-header__inner">
            <img class="top-header__logo" src="../assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <div>
                <div class="top-header__title"><?= e(APP_NAME) ?></div>
                <div class="top-header__subtitle">Vorstandsbereich</div>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="card" style="max-width:360px; margin:0 auto;">
            <h2>Vorstand-Login</h2>

            <?php if ($fehler !== ''): ?>
                <div class="alert alert-error"><?= e($fehler) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <label class="required" for="passwort">Passwort</label>
                <input type="password" id="passwort" name="passwort" required autofocus>
                <div style="margin-top:18px;">
                    <button type="submit" class="btn">Anmelden</button>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <a href="../index.php">Zur Startseite</a>
    </footer>
</body>
</html>
