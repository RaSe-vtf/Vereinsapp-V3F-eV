<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$mitglied = requireMemberLogin('../login.php');

$fehler = [];
$erfolg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'passwort_aendern') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $fehler[] = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.';
    } else {
        $aktuelles = (string) ($_POST['aktuelles_passwort'] ?? '');
        $neu = (string) ($_POST['neues_passwort'] ?? '');
        $neuWiederholt = (string) ($_POST['neues_passwort_wiederholt'] ?? '');

        if ($mitglied['passwort_hash'] !== null && !password_verify($aktuelles, $mitglied['passwort_hash'])) {
            $fehler[] = 'Das aktuelle Passwort ist nicht korrekt.';
        }
        if (strlen($neu) < 8) {
            $fehler[] = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        }
        if ($neu !== $neuWiederholt) {
            $fehler[] = 'Die Passwort-Wiederholung stimmt nicht überein.';
        }

        if (empty($fehler)) {
            $stmt = getPdo()->prepare('UPDATE mitglieder SET passwort_hash = :hash WHERE id = :id');
            $stmt->execute(['hash' => password_hash($neu, PASSWORD_DEFAULT), 'id' => $mitglied['id']]);
            invalidiereAndereRememberTokens((int) $mitglied['id']);
            $erfolg = 'Dein Passwort wurde geändert.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passwort ändern &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=5">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#5b9bd5">
</head>
<body>
    <?php
    $tiefe = '';
    $seitenUntertitel = 'Passwort ändern';
    $aktivReiter = 'meine-daten';
    $zurueck = 'index.php';
    require __DIR__ . '/../../includes/kopf.php';
    ?>

    <main class="container">
        <div class="card">
            <h2>Passwort ändern</h2>

            <?php if (!empty($fehler)): ?>
                <div class="alert alert-error">
                    <ul style="margin:0; padding-left:20px;">
                        <?php foreach ($fehler as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($erfolg !== ''): ?>
                <div class="alert alert-success"><?= e($erfolg) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="passwort_aendern">

                <?php if ($mitglied['passwort_hash'] !== null): ?>
                    <label class="required" for="aktuelles_passwort">Aktuelles Passwort</label>
                    <input type="password" id="aktuelles_passwort" name="aktuelles_passwort" required>
                <?php endif; ?>

                <label class="required" for="neues_passwort">Neues Passwort</label>
                <input type="password" id="neues_passwort" name="neues_passwort" minlength="8" required>

                <label class="required" for="neues_passwort_wiederholt">Neues Passwort wiederholen</label>
                <input type="password" id="neues_passwort_wiederholt" name="neues_passwort_wiederholt" minlength="8" required>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn">Passwort speichern</button>
                </div>
            </form>
        </div>
    </main>
    <script src="../assets/js/menue.js" defer></script>
</body>
</html>
