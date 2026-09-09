<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');
$pdo = getPdo();

$notizen = $pdo->query(
    'SELECT n.*, m.vorname, m.nachname
     FROM notizen n
     LEFT JOIN mitglieder m ON m.id = n.mitglied_id
     ORDER BY n.aktualisiert_am DESC, n.id DESC'
)->fetchAll();

$flash = takeFlash();
$tiefe = '../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = 'index.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notizen &ndash; Geschäftsstelle &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php require __DIR__ . '/../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 style="margin-top:0;">Notizbuch</h2>
            <p class="text-muted">Freie Notizseiten der Geschäftsstelle - jede Seite hat eine Überschrift und Freitext, den man eintippen oder eindiktieren kann.</p>

            <a href="notiz.php" class="btn">+ Neue Seite anlegen</a>

            <?php if (empty($notizen)): ?>
                <p style="margin-top:18px;">Noch keine Notizen vorhanden.</p>
            <?php else: ?>
                <div class="notiz-liste">
                    <?php foreach ($notizen as $n): ?>
                        <a class="notiz-eintrag" href="notiz.php?id=<?= (int) $n['id'] ?>">
                            <h3><?= e($n['titel']) ?></h3>
                            <div class="notiz-meta">
                                <?= e((new DateTime($n['aktualisiert_am']))->format('d.m.Y H:i')) ?>
                                <?php if ($n['vorname'] !== null): ?>
                                    &middot; <?= e($n['vorname'] . ' ' . $n['nachname']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if (trim($n['inhalt']) !== ''): ?>
                                <div class="notiz-anriss"><?= e($n['inhalt']) ?></div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
