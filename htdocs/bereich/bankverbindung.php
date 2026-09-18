<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$mitglied = requireMemberLogin('../login.php');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bankverbindung &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=8">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#5b9bd5">
</head>
<body>
    <?php
    $tiefe = '';
    $seitenUntertitel = 'Bankverbindung';
    $aktivReiter = 'meine-daten';
    $zurueck = 'index.php';
    require __DIR__ . '/../../includes/kopf.php';
    ?>

    <main class="container">
        <div class="card">
            <h2>Bankverbindung</h2>

            <?php if ($mitglied['sepa_erteilt_am'] !== null): ?>
                <p class="text-muted">Aus Sicherheitsgründen nicht direkt änderbar. Für eine neue Bankverbindung bitte das SEPA-Mandat erneut ausfüllen &ndash; der Vorstand wird darüber automatisch informiert.</p>

                <table class="tabelle-eigenschaften">
                    <tr><th>Kontoinhaber</th><td><?= e((string) $mitglied['sepa_kontoinhaber']) ?></td></tr>
                    <tr><th>IBAN</th><td class="nowrap-wert"><?= e((string) $mitglied['sepa_iban']) ?></td></tr>
                    <tr><th>BIC</th><td class="nowrap-wert"><?= $mitglied['sepa_bic'] ? e($mitglied['sepa_bic']) : '&ndash;' ?></td></tr>
                    <tr><th>Mandatsreferenz</th><td class="nowrap-wert"><?= e((string) $mitglied['sepa_mandatsreferenz']) ?></td></tr>
                    <tr><th>Erteilt am</th><td class="nowrap-wert"><?= e((new DateTime($mitglied['sepa_erteilt_am']))->format('d.m.Y')) ?></td></tr>
                </table>

                <a href="sepa_mandat.php" class="btn btn-secondary">Bankverbindung ändern</a>
            <?php else: ?>
                <p class="text-muted">Es liegt noch keine Bankverbindung vor.</p>
                <a href="sepa_mandat.php" class="btn">SEPA-Mandat ausfüllen</a>
            <?php endif; ?>
        </div>
    </main>
    <script src="../assets/js/menue.js" defer></script>
</body>
</html>
