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
    <title>Mein Bereich &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="top-header">
        <div class="top-header__inner">
            <img class="top-header__logo" src="../assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <div>
                <div class="top-header__title"><?= e(APP_NAME) ?></div>
                <div class="top-header__subtitle"><?= e($mitglied['vorname'] . ' ' . $mitglied['nachname']) ?> &middot; <?= e(rollenLabel($mitglied['rolle'])) ?></div>
            </div>
            <div style="margin-left:auto;">
                <a href="../logout.php" class="btn btn-secondary">Abmelden</a>
            </div>
        </div>
    </header>

    <main class="container">
        <nav class="tabs">
            <a href="index.php" class="active">Meine Daten</a>
            <?php if ($mitglied['rolle'] === 'vorstandsmitglied'): ?>
                <a href="vorstand/antraege.php">Vorstand</a>
            <?php endif; ?>
        </nav>

        <div class="card">
            <h2>Meine Daten</h2>

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

            <?php if ($mitglied['foto_dateiname']): ?>
                <img class="foto-preview" src="foto.php?typ=mitglied&id=<?= (int) $mitglied['id'] ?>" alt="Mein Foto" style="margin-bottom:16px;">
            <?php endif; ?>

            <table>
                <tr><th>Name</th><td><?= e($mitglied['vorname'] . ' ' . $mitglied['nachname']) ?></td></tr>
                <tr><th>Rolle</th><td><?= e(rollenLabel($mitglied['rolle'])) ?></td></tr>
                <tr><th>Geburtsdatum</th><td><?= e((new DateTime($mitglied['geburtsdatum']))->format('d.m.Y')) ?></td></tr>
                <tr><th>Adresse</th><td><?= e($mitglied['strasse_hausnummer']) ?>, <?= e($mitglied['plz'] . ' ' . $mitglied['ort']) ?></td></tr>
                <tr><th>Telefon</th><td><?= e($mitglied['telefon']) ?></td></tr>
                <tr><th>E-Mail</th><td><?= e($mitglied['email']) ?></td></tr>
                <tr><th>Instagram</th><td><?= $mitglied['instagram'] ? '@' . e($mitglied['instagram']) : '&ndash;' ?></td></tr>
            </table>

            <p class="text-muted" style="margin-top:16px;">Änderungen an deinen Daten bitte über den Vorstand vornehmen lassen.</p>
        </div>

        <div class="card" style="margin-top:20px;">
            <h2>Passwort ändern</h2>
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
</body>
</html>
