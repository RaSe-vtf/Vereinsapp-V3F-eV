<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$mitglied = requireMemberLogin('../login.php', false);
$istAenderung = $mitglied['sepa_erteilt_am'] !== null;

$fehler = [];
$werte = [
    'kontoinhaber' => $mitglied['sepa_kontoinhaber'] ?? ($mitglied['vorname'] . ' ' . $mitglied['nachname']),
    'iban' => $mitglied['sepa_iban'] ?? '',
    'bic' => $mitglied['sepa_bic'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $fehler[] = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.';
    } else {
        foreach ($werte as $feld => $default) {
            $werte[$feld] = trim((string) ($_POST[$feld] ?? ''));
        }
        $werte['iban'] = strtoupper(str_replace(' ', '', $werte['iban']));
        $werte['bic'] = strtoupper(str_replace(' ', '', $werte['bic']));

        if ($werte['kontoinhaber'] === '') {
            $fehler[] = 'Bitte gib den Namen des Kontoinhabers an.';
        }
        if (!istGueltigeIban($werte['iban'])) {
            $fehler[] = 'Bitte gib eine gültige IBAN an.';
        }
        if (empty($_POST['zustimmung'])) {
            $fehler[] = 'Bitte bestätige das SEPA-Lastschriftmandat, um fortzufahren.';
        }

        if (empty($fehler)) {
            $mandatsreferenz = 'M' . str_pad((string) $mitglied['id'], 6, '0', STR_PAD_LEFT) . '-' . date('Y');
            $stmt = getPdo()->prepare(
                'UPDATE mitglieder SET
                    sepa_kontoinhaber = :kontoinhaber, sepa_iban = :iban, sepa_bic = :bic,
                    sepa_mandatsreferenz = :mandatsreferenz, sepa_erteilt_am = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'kontoinhaber' => $werte['kontoinhaber'],
                'iban' => $werte['iban'],
                'bic' => $werte['bic'] !== '' ? $werte['bic'] : null,
                'mandatsreferenz' => $mandatsreferenz,
                'id' => $mitglied['id'],
            ]);

            if ($istAenderung) {
                sendeEinzelMail(
                    MAIL_ABSENDER_EMAIL,
                    'Bankverbindung geändert: ' . $mitglied['vorname'] . ' ' . $mitglied['nachname'],
                    $mitglied['vorname'] . ' ' . $mitglied['nachname'] . " hat soeben die Bankverbindung für das SEPA-Lastschriftmandat geändert.\n\n"
                    . "Kontoinhaber: " . $werte['kontoinhaber'] . "\n"
                    . "IBAN: " . $werte['iban'] . "\n"
                    . "BIC: " . ($werte['bic'] !== '' ? $werte['bic'] : '-') . "\n"
                    . "Mandatsreferenz: " . $mandatsreferenz
                );
            }

            header('Location: home.php');
            exit;
        }
    }
}

$tiefe = '';
$aktivReiter = null;
$zurueck = null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SEPA-Lastschriftmandat &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
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
            <h2 style="margin-top:0;">SEPA-Lastschriftmandat</h2>
            <p>Bevor es weitergeht, benötigen wir einmalig dein Einverständnis für den Beitragseinzug per Lastschrift.</p>

            <?php if (!empty($fehler)): ?>
                <div class="alert alert-error">
                    <ul style="margin:0; padding-left:20px;">
                        <?php foreach ($fehler as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card" style="background:var(--farbe-bg); box-shadow:none;">
                <p style="margin-top:0;"><strong>Zahlungsempfänger:</strong> <?= e(vereinNameNowrap()) ?><br>
                <strong>Gläubiger-Identifikationsnummer:</strong> <?= defined('SEPA_GLAEUBIGER_ID') ? e(SEPA_GLAEUBIGER_ID) : 'noch nicht hinterlegt' ?><br>
                <strong>Mandatsreferenz:</strong> wird bei Erteilung vergeben und dir angezeigt</p>

                <p>Ich ermächtige <?= e(vereinNameNowrap()) ?>, Zahlungen von meinem Konto mittels Lastschrift einzuziehen, die gemäß der Beitragsordnung sowie der Startpassregelung der DTU (Deutsche Triathlon Union) entstehen. Zugleich weise ich mein Kreditinstitut an, die von <?= e(vereinNameNowrap()) ?> auf mein Konto gezogenen Lastschriften einzulösen.</p>

                <p class="text-muted" style="margin-bottom:0;">Hinweis: Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen. Es gelten dabei die mit meinem Kreditinstitut vereinbarten Bedingungen.</p>
            </div>

            <?php if ($istAenderung): ?>
                <p class="text-muted">Der Vorstand wird automatisch per E-Mail über die Änderung informiert.</p>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                <label class="required" for="kontoinhaber">Kontoinhaber</label>
                <input type="text" id="kontoinhaber" name="kontoinhaber" value="<?= e($werte['kontoinhaber']) ?>" required>

                <label class="required" for="iban">IBAN</label>
                <input type="text" id="iban" name="iban" value="<?= e($werte['iban']) ?>" placeholder="DE00 0000 0000 0000 0000 00" required>

                <label for="bic">BIC (optional)</label>
                <input type="text" id="bic" name="bic" value="<?= e($werte['bic']) ?>">

                <label class="inline" style="margin-top:16px;">
                    <input type="checkbox" name="zustimmung" value="1" required>
                    <span class="required">Ich erteile das oben stehende SEPA-Lastschriftmandat.</span>
                </label>

                <div style="margin-top:18px;">
                    <button type="submit" class="btn"><?= $istAenderung ? 'Bankverbindung ändern' : 'Mandat erteilen' ?></button>
                </div>
            </form>
        </div>
    </main>
    <script src="../assets/js/menue.js" defer></script>
</body>
</html>
