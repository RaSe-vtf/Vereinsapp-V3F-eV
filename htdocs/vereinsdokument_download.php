<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = getPdo()->prepare('SELECT bezeichnung, dateiname, original_dateiname FROM vereinsdokumente WHERE id = :id');
$stmt->execute(['id' => $id]);
$dokument = $stmt->fetch();

if (!$dokument) {
    http_response_code(404);
    exit;
}

$pfad = __DIR__ . '/../private/uploads/vereinsdokumente/' . basename($dokument['dateiname']);
if (!is_file($pfad)) {
    http_response_code(404);
    exit;
}

$anzeigename = $dokument['original_dateiname'] ?? $dokument['bezeichnung'];
$format = strtoupper(pathinfo($dokument['dateiname'], PATHINFO_EXTENSION));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download &ndash; <?= e(APP_NAME) ?></title>
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
        <div class="card" style="text-align:center;">
            <h2><?= e($dokument['bezeichnung']) ?></h2>
            <p>Datei: <strong><?= e($anzeigename) ?></strong> (<?= e($format) ?>)</p>
            <p class="text-muted">Diese Datei kann nicht direkt im Browser angezeigt werden und wird beim Klick heruntergeladen.</p>
            <a class="btn" href="vereinsdokument.php?id=<?= (int) $id ?>">Jetzt herunterladen</a>
        </div>
    </main>

    <footer>
        <a href="index.php">Startseite</a>
        <a href="impressum.php">Impressum</a>
        <a href="datenschutz.php">Datenschutz</a>
    </footer>
</body>
</html>
