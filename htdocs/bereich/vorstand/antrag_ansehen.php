<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');

$id = (int) ($_GET['id'] ?? 0);
$stmt = getPdo()->prepare('SELECT * FROM antraege WHERE id = :id');
$stmt->execute(['id' => $id]);
$antrag = $stmt->fetch();

if (!$antrag) {
    http_response_code(404);
    die('Antrag nicht gefunden.');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($antrag['vorname'] . ' ' . $antrag['nachname']) ?> &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php
    $tiefe = '../';
    $seitenUntertitel = 'Geschäftsstelle';
    $aktivReiter = 'geschaeftsstelle';
    $zurueck = 'antraege.php';
    require __DIR__ . '/../../../includes/kopf.php';
    ?>

    <main class="container">
        <div class="card">
            <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
                <img class="foto-preview foto-zoombar" src="../foto.php?typ=antrag&id=<?= (int) $antrag['id'] ?>" alt="Foto von <?= e($antrag['vorname']) ?>">

                <div style="flex:1; min-width:240px;">
                    <h2 style="margin-top:0;"><?= e($antrag['vorname'] . ' ' . $antrag['nachname']) ?></h2>
                    <span class="badge badge-<?= e($antrag['status']) ?>"><?= e($antrag['status']) ?></span>

                    <table style="margin-top:16px;">
                        <tr><th>Geburtsdatum</th><td><?= e((new DateTime($antrag['geburtsdatum']))->format('d.m.Y')) ?></td></tr>
                        <tr><th>Geburtsort</th><td><?= e($antrag['geburtsort']) ?></td></tr>
                        <tr><th>Adresse</th><td><?= e($antrag['strasse_hausnummer']) ?>, <?= e($antrag['plz'] . ' ' . $antrag['ort']) ?></td></tr>
                        <tr><th>Telefon</th><td><?= e($antrag['telefon']) ?></td></tr>
                        <tr><th>E-Mail</th><td><a href="mailto:<?= e($antrag['email']) ?>"><?= e($antrag['email']) ?></a></td></tr>
                        <tr><th>Instagram</th><td><?= $antrag['instagram'] ? '@' . e($antrag['instagram']) : '&ndash;' ?></td></tr>
                        <tr><th>Satzung akzeptiert</th><td><?= $antrag['einverstaendnis_satzung'] ? 'Ja' : 'Nein' ?></td></tr>
                        <tr><th>Datenschutz zur Kenntnis genommen</th><td><?= $antrag['einverstaendnis_datenschutz'] ? 'Ja' : 'Nein' ?></td></tr>
                        <tr><th>Bildnutzung Social Media erlaubt</th><td><?= $antrag['einverstaendnis_bildnutzung'] ? 'Ja' : 'Nein' ?></td></tr>
                        <tr><th>Eingegangen am</th><td><?= e((new DateTime($antrag['erstellt_am']))->format('d.m.Y H:i')) ?> Uhr</td></tr>
                    </table>
                </div>
            </div>

            <div style="margin-top:24px; display:flex; gap:10px; flex-wrap:wrap;">
                <form method="post" action="antraege.php">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $antrag['id'] ?>">
                    <input type="hidden" name="aktion" value="annehmen">
                    <button type="submit" class="btn">Annehmen</button>
                </form>
                <form method="post" action="antraege.php">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $antrag['id'] ?>">
                    <input type="hidden" name="aktion" value="ablehnen">
                    <button type="submit" class="btn btn-secondary">Ablehnen</button>
                </form>
                <form method="post" action="antraege.php">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $antrag['id'] ?>">
                    <input type="hidden" name="aktion" value="zuruecksetzen">
                    <button type="submit" class="btn btn-secondary">Zurücksetzen auf "Neu"</button>
                </form>
            </div>
        </div>
    </main>
    <script src="../../assets/js/lightbox.js" defer></script>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
