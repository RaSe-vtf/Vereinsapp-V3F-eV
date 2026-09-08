<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$mitglied = requireMemberLogin('../login.php');
$pdo = getPdo();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM mitglieder WHERE id = :id AND aktiv = 1');
$stmt->execute(['id' => $id]);
$angesehen = $stmt->fetch();

if (!$angesehen) {
    http_response_code(404);
    die('Sportlerprofil nicht gefunden.');
}

$istEigenesProfil = (int) $angesehen['id'] === (int) $mitglied['id'];

$tiefe = '';
$seitenUntertitel = 'Sportlerprofile';
$aktivReiter = 'sportlerprofile';
$zurueck = 'sportlerprofile.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($angesehen['vorname'] . ' ' . $angesehen['nachname']) ?> &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php require __DIR__ . '/../../includes/kopf.php'; ?>

    <main class="container">
        <div class="card">
            <div class="sportlerprofil-kopf">
                <?php if ($angesehen['foto_dateiname']): ?>
                    <img class="foto-preview foto-zoombar" src="foto.php?typ=mitglied&id=<?= (int) $angesehen['id'] ?>" alt="Foto von <?= e($angesehen['vorname']) ?>">
                <?php else: ?>
                    <span class="foto-thumb foto-thumb--platzhalter" style="width:120px; height:120px; font-size:2rem;"><?= e(mb_substr($angesehen['vorname'], 0, 1) . mb_substr($angesehen['nachname'], 0, 1)) ?></span>
                <?php endif; ?>

                <div>
                    <h2 style="margin:0 0 6px;"><?= e($angesehen['vorname'] . ' ' . $angesehen['nachname']) ?></h2>
                    <div class="sportlerprofil-tags">
                        <?php if ($angesehen['shirt_groesse']): ?>
                            <span class="sportlerprofil-tag">Shirt: <?= e($angesehen['shirt_groesse']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($istEigenesProfil): ?>
                        <a href="index.php" class="btn btn-secondary" style="margin-top:12px; display:inline-block;">Profil bearbeiten</a>
                    <?php endif; ?>
                </div>
            </div>

            <dl>
                <div class="sportlerprofil-feld">
                    <dt>Geburtsdatum</dt>
                    <dd><?= e((new DateTime($angesehen['geburtsdatum']))->format('d.m.Y')) ?></dd>
                </div>
                <div class="sportlerprofil-feld">
                    <dt>Im Verein seit</dt>
                    <dd><?= e((new DateTime($angesehen['erstellt_am']))->format('d.m.Y')) ?></dd>
                </div>
                <div class="sportlerprofil-feld">
                    <dt>Heimatort</dt>
                    <dd><?= e($angesehen['ort']) ?></dd>
                </div>
                <?php if ($angesehen['telefon']): ?>
                    <div class="sportlerprofil-feld">
                        <dt>Handynummer (für WhatsApp-Zuordnung)</dt>
                        <dd><?= e(maskiereTelefon($angesehen['telefon'])) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($angesehen['instagram']): ?>
                    <div class="sportlerprofil-feld">
                        <dt>Instagram</dt>
                        <dd><a href="https://instagram.com/<?= e($angesehen['instagram']) ?>" target="_blank" rel="noopener">@<?= e($angesehen['instagram']) ?></a></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <?php if ($angesehen['portraet']): ?>
                <hr style="border:none; border-top:1px solid var(--farbe-border); margin:20px 0;">
                <p style="white-space:pre-line;"><?= e($angesehen['portraet']) ?></p>
            <?php endif; ?>
        </div>
    </main>
    <script src="../assets/js/lightbox.js" defer></script>
    <script src="../assets/js/menue.js" defer></script>
</body>
</html>
