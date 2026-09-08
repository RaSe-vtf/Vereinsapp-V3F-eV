<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../../includes/db.php';
require_once __DIR__ . '/../../../../../includes/functions.php';
require_once __DIR__ . '/../../../../../includes/auth.php';

$mitglied = requireVorstand('../../../../login.php', '../../../index.php');
$pdo = getPdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.');
    } elseif (($_POST['aktion'] ?? '') === 'konto_uebernehmen' && isset($_POST['kontobewegung_id'])) {
        $kbId = (int) $_POST['kontobewegung_id'];
        $stmt = $pdo->prepare('SELECT * FROM kontobewegungen WHERE id = :id AND ist_bargeld_verdacht = 1 AND in_barkasse_uebernommen = 0');
        $stmt->execute(['id' => $kbId]);
        $kb = $stmt->fetch();
        if ($kb) {
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO barkasse_buchungen (typ, datum, betrag, beschreibung, kontobewegung_id)
                 VALUES (\'einnahme_konto\', :datum, :betrag, :beschreibung, :kontobewegung_id)'
            )->execute([
                'datum' => $kb['buchungsdatum'],
                'betrag' => number_format(abs((float) $kb['betrag']), 2, '.', ''),
                'beschreibung' => $kb['verwendungszweck'],
                'kontobewegung_id' => $kb['id'],
            ]);
            $pdo->prepare('UPDATE kontobewegungen SET in_barkasse_uebernommen = 1 WHERE id = :id')->execute(['id' => $kbId]);
            $pdo->commit();
            setFlash('success', 'Bargeldabhebung wurde als Kassenzugang übernommen.');
        }
    } elseif (($_POST['aktion'] ?? '') === 'einnahme_manuell') {
        $datum = trim((string) ($_POST['datum'] ?? ''));
        $betragRoh = str_replace(',', '.', trim((string) ($_POST['betrag'] ?? '')));
        $beschreibung = trim((string) ($_POST['beschreibung'] ?? ''));

        if (!DateTime::createFromFormat('Y-m-d', $datum)) {
            setFlash('error', 'Bitte ein gültiges Datum angeben.');
        } elseif (!is_numeric($betragRoh) || (float) $betragRoh <= 0) {
            setFlash('error', 'Bitte einen gültigen Betrag größer als 0 angeben.');
        } elseif ($beschreibung === '') {
            setFlash('error', 'Bitte eine Beschreibung angeben.');
        } else {
            $pdo->prepare('INSERT INTO barkasse_buchungen (typ, datum, betrag, beschreibung) VALUES (\'einnahme_manuell\', :datum, :betrag, :beschreibung)')
                ->execute(['datum' => $datum, 'betrag' => number_format((float) $betragRoh, 2, '.', ''), 'beschreibung' => $beschreibung]);
            setFlash('success', 'Einnahme wurde erfasst.');
        }
    } elseif (($_POST['aktion'] ?? '') === 'ausgabe') {
        $datum = trim((string) ($_POST['datum'] ?? ''));
        $betragRoh = str_replace(',', '.', trim((string) ($_POST['betrag'] ?? '')));
        $empfaenger = trim((string) ($_POST['empfaenger'] ?? ''));
        $beschreibung = trim((string) ($_POST['beschreibung'] ?? ''));

        if (!DateTime::createFromFormat('Y-m-d', $datum)) {
            setFlash('error', 'Bitte ein gültiges Datum angeben.');
        } elseif (!is_numeric($betragRoh) || (float) $betragRoh <= 0) {
            setFlash('error', 'Bitte einen gültigen Betrag größer als 0 angeben.');
        } elseif ($empfaenger === '') {
            setFlash('error', 'Bitte den Empfänger angeben.');
        } else {
            try {
                $belegDateiname = handleBelegUpload($_FILES['beleg'] ?? []);
                $pdo->prepare(
                    'INSERT INTO barkasse_buchungen (typ, datum, betrag, beschreibung, empfaenger, beleg_dateiname)
                     VALUES (\'ausgabe\', :datum, :betrag, :beschreibung, :empfaenger, :beleg)'
                )->execute([
                    'datum' => $datum,
                    'betrag' => number_format((float) $betragRoh, 2, '.', ''),
                    'beschreibung' => $beschreibung,
                    'empfaenger' => $empfaenger,
                    'beleg' => $belegDateiname,
                ]);
                setFlash('success', 'Ausgabe wurde erfasst.');
            } catch (Throwable $e) {
                setFlash('error', $e->getMessage());
            }
        }
    } elseif (($_POST['aktion'] ?? '') === 'loeschen' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('SELECT * FROM barkasse_buchungen WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $buchung = $stmt->fetch();
        if ($buchung) {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM barkasse_buchungen WHERE id = :id')->execute(['id' => $id]);
            if ($buchung['kontobewegung_id'] !== null) {
                $pdo->prepare('UPDATE kontobewegungen SET in_barkasse_uebernommen = 0 WHERE id = :id')->execute(['id' => $buchung['kontobewegung_id']]);
            }
            $pdo->commit();
            if ($buchung['beleg_dateiname']) {
                $pfad = __DIR__ . '/../../../../../private/uploads/belege/' . basename($buchung['beleg_dateiname']);
                if (is_file($pfad)) {
                    unlink($pfad);
                }
            }
            setFlash('success', 'Buchung wurde gelöscht.');
        }
    }
    $zielJahr = isset($_GET['jahr']) ? (int) $_GET['jahr'] : (int) date('Y');
    $zielMonat = isset($_GET['monat']) ? (int) $_GET['monat'] : (int) date('n');
    header('Location: barkasse.php?jahr=' . $zielJahr . '&monat=' . $zielMonat);
    exit;
}

$heute = new DateTime();
$jahrAuswahl = isset($_GET['jahr']) ? (int) $_GET['jahr'] : (int) $heute->format('Y');
$monatAuswahl = isset($_GET['monat']) ? (int) $_GET['monat'] : (int) $heute->format('n');
if ($monatAuswahl < 1 || $monatAuswahl > 12) {
    $monatAuswahl = (int) $heute->format('n');
}

$vorzeichen = ['einnahme_konto' => 1, 'einnahme_manuell' => 1, 'ausgabe' => -1];

$alleBuchungen = $pdo->query('SELECT * FROM barkasse_buchungen ORDER BY datum, id')->fetchAll();
$kassenstand = 0.0;
$jahresSaldo = 0.0;
foreach ($alleBuchungen as $b) {
    $vorzeichenWert = $vorzeichen[$b['typ']] * (float) $b['betrag'];
    $kassenstand += $vorzeichenWert;
    if ((int) date('Y', strtotime($b['datum'])) === $jahrAuswahl) {
        $jahresSaldo += $vorzeichenWert;
    }
}

$kandidaten = $pdo->query(
    "SELECT * FROM kontobewegungen WHERE ist_bargeld_verdacht = 1 AND in_barkasse_uebernommen = 0 ORDER BY buchungsdatum DESC"
)->fetchAll();

$stmtMonat = $pdo->prepare(
    'SELECT * FROM barkasse_buchungen WHERE YEAR(datum) = :jahr AND MONTH(datum) = :monat ORDER BY datum, id'
);
$stmtMonat->execute(['jahr' => $jahrAuswahl, 'monat' => $monatAuswahl]);
$buchungenMonat = $stmtMonat->fetchAll();

$einnahmenMonat = 0.0;
$ausgabenMonat = 0.0;
foreach ($buchungenMonat as $b) {
    if ($b['typ'] === 'ausgabe') {
        $ausgabenMonat += (float) $b['betrag'];
    } else {
        $einnahmenMonat += (float) $b['betrag'];
    }
}

$typLabels = ['einnahme_konto' => 'Einnahme (vom Konto)', 'einnahme_manuell' => 'Einnahme', 'ausgabe' => 'Ausgabe'];
$monatsNamen = [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];

$flash = takeFlash();
$tiefe = '../../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = 'index.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barkasse &ndash; Kassenbücher &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php require __DIR__ . '/../../../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">
        <nav class="subnav">
            <a href="../../antraege.php">Aufnahmeanträge</a>
            <a href="../../mitglieder.php">Mitgliederverwaltung</a>
            <a href="../../verteiler.php">E-Mail-Verteiler</a>
            <a href="../index.php" class="active">Kassenwart</a>
            <a href="../../vereinsdokumente.php">Vereinsdokumente</a>
            <a href="../../protokolle.php">Protokolle</a>
        </nav>

        <nav class="subnav">
            <a href="index.php">Kassenbücher</a>
            <a href="vereinskonto.php">Vereinskonto</a>
            <a href="barkasse.php" class="active">Barkasse</a>
        </nav>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
        <?php endif; ?>

        <div class="card">
            <div style="display:flex; flex-wrap:wrap; gap:32px; align-items:baseline;">
                <div>
                    <div class="text-muted" style="font-size:0.85rem;">Kassenstand</div>
                    <div style="font-size:1.6rem; font-weight:700;"><?= number_format($kassenstand, 2, ',', '.') ?> €</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:0.85rem;">+/- im Jahr <?= (int) $jahrAuswahl ?></div>
                    <div style="font-size:1.3rem; font-weight:700; color:<?= $jahresSaldo >= 0 ? 'var(--farbe-success)' : 'var(--farbe-error)' ?>;">
                        <?= $jahresSaldo >= 0 ? '+' : '' ?><?= number_format($jahresSaldo, 2, ',', '.') ?> €
                    </div>
                </div>
                <div style="margin-left:auto; display:flex; gap:8px;">
                    <a class="btn btn-secondary" href="?jahr=<?= $jahrAuswahl - 1 ?>&monat=<?= $monatAuswahl ?>">&laquo; <?= $jahrAuswahl - 1 ?></a>
                    <a class="btn btn-secondary" href="?jahr=<?= $jahrAuswahl + 1 ?>&monat=<?= $monatAuswahl ?>"><?= $jahrAuswahl + 1 ?> &raquo;</a>
                </div>
            </div>
        </div>

        <?php if (!empty($kandidaten)): ?>
        <div class="card">
            <h2 style="margin-top:0;">Vorschläge aus dem Vereinskonto</h2>
            <p class="text-muted">Diese Kontobewegungen sehen nach einer Bargeldabhebung aus. Bitte bestätigen, wenn das Geld tatsächlich in die Barkasse geflossen ist.</p>
            <div style="overflow-x:auto;">
            <table class="tabelle-einzeilig">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Verwendungszweck</th>
                        <th>Betrag</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kandidaten as $k): ?>
                        <tr>
                            <td><?= e((new DateTime($k['buchungsdatum']))->format('d.m.Y')) ?></td>
                            <td><?= e((string) $k['verwendungszweck']) ?></td>
                            <td><?= number_format(abs((float) $k['betrag']), 2, ',', '.') ?> €</td>
                            <td>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                    <input type="hidden" name="aktion" value="konto_uebernehmen">
                                    <input type="hidden" name="kontobewegung_id" value="<?= (int) $k['id'] ?>">
                                    <button type="submit" class="btn btn-secondary">Als Kassenzugang übernehmen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <nav class="subnav" style="margin-bottom:0;">
                <?php foreach ($monatsNamen as $nr => $name): ?>
                    <a href="?jahr=<?= $jahrAuswahl ?>&monat=<?= $nr ?>" class="<?= $nr === $monatAuswahl ? 'active' : '' ?>"><?= e($name) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Einnahmen und Ausgaben &ndash; <?= e($monatsNamen[$monatAuswahl]) ?> <?= (int) $jahrAuswahl ?></h2>
            <p class="text-muted">
                Einnahmen: <strong style="color:var(--farbe-success);"><?= number_format($einnahmenMonat, 2, ',', '.') ?> €</strong>
                &nbsp;&middot;&nbsp;
                Ausgaben: <strong style="color:var(--farbe-error);"><?= number_format($ausgabenMonat, 2, ',', '.') ?> €</strong>
            </p>

            <?php if (empty($buchungenMonat)): ?>
                <p>Keine Buchungen für diesen Monat vorhanden.</p>
            <?php else: ?>
                <div style="overflow-x:auto; margin-bottom:20px;">
                <table class="tabelle-einzeilig">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Typ</th>
                            <th>Beschreibung</th>
                            <th>Empfänger</th>
                            <th>Betrag</th>
                            <th>Beleg</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buchungenMonat as $b): ?>
                            <tr>
                                <td><?= e((new DateTime($b['datum']))->format('d.m.Y')) ?></td>
                                <td><?= e($typLabels[$b['typ']]) ?></td>
                                <td><?= e((string) $b['beschreibung']) ?></td>
                                <td><?= e((string) $b['empfaenger']) ?></td>
                                <td style="color:<?= $b['typ'] === 'ausgabe' ? 'var(--farbe-error)' : 'var(--farbe-success)' ?>;">
                                    <?= $b['typ'] === 'ausgabe' ? '-' : '+' ?><?= number_format((float) $b['betrag'], 2, ',', '.') ?> €
                                </td>
                                <td>
                                    <?php if ($b['beleg_dateiname']): ?>
                                        <a href="beleg_datei.php?id=<?= (int) $b['id'] ?>">ansehen</a>
                                    <?php else: ?>
                                        &ndash;
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="aktion" value="loeschen">
                                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Diese Buchung wirklich löschen?');">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Einnahme erfassen</h3>
            <p class="text-muted">Nur für Bareinnahmen, die nie über das Vereinskonto liefen (z.B. eine Bar-Spende).</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="einnahme_manuell">

                <label class="required" for="einnahme_datum">Datum</label>
                <input type="date" id="einnahme_datum" name="datum" value="<?= e($heute->format('Y-m-d')) ?>" required>

                <label class="required" for="einnahme_betrag">Betrag (€)</label>
                <input type="text" id="einnahme_betrag" name="betrag" inputmode="decimal" placeholder="z.B. 20,00" required>

                <label class="required" for="einnahme_beschreibung">Beschreibung</label>
                <input type="text" id="einnahme_beschreibung" name="beschreibung" placeholder="z.B. Spende beim Vereinsfest" required>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn">Einnahme speichern</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Ausgabe erfassen</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="ausgabe">

                <label class="required" for="ausgabe_datum">Datum</label>
                <input type="date" id="ausgabe_datum" name="datum" value="<?= e($heute->format('Y-m-d')) ?>" required>

                <label class="required" for="ausgabe_empfaenger">Empfänger</label>
                <input type="text" id="ausgabe_empfaenger" name="empfaenger" placeholder="An wen wurde gezahlt?" required>

                <label class="required" for="ausgabe_betrag">Betrag (€)</label>
                <input type="text" id="ausgabe_betrag" name="betrag" inputmode="decimal" placeholder="z.B. 15,00" required>

                <label for="ausgabe_beschreibung">Beschreibung</label>
                <input type="text" id="ausgabe_beschreibung" name="beschreibung" placeholder="Wofür?">

                <label class="required" for="ausgabe_beleg">Beleg (Rechnung als Foto oder PDF)</label>
                <input type="file" id="ausgabe_beleg" name="beleg" accept="image/jpeg,image/png,image/webp,application/pdf" required>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn">Ausgabe speichern</button>
                </div>
            </form>
        </div>
    </main>
    <script src="../../../../assets/js/menue.js" defer></script>
</body>
</html>
