<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/auth.php';

$mitglied = requireVorstand('../../../login.php', '../../index.php');
$pdo = getPdo();

$mitgliederListe = $pdo->query(
    "SELECT vorname, nachname, rolle, ist_admin, sepa_kontoinhaber, sepa_iban, sepa_bic, sepa_mandatsreferenz, sepa_erteilt_am
     FROM mitglieder
     WHERE aktiv = 1
     ORDER BY nachname, vorname"
)->fetchAll();

$tiefe = '../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = 'index.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bankverbindungen &ndash; Kassenwart &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../../assets/css/style.css?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
    <style>
        /* Bankverbindungen hat viele Spalten (IBAN, BIC, Mandatsreferenz, ...)
           - kompakter als die App-weite Tabellen-Grundschrift, damit mehr
           ohne horizontales Scrollen direkt lesbar ist. */
        .bank-tabelle { font-size: 0.78rem; }
        .bank-tabelle th,
        .bank-tabelle td { padding: 5px 7px; }
        .bank-tabelle .badge { padding: 2px 7px; font-size: 0.7rem; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">
        <nav class="subnav">
            <a href="bankverbindungen.php" class="active">Bankverbindungen</a>
            <a href="beitraege.php">Beiträge</a>
            <a href="export.php">SEPA-Export</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Bankverbindungen</h2>
            <p class="text-muted">Kontoinhaber und IBAN aller aktiven Mitglieder für den Beitragseinzug. Admins und Vorstandsmitglieder gelten im Bankbereich als Vollmitglieder.</p>

            <?php if (empty($mitgliederListe)): ?>
                <p>Noch keine aktiven Mitglieder vorhanden.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="tabelle-einzeilig bank-tabelle">
                    <thead>
                        <tr>
                            <th>Nachname</th>
                            <th>Vorname</th>
                            <th>Rolle</th>
                            <th>Kontoinhaber</th>
                            <th>IBAN</th>
                            <th>BIC</th>
                            <th>Mandatsreferenz</th>
                            <th>Erteilt am</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mitgliederListe as $m): ?>
                            <tr>
                                <td><?= e($m['nachname']) ?></td>
                                <td><?= e($m['vorname']) ?></td>
                                <td><?= e(rollenLabel(bankRolle($m))) ?></td>
                                <?php if ($m['sepa_erteilt_am'] !== null): ?>
                                    <td><?= e((string) $m['sepa_kontoinhaber']) ?></td>
                                    <td><?= e((string) $m['sepa_iban']) ?></td>
                                    <td><?= $m['sepa_bic'] ? e($m['sepa_bic']) : '&ndash;' ?></td>
                                    <td><?= e((string) $m['sepa_mandatsreferenz']) ?></td>
                                    <td><?= e((new DateTime($m['sepa_erteilt_am']))->format('d.m.Y')) ?></td>
                                <?php else: ?>
                                    <td colspan="5"><span class="badge badge-abgelehnt">kein Mandat hinterlegt</span></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="../../../assets/js/menue.js" defer></script>
</body>
</html>
