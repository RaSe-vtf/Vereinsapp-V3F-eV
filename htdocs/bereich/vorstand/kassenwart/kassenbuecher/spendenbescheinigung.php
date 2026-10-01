<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../../includes/db.php';
require_once __DIR__ . '/../../../../../includes/functions.php';
require_once __DIR__ . '/../../../../../includes/auth.php';

$mitglied = requireVorstand('../../../../login.php', '../../../index.php');
$pdo = getPdo();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM kontobewegungen WHERE id = :id');
$stmt->execute(['id' => $id]);
$buchung = $stmt->fetch();

if (!$buchung || (float) $buchung['betrag'] < 0) {
    http_response_code(404);
    exit('Keine Einnahme mit dieser ID gefunden.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'als_ausgestellt_markieren') {
    if (checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $pdo->prepare('UPDATE kontobewegungen SET spendenbescheinigung_ausgestellt_am = CURDATE() WHERE id = :id')
            ->execute(['id' => $id]);
        header('Location: spendenbescheinigung.php?id=' . $id
            . '&spender_name=' . urlencode((string) ($_POST['spender_name'] ?? ''))
            . '&spender_strasse=' . urlencode((string) ($_POST['spender_strasse'] ?? ''))
            . '&spender_plz_ort=' . urlencode((string) ($_POST['spender_plz_ort'] ?? '')));
        exit;
    }
}

$fehlendeDaten = fehlendeSpendenbescheinigungsDaten();

$spenderName = trim((string) ($_GET['spender_name'] ?? $buchung['beteiligter'] ?? ''));
$spenderStrasse = trim((string) ($_GET['spender_strasse'] ?? ''));
$spenderPlzOrt = trim((string) ($_GET['spender_plz_ort'] ?? ''));

$vorsitzende = empty($fehlendeDaten) ? holeAktuelleVorsitzende($pdo) : null;

$tiefe = '../../../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = 'vereinskonto.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Spendenbescheinigung &ndash; Kassenbücher &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../../../assets/css/style.css?v=<?= cacheV('assets/css/style.css') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../../../assets/img/favicon-16.png">
    <meta name="theme-color" content="#1f7a8c">
    <style>
        .zuwendung-hinweis { margin-bottom: 20px; }
        .zuwendung-papier {
            background: #fff;
            border: 1px solid var(--farbe-border);
            border-radius: 8px;
            padding: 36px;
            max-width: 720px;
            margin: 0 auto;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .zuwendung-papier h2 { font-size: 1.1rem; text-align: center; margin-bottom: 24px; }
        .zuwendung-papier .block { margin-bottom: 18px; }
        .zuwendung-papier .fussnote { font-size: 0.78rem; color: var(--farbe-text-muted, #666); margin-top: 24px; }
        .zuwendung-papier .unterschrift { margin-top: 48px; }
        .zuwendung-aktionen { max-width: 720px; margin: 20px auto 0; display: flex; gap: 10px; flex-wrap: wrap; }
        @media print {
            .zuwendung-hinweis, .zuwendung-aktionen, .top-header, nav, .seiten-titel-block, form.belegform { display: none !important; }
            .zuwendung-papier { border: none; padding: 0; }
            body { background: #fff; }
        }
    </style>
</head>
<body>
    <?php if (empty($fehlendeDaten)): ?>
    <?php require __DIR__ . '/../../../../../includes/kopf.php'; ?>
    <?php else: ?>
    <header class="top-header">
        <div class="top-header__inner">
            <div class="top-header__title"><?= e(APP_NAME) ?></div>
        </div>
    </header>
    <?php endif; ?>

    <main class="container" style="max-width:820px;">

        <?php if (!empty($fehlendeDaten)): ?>
            <div class="card">
                <h2 style="margin-top:0;">Spendenbescheinigung nicht verfügbar</h2>
                <div class="alert alert-warning">
                    <strong>Diese Funktion bleibt inaktiv, bis folgende Daten in <code>private/config.php</code> eingetragen sind:</strong>
                    <ul style="margin:10px 0 0; padding-left:20px;">
                        <?php foreach ($fehlendeDaten as $f): ?>
                            <li><?= e($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <p class="text-muted">Diese Angaben setzen voraus, dass euer Freistellungsbescheid vom Finanzamt bereits vorliegt. Sobald das der Fall ist, in <code>config.example.php</code> nachsehen, wie die Felder heißen, und in <code>private/config.php</code> eintragen.</p>
                <a class="btn btn-secondary" href="vereinskonto.php">&larr; Zurück zum Vereinskonto</a>
            </div>
        <?php else: ?>

            <div class="card zuwendung-hinweis">
                <h2 style="margin-top:0;">Spendenbescheinigung erstellen</h2>
                <p class="text-muted">Für die Einnahme vom <?= e((new DateTime($buchung['buchungsdatum']))->format('d.m.Y')) ?> über <?= number_format((float) $buchung['betrag'], 2, ',', '.') ?> €<?= $buchung['verwendungszweck'] ? ' (' . e((string) $buchung['verwendungszweck']) . ')' : '' ?>.</p>
                <div class="alert alert-warning">
                    Dieser Text folgt dem amtlichen Muster für Geldzuwendungen (§ 10b EStG), wurde aber nicht gegen die jeweils aktuell gültige BMF-Fassung geprüft &ndash; bitte vor dem ersten tatsächlichen Einsatz mit dem aktuellen Muster bzw. eurer Steuerberatung abgleichen. Diese Hinweiszeile wird beim Drucken automatisch ausgeblendet.
                </div>

                <form method="get" class="belegform">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <div class="form-row">
                        <div>
                            <label for="spender_name">Name des Zuwendenden</label>
                            <input type="text" id="spender_name" name="spender_name" value="<?= e($spenderName) ?>">
                        </div>
                        <div>
                            <label for="spender_strasse">Straße und Hausnummer</label>
                            <input type="text" id="spender_strasse" name="spender_strasse" value="<?= e($spenderStrasse) ?>">
                        </div>
                    </div>
                    <label for="spender_plz_ort">PLZ und Ort</label>
                    <input type="text" id="spender_plz_ort" name="spender_plz_ort" value="<?= e($spenderPlzOrt) ?>">
                    <div style="margin-top:10px;">
                        <button type="submit" class="btn btn-secondary">Vorschau aktualisieren</button>
                    </div>
                </form>
            </div>

            <div class="zuwendung-papier">
                <p><?= e(vereinNameNowrap()) ?><br><?= e(VEREIN_ANSCHRIFT) ?></p>

                <h2>Bestätigung über Geldzuwendungen<br>im Sinne des § 10b des Einkommensteuergesetzes</h2>

                <div class="block">
                    <strong>Name und Anschrift des Zuwendenden:</strong><br>
                    <?= $spenderName !== '' ? e($spenderName) . '<br>' : '<em>&ndash; bitte oben eintragen &ndash;</em><br>' ?>
                    <?= $spenderStrasse !== '' ? e($spenderStrasse) . '<br>' : '' ?>
                    <?= $spenderPlzOrt !== '' ? e($spenderPlzOrt) : '' ?>
                </div>

                <div class="block">
                    <strong>Betrag der Zuwendung</strong><br>
                    in Ziffern: <?= number_format((float) $buchung['betrag'], 2, ',', '.') ?> €<br>
                    in Buchstaben: <?= e(betragInWorten((float) $buchung['betrag'])) ?>
                </div>

                <div class="block">
                    <strong>Tag der Zuwendung:</strong> <?= e((new DateTime($buchung['buchungsdatum']))->format('d.m.Y')) ?>
                </div>

                <div class="block">
                    Es handelt sich um eine Geldzuwendung. Es handelt sich nicht um den Verzicht auf die Erstattung von Aufwendungen.
                </div>

                <div class="block">
                    Wir sind wegen Förderung des Sports nach dem Freistellungsbescheid des Finanzamts <?= e(FINANZAMT_NAME) ?>, Steuernummer <?= e(VEREIN_STEUERNUMMER) ?>, vom <?= e((new DateTime(FREISTELLUNGSBESCHEID_DATUM))->format('d.m.Y')) ?> nach § 5 Abs. 1 Nr. 9 des Körperschaftsteuergesetzes von der Körperschaftsteuer und nach § 3 Nr. 6 des Gewerbesteuergesetzes von der Gewerbesteuer befreit.
                </div>

                <div class="block">
                    Es wird bestätigt, dass die Zuwendung nur zur Förderung des Satzungszwecks verwendet wird und dass über diese Zuwendung keine weitere Bestätigung ausgestellt wurde bzw. wird.
                </div>

                <div class="unterschrift">
                    <?= e(VEREIN_NAME) ?>, den <?= e((new DateTime())->format('d.m.Y')) ?><br><br>
                    _____________________________<br>
                    <?= $vorsitzende !== null ? e($vorsitzende) . ' (Vorsitz)' : '(Unterschrift Vorstand)' ?>
                </div>

                <p class="fussnote">Hinweis: Diese Bestätigung wird nicht als Nachweis für die steuerliche Berücksichtigung der Zuwendung anerkannt, wenn das zugewendete Geld nicht zu den angegebenen steuerbegünstigten Zwecken verwendet wird oder wenn Werbung für die begünstigte Körperschaft mit dieser Bestätigung betrieben wird. Zuwendungsbestätigungen, die vorsätzlich oder grob fahrlässig unrichtig ausgestellt sind, sowie Veranlasser- und Vertrauenshaftungen regelt § 10b Abs. 4 EStG.</p>
            </div>

            <div class="zuwendung-aktionen">
                <button type="button" class="btn" onclick="window.print()">Drucken</button>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="aktion" value="als_ausgestellt_markieren">
                    <input type="hidden" name="spender_name" value="<?= e($spenderName) ?>">
                    <input type="hidden" name="spender_strasse" value="<?= e($spenderStrasse) ?>">
                    <input type="hidden" name="spender_plz_ort" value="<?= e($spenderPlzOrt) ?>">
                    <button type="submit" class="btn btn-secondary">
                        <?= $buchung['spendenbescheinigung_ausgestellt_am'] !== null
                            ? 'Erneut als ausgestellt markieren (heute)'
                            : 'Als ausgestellt markieren (heute)' ?>
                    </button>
                </form>
                <a class="btn btn-secondary" href="vereinskonto.php">&larr; Zurück zum Vereinskonto</a>
            </div>

        <?php endif; ?>
    </main>
</body>
</html>
