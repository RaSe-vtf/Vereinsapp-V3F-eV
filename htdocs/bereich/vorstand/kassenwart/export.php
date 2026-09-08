<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/auth.php';

$mitglied = requireVorstand('../../../login.php', '../../index.php');
$pdo = getPdo();

$fehler = [];
$ausgewaehlteIds = [];
$faelligkeitsdatum = (new DateTime('+14 days'))->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $fehler[] = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.';
    } else {
        $ausgewaehlteIds = array_map('intval', (array) ($_POST['posten'] ?? []));
        $faelligkeitsdatum = trim((string) ($_POST['faelligkeitsdatum'] ?? ''));
        $datum = DateTime::createFromFormat('Y-m-d', $faelligkeitsdatum);
        $fruehesterTermin = new DateTime('+5 days');

        if (empty($ausgewaehlteIds)) {
            $fehler[] = 'Bitte wähle mindestens einen Beitragsposten aus.';
        }
        if (!$datum || $datum < $fruehesterTermin) {
            $fehler[] = 'Bitte wähle einen Fälligkeitstermin von mindestens 5 Tagen in der Zukunft (Vorlaufzeit für Erstlastschriften).';
        }
        if (!defined('VEREIN_IBAN') || !istGueltigeIban(VEREIN_IBAN)) {
            $fehler[] = 'Für den Verein ist noch keine gültige IBAN hinterlegt (VEREIN_IBAN in private/config.php).';
        }

        if (empty($fehler)) {
            $platzhalter = implode(',', array_fill(0, count($ausgewaehlteIds), '?'));
            $stmtPosten = $pdo->prepare("SELECT * FROM beitragsposten WHERE id IN ($platzhalter) AND aktiv = 1");
            $stmtPosten->execute($ausgewaehlteIds);
            $ausgewaehltePosten = $stmtPosten->fetchAll();

            $mitgliederListe = $pdo->query(
                "SELECT id, vorname, nachname, rolle, ist_admin, sepa_kontoinhaber, sepa_iban, sepa_bic, sepa_mandatsreferenz, sepa_erteilt_am, sepa_erste_lastschrift_erfolgt
                 FROM mitglieder WHERE aktiv = 1"
            )->fetchAll();

            $lastschriften = [];
            $aktualisierenIds = [];

            foreach ($mitgliederListe as $m) {
                $betrag = 0.0;
                $bezeichnungen = [];
                $rolleFuerBeitrag = bankRolle($m);
                foreach ($ausgewaehltePosten as $p) {
                    if ($p['rolle'] === null || $p['rolle'] === $rolleFuerBeitrag) {
                        $betrag += (float) $p['betrag'];
                        $bezeichnungen[] = $p['bezeichnung'];
                    }
                }
                if ($betrag <= 0 || $m['sepa_erteilt_am'] === null) {
                    continue;
                }
                $lastschriften[] = [
                    'name' => (string) $m['sepa_kontoinhaber'],
                    'iban' => (string) $m['sepa_iban'],
                    'bic' => (string) $m['sepa_bic'],
                    'betrag' => $betrag,
                    'mandatsreferenz' => (string) $m['sepa_mandatsreferenz'],
                    'mandatsdatum' => (new DateTime($m['sepa_erteilt_am']))->format('Y-m-d'),
                    'sequenztyp' => $m['sepa_erste_lastschrift_erfolgt'] ? 'RCUR' : 'FRST',
                    'verwendungszweck' => implode(', ', $bezeichnungen),
                ];
                $aktualisierenIds[] = (int) $m['id'];
            }

            if (empty($lastschriften)) {
                $fehler[] = 'Für die gewählten Posten ergibt sich für kein Mitglied mit Mandat ein Betrag.';
            } else {
                $xml = erzeugeSepaLastschriftDatei($faelligkeitsdatum, $lastschriften);

                $platzhalter2 = implode(',', array_fill(0, count($aktualisierenIds), '?'));
                $pdo->prepare("UPDATE mitglieder SET sepa_erste_lastschrift_erfolgt = 1 WHERE id IN ($platzhalter2)")
                    ->execute($aktualisierenIds);

                header('Content-Type: application/xml; charset=UTF-8');
                header('Content-Disposition: attachment; filename="sepa-lastschrift-' . date('Y-m-d') . '.xml"');
                header('Content-Length: ' . (string) strlen($xml));
                echo $xml;
                exit;
            }
        }
    }
}

$postenListe = $pdo->query('SELECT * FROM beitragsposten WHERE aktiv = 1 ORDER BY bezeichnung')->fetchAll();
$mitgliederFuerVorschau = $pdo->query("SELECT rolle, ist_admin, (sepa_erteilt_am IS NOT NULL) AS hat_mandat FROM mitglieder WHERE aktiv = 1")->fetchAll();

$tiefe = '../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = 'index.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SEPA-Export &ndash; Kassenwart &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php require __DIR__ . '/../../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">
        <nav class="subnav">
            <a href="../antraege.php">Aufnahmeanträge</a>
            <a href="../mitglieder.php">Mitgliederverwaltung</a>
            <a href="../verteiler.php">E-Mail-Verteiler</a>
            <a href="index.php" class="active">Kassenwart</a>
            <a href="../vereinsdokumente.php">Vereinsdokumente</a>
        </nav>

        <nav class="subnav">
            <a href="bankverbindungen.php">Bankverbindungen</a>
            <a href="beitraege.php">Beiträge</a>
            <a href="export.php" class="active">SEPA-Export</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">SEPA-Export</h2>
            <p class="text-muted">Wähle die Posten für diesen Lauf, dann den Fälligkeitstermin. Die erzeugte Datei (pain.008.001.02) kannst du direkt im Online-Banking als SEPA-Sammellastschrift hochladen.</p>

            <?php if (!empty($fehler)): ?>
                <div class="alert alert-error">
                    <ul style="margin:0; padding-left:20px;">
                        <?php foreach ($fehler as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (empty($postenListe)): ?>
                <p>Noch keine aktiven Beiträge hinterlegt. Bitte zuerst unter "Beiträge" Beträge eintragen.</p>
            <?php else: ?>
                <form method="post" id="export-form">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                    <fieldset>
                        <legend>Posten für diesen Lauf</legend>
                        <?php foreach ($postenListe as $p): ?>
                            <label class="inline">
                                <input type="checkbox" name="posten[]" value="<?= (int) $p['id'] ?>" data-betrag="<?= e(number_format((float) $p['betrag'], 2, '.', '')) ?>" data-rolle="<?= $p['rolle'] !== null ? e($p['rolle']) : '' ?>" <?= in_array((string) $p['id'], (array) ($_POST['posten'] ?? []), true) ? 'checked' : '' ?>>
                                <span><?= e($p['bezeichnung']) ?> (<?= number_format((float) $p['betrag'], 2, ',', '.') ?> € &ndash; <?= $p['rolle'] !== null ? e(rollenLabel($p['rolle'])) : 'alle' ?>, <?= $p['ist_startpass'] ? 'jährlich' : 'monatlich' ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <label class="required" for="faelligkeitsdatum">Fälligkeitstermin</label>
                    <input type="date" id="faelligkeitsdatum" name="faelligkeitsdatum" value="<?= e($faelligkeitsdatum) ?>" required>
                    <div class="hint">Mindestens 5 Tage in der Zukunft (Vorlaufzeit für Erstlastschriften).</div>

                    <p class="text-muted" style="margin-top:16px;">
                        <span id="vorschau-anzahl">0</span> Mitglied(er) mit Mandat werden einbezogen, Gesamtbetrag <span id="vorschau-summe">0,00</span> €.
                        <span id="vorschau-fehlend-zeile" hidden>Ohne Mandat und daher übersprungen: <span id="vorschau-fehlend">0</span>.</span>
                    </p>

                    <div style="margin-top:16px;">
                        <button type="submit" class="btn">SEPA-Datei erzeugen</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
    <script src="../../../assets/js/menue.js" defer></script>
    <script id="mitglieder-daten" type="application/json"><?= json_encode(array_map(static fn (array $m) => ['rolle' => bankRolle($m), 'hatMandat' => (bool) $m['hat_mandat']], $mitgliederFuerVorschau), JSON_THROW_ON_ERROR) ?></script>
    <script>
        (function () {
            var form = document.getElementById('export-form');
            if (!form) {
                return;
            }
            var mitglieder = JSON.parse(document.getElementById('mitglieder-daten').textContent);
            var checkboxen = form.querySelectorAll('input[name="posten[]"]');

            function aktualisieren() {
                var ausgewaehlt = [];
                checkboxen.forEach(function (cb) {
                    if (cb.checked) {
                        ausgewaehlt.push({ betrag: parseFloat(cb.dataset.betrag), rolle: cb.dataset.rolle || null });
                    }
                });

                var anzahl = 0, summe = 0, fehlend = 0;
                mitglieder.forEach(function (m) {
                    var betrag = 0;
                    ausgewaehlt.forEach(function (p) {
                        if (p.rolle === null || p.rolle === m.rolle) {
                            betrag += p.betrag;
                        }
                    });
                    if (betrag <= 0) {
                        return;
                    }
                    if (!m.hatMandat) {
                        fehlend++;
                        return;
                    }
                    anzahl++;
                    summe += betrag;
                });

                document.getElementById('vorschau-anzahl').textContent = String(anzahl);
                document.getElementById('vorschau-summe').textContent = summe.toFixed(2).replace('.', ',');
                document.getElementById('vorschau-fehlend').textContent = String(fehlend);
                document.getElementById('vorschau-fehlend-zeile').hidden = fehlend === 0;
            }

            checkboxen.forEach(function (cb) {
                cb.addEventListener('change', aktualisieren);
            });
            aktualisieren();
        })();
    </script>
</body>
</html>
