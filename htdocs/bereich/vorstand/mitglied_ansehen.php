<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');

$id = (int) ($_GET['id'] ?? 0);
$stmt = getPdo()->prepare('SELECT * FROM mitglieder WHERE id = :id');
$stmt->execute(['id' => $id]);
$angesehen = $stmt->fetch();

if (!$angesehen) {
    http_response_code(404);
    die('Mitglied nicht gefunden.');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($angesehen['vorname'] . ' ' . $angesehen['nachname']) ?> &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <header class="top-header">
        <div class="top-header__inner">
            <img class="top-header__logo" src="../../assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <div>
                <div class="top-header__title"><?= e(APP_NAME) ?></div>
                <div class="top-header__subtitle">Geschäftsstelle</div>
            </div>
            <div style="margin-left:auto;">
                <a href="../../logout.php" class="btn btn-secondary">Abmelden</a>
            </div>
        </div>
    </header>

    <main class="container">
        <p><a href="mitglieder.php">&larr; Zurück zur Mitgliederverwaltung</a></p>

        <div class="card">
            <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
                <?php if ($angesehen['foto_dateiname']): ?>
                    <img class="foto-preview foto-zoombar" src="../foto.php?typ=mitglied&id=<?= (int) $angesehen['id'] ?>" alt="Foto von <?= e($angesehen['vorname']) ?>">
                <?php endif; ?>

                <div style="flex:1; min-width:240px;">
                    <h2 style="margin-top:0;"><?= e($angesehen['vorname'] . ' ' . $angesehen['nachname']) ?></h2>
                    <span class="badge badge-<?= $angesehen['aktiv'] ? 'angenommen' : 'abgelehnt' ?>"><?= $angesehen['aktiv'] ? 'aktiv' : 'inaktiv' ?></span>

                    <table style="margin-top:16px;">
                        <tr><th>Rolle</th><td><?= e(rollenLabel($angesehen['rolle'])) ?></td></tr>
                        <tr><th>Geburtsdatum</th><td><?= e((new DateTime($angesehen['geburtsdatum']))->format('d.m.Y')) ?></td></tr>
                        <tr><th>Geburtsort</th><td><?= e($angesehen['geburtsort']) ?></td></tr>
                        <tr><th>Adresse</th><td><?= e($angesehen['strasse_hausnummer']) ?>, <?= e($angesehen['plz'] . ' ' . $angesehen['ort']) ?></td></tr>
                        <tr><th>Telefon</th><td><?= e($angesehen['telefon']) ?></td></tr>
                        <tr><th>E-Mail</th><td><a href="mailto:<?= e($angesehen['email']) ?>"><?= e($angesehen['email']) ?></a></td></tr>
                        <tr><th>Instagram</th><td><?= $angesehen['instagram'] ? '@' . e($angesehen['instagram']) : '&ndash;' ?></td></tr>
                        <tr><th>Passwort</th><td><?= $angesehen['passwort_hash'] !== null ? 'gesetzt' : 'nicht gesetzt' ?></td></tr>
                        <tr><th>Mitglied seit</th><td><?= e((new DateTime($angesehen['erstellt_am']))->format('d.m.Y')) ?></td></tr>
                    </table>
                </div>
            </div>

            <p style="margin-top:20px;"><a href="mitglieder.php">Rolle, Status oder Passwort in der Mitgliederverwaltung ändern &rarr;</a></p>
        </div>
    </main>
    <script src="../../assets/js/lightbox.js" defer></script>
</body>
</html>
