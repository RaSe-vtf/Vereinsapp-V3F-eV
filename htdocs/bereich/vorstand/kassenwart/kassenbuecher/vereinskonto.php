<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../../includes/db.php';
require_once __DIR__ . '/../../../../../includes/functions.php';
require_once __DIR__ . '/../../../../../includes/auth.php';

$mitglied = requireVorstand('../../../../login.php', '../../../index.php');
$pdo = getPdo();
stelleKassenberichtKategorienSicher($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.');
    } elseif (($_POST['aktion'] ?? '') === 'upload') {
        try {
            $geparst = handleKontoauszugUpload($_FILES['auszug'] ?? []);
            $buchungen = $geparst['buchungen'];

            if (!empty($buchungen)) {
                $daten = array_column($buchungen, 'datum');
                sort($daten);
                $stichdatum = end($daten);
            } else {
                $stichdatum = date('Y-m-d');
            }
            $jahr = (int) date('Y', strtotime($stichdatum));
            $monat = (int) date('n', strtotime($stichdatum));

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO kontoauszuege (dateiname, format, jahr, monat, auszugsnummer, anfangssaldo, endsaldo)
                 VALUES (:dateiname, :format, :jahr, :monat, :auszugsnummer, :anfangssaldo, :endsaldo)'
            );
            $stmt->execute([
                'dateiname' => $geparst['dateiname'],
                'format' => $geparst['format'],
                'jahr' => $jahr,
                'monat' => $monat,
                'auszugsnummer' => $geparst['auszugsnummer'],
                'anfangssaldo' => $geparst['anfangssaldo'],
                'endsaldo' => $geparst['endsaldo'],
            ]);
            $auszugId = (int) $pdo->lastInsertId();

            $stmtBuchung = $pdo->prepare(
                'INSERT INTO kontobewegungen (auszug_id, buchungsdatum, betrag, verwendungszweck, beteiligter, kategorie_id, ist_bargeld_verdacht)
                 VALUES (:auszug_id, :buchungsdatum, :betrag, :verwendungszweck, :beteiligter, :kategorie_id, :verdacht)'
            );
            foreach ($buchungen as $b) {
                $typ = $b['betrag'] >= 0 ? 'einnahme' : 'ausgabe';
                $stmtBuchung->execute([
                    'auszug_id' => $auszugId,
                    'buchungsdatum' => $b['datum'],
                    'betrag' => number_format($b['betrag'], 2, '.', ''),
                    'verwendungszweck' => $b['verwendungszweck'],
                    'beteiligter' => $b['beteiligter'],
                    'kategorie_id' => holeKassenberichtRegelKategorie($pdo, $b['beteiligter'], $b['verwendungszweck'], $typ),
                    'verdacht' => istBargeldabhebungVerdacht($b['betrag'], $b['verwendungszweck']) ? 1 : 0,
                ]);
            }
            $pdo->commit();
            $nummerHinweis = $geparst['auszugsnummer'] !== null ? ' (Auszug Nr. ' . $geparst['auszugsnummer'] . ')' : ' (Auszugsnummer nicht erkannt)';
            setFlash('success', count($buchungen) . ' Buchung(en) aus dem Kontoauszug übernommen.' . $nummerHinweis);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23000') {
                setFlash('error', 'Für Jahr ' . $jahr . ' wurde Auszug Nr. ' . $geparst['auszugsnummer'] . ' bereits hochgeladen. Dieselbe Nummer kann nicht zweimal vergeben werden.');
            } else {
                setFlash('error', 'Der Kontoauszug konnte nicht gespeichert werden.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
        }
    } elseif (($_POST['aktion'] ?? '') === 'kategorie_setzen' && isset($_POST['id'])) {
        $kategorieId = (int) ($_POST['kategorie_id'] ?? 0);
        $pdo->prepare('UPDATE kontobewegungen SET kategorie_id = :kategorie_id WHERE id = :id')
            ->execute(['kategorie_id' => $kategorieId > 0 ? $kategorieId : null, 'id' => (int) $_POST['id']]);
        if ($kategorieId > 0) {
            $stmtBeteiligter = $pdo->prepare('SELECT beteiligter FROM kontobewegungen WHERE id = :id');
            $stmtBeteiligter->execute(['id' => (int) $_POST['id']]);
            lerneKassenberichtRegel($pdo, (string) $stmtBeteiligter->fetchColumn(), $kategorieId);
        }
    } elseif (($_POST['aktion'] ?? '') === 'auszug_loeschen' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('SELECT dateiname FROM kontoauszuege WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('DELETE FROM kontoauszuege WHERE id = :id')->execute(['id' => $id]);
            $pfad = __DIR__ . '/../../../../../private/uploads/kontoauszuege/' . basename($row['dateiname']);
            if (is_file($pfad)) {
                unlink($pfad);
            }
            setFlash('success', 'Kontoauszug und seine Buchungen wurden gelöscht.');
        }
    } elseif (($_POST['aktion'] ?? '') === 'beleg_hochladen') {
        $betragRoh = str_replace(',', '.', trim((string) ($_POST['betrag'] ?? '')));
        $datum = trim((string) ($_POST['datum'] ?? ''));
        $beschreibung = trim((string) ($_POST['beschreibung'] ?? ''));
        try {
            $belegDateiname = handleBelegUpload($_FILES['beleg'] ?? []);
            $pdo->prepare(
                'INSERT INTO belege (dateiname, original_dateiname, betrag, datum, beschreibung, hochgeladen_von)
                 VALUES (:dateiname, :original, :betrag, :datum, :beschreibung, :mitglied_id)'
            )->execute([
                'dateiname' => $belegDateiname,
                'original' => $_FILES['beleg']['name'] ?? null,
                'betrag' => $betragRoh !== '' && is_numeric($betragRoh) ? number_format((float) $betragRoh, 2, '.', '') : null,
                'datum' => $datum !== '' && DateTime::createFromFormat('Y-m-d', $datum) ? $datum : null,
                'beschreibung' => $beschreibung !== '' ? $beschreibung : null,
                'mitglied_id' => $mitglied['id'],
            ]);
            setFlash('success', 'Beleg wurde hochgeladen. Passende Ausgabe unten zuordnen, sobald der Kontoauszug vorliegt.');
        } catch (Throwable $e) {
            setFlash('error', $e->getMessage());
        }
    } elseif (($_POST['aktion'] ?? '') === 'beleg_zuordnen' && isset($_POST['beleg_id'], $_POST['kontobewegung_id'])) {
        $belegId = (int) $_POST['beleg_id'];
        $kbId = (int) $_POST['kontobewegung_id'];
        if ($belegId > 0 && $kbId > 0) {
            $pdo->prepare('UPDATE belege SET kontobewegung_id = :kb WHERE id = :id AND kontobewegung_id IS NULL')
                ->execute(['kb' => $kbId, 'id' => $belegId]);
            setFlash('success', 'Beleg wurde der Ausgabe zugeordnet.');
        }
    } elseif (($_POST['aktion'] ?? '') === 'beleg_trennen' && isset($_POST['beleg_id'])) {
        $pdo->prepare('UPDATE belege SET kontobewegung_id = NULL WHERE id = :id')->execute(['id' => (int) $_POST['beleg_id']]);
        setFlash('success', 'Zuordnung wurde aufgehoben.');
    } elseif (($_POST['aktion'] ?? '') === 'beleg_loeschen' && isset($_POST['beleg_id'])) {
        $stmt = $pdo->prepare('SELECT dateiname FROM belege WHERE id = :id');
        $stmt->execute(['id' => (int) $_POST['beleg_id']]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('DELETE FROM belege WHERE id = :id')->execute(['id' => (int) $_POST['beleg_id']]);
            $pfad = __DIR__ . '/../../../../../private/uploads/belege/' . basename($row['dateiname']);
            if (is_file($pfad)) {
                unlink($pfad);
            }
            setFlash('success', 'Beleg wurde gelöscht.');
        }
    } elseif (($_POST['aktion'] ?? '') === 'spendenbescheinigung_datum_setzen' && isset($_POST['kontobewegung_id'])) {
        $datum = trim((string) ($_POST['datum'] ?? ''));
        $gueltig = $datum !== '' ? DateTime::createFromFormat('Y-m-d', $datum) : null;
        $pdo->prepare('UPDATE kontobewegungen SET spendenbescheinigung_ausgestellt_am = :datum WHERE id = :id')
            ->execute([
                'datum' => $gueltig ? $datum : null,
                'id' => (int) $_POST['kontobewegung_id'],
            ]);
        setFlash('success', $gueltig ? 'Ausstellungsdatum gespeichert.' : 'Als noch nicht ausgestellt markiert.');
    }
    $jahrRedirect = $jahr ?? (isset($_GET['jahr']) ? (int) $_GET['jahr'] : (int) date('Y'));
    $monatRedirect = $monat ?? (isset($_GET['monat']) ? (int) $_GET['monat'] : (int) date('n'));
    header('Location: vereinskonto.php?jahr=' . $jahrRedirect . '&monat=' . $monatRedirect);
    exit;
}

wendeKassenberichtRegelnAufUnkategorisierteAn($pdo);

$belegVorschlaege = findeBelegVorschlaege($pdo);
$unzugeordneteBelege = $pdo->query('SELECT * FROM belege WHERE kontobewegung_id IS NULL ORDER BY hochgeladen_am DESC')->fetchAll();
$belegeNachKontobewegung = [];
foreach ($pdo->query('SELECT * FROM belege WHERE kontobewegung_id IS NOT NULL')->fetchAll() as $beleg) {
    $belegeNachKontobewegung[(int) $beleg['kontobewegung_id']] = $beleg;
}
$spendenKategorieId = (int) ($pdo->query("SELECT id FROM kassenbericht_kategorien WHERE typ = 'einnahme' AND name = 'Spenden' LIMIT 1")->fetchColumn() ?: 0);
$fehlendeSpendenDaten = fehlendeSpendenbescheinigungsDaten();

$heute = new DateTime();
$jahrAuswahl = isset($_GET['jahr']) ? (int) $_GET['jahr'] : (int) $heute->format('Y');
$monatAuswahl = isset($_GET['monat']) ? (int) $_GET['monat'] : (int) $heute->format('n');
if ($monatAuswahl < 1 || $monatAuswahl > 12) {
    $monatAuswahl = (int) $heute->format('n');
}

$letzterAuszug = $pdo->query('SELECT endsaldo FROM kontoauszuege ORDER BY jahr DESC, monat DESC, id DESC LIMIT 1')->fetch();
$kontostand = $letzterAuszug && $letzterAuszug['endsaldo'] !== null ? (float) $letzterAuszug['endsaldo'] : null;

$stmtJahr = $pdo->prepare(
    'SELECT COALESCE(SUM(betrag), 0) AS summe FROM kontobewegungen WHERE YEAR(buchungsdatum) = :jahr'
);
$stmtJahr->execute(['jahr' => $jahrAuswahl]);
$jahresSaldo = (float) $stmtJahr->fetchColumn();

$stmtMonat = $pdo->prepare(
    'SELECT * FROM kontobewegungen WHERE YEAR(buchungsdatum) = :jahr AND MONTH(buchungsdatum) = :monat ORDER BY buchungsdatum, id'
);
$stmtMonat->execute(['jahr' => $jahrAuswahl, 'monat' => $monatAuswahl]);
$buchungenMonat = $stmtMonat->fetchAll();

$einnahmenMonat = 0.0;
$ausgabenMonat = 0.0;
foreach ($buchungenMonat as $b) {
    if ((float) $b['betrag'] >= 0) {
        $einnahmenMonat += (float) $b['betrag'];
    } else {
        $ausgabenMonat += (float) $b['betrag'];
    }
}

$kategorienEinnahme = holeKassenberichtKategorien($pdo, 'einnahme');
$kategorienAusgabe = holeKassenberichtKategorien($pdo, 'ausgabe');

$stmtAuszuegeMonat = $pdo->prepare('SELECT * FROM kontoauszuege WHERE jahr = :jahr AND monat = :monat ORDER BY id');
$stmtAuszuegeMonat->execute(['jahr' => $jahrAuswahl, 'monat' => $monatAuswahl]);
$auszuegeMonat = $stmtAuszuegeMonat->fetchAll();

$monatsNamen = [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];

$luecken = holeKontoauszugLuecken($pdo);

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
    <title>Vereinskonto &ndash; Kassenbücher &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../../../assets/css/style.css?v=<?= cacheV('assets/css/style.css') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php require __DIR__ . '/../../../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">

        <?php if (!empty($luecken['luecken'])): ?>
            <div class="alert alert-warning">
                <strong>Lücke in der Auszugsnummerierung:</strong>
                <ul style="margin:6px 0 0; padding-left:20px;">
                    <?php foreach ($luecken['luecken'] as $jahrMitLuecke => $fehlendeNummern): ?>
                        <li>Für <?= (int) $jahrMitLuecke ?> fehlt Auszug Nr. <?= e(implode(', ', $fehlendeNummern)) ?> &ndash; bitte bei der Bank nachfordern.</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($luecken['ohneNummerAnzahl'] > 0): ?>
            <div class="alert alert-warning"><?= (int) $luecken['ohneNummerAnzahl'] ?> hochgeladene(r) Kontoauszug/Kontoauszüge ohne automatisch erkannte Auszugsnummer &ndash; für diese kann keine Lückenprüfung erfolgen.</div>
        <?php endif; ?>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
        <?php endif; ?>

        <div class="card">
            <div style="display:flex; flex-wrap:wrap; gap:32px; align-items:baseline;">
                <div>
                    <div class="text-muted" style="font-size:0.85rem;">Kontostand</div>
                    <div style="font-size:1.6rem; font-weight:700;"><?= $kontostand !== null ? number_format($kontostand, 2, ',', '.') . ' €' : '–' ?></div>
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
                <div style="overflow-x:auto;">
                <table class="tabelle-karten">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Verwendungszweck</th>
                            <th>Beteiligter</th>
                            <th>Betrag</th>
                            <th>Kategorie</th>
                            <th>Beleg</th>
                            <th>Spendenbescheinigung</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buchungenMonat as $b): ?>
                            <tr>
                                <td data-label="Datum"><?= e((new DateTime($b['buchungsdatum']))->format('d.m.Y')) ?></td>
                                <td data-label="Verwendungszweck"><?= e((string) $b['verwendungszweck']) ?></td>
                                <td data-label="Beteiligter"><?= e((string) $b['beteiligter']) ?></td>
                                <td data-label="Betrag" style="color:<?= (float) $b['betrag'] >= 0 ? 'var(--farbe-success)' : 'var(--farbe-error)' ?>;">
                                    <?= (float) $b['betrag'] >= 0 ? '+' : '' ?><?= number_format((float) $b['betrag'], 2, ',', '.') ?> €
                                    <?php if ((bool) $b['ist_bargeld_verdacht']): ?>
                                        <span class="badge badge-neu" title="Möglicherweise eine Bargeldabhebung &ndash; siehe Barkasse">Bargeld?</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Kategorie">
                                    <?php if ((bool) $b['in_barkasse_uebernommen']): ?>
                                        <span class="text-muted">&rarr; Barkasse</span>
                                    <?php else: ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="aktion" value="kategorie_setzen">
                                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                            <select name="kategorie_id" onchange="this.form.submit()">
                                                <option value="0">&ndash; ohne Kategorie &ndash;</option>
                                                <?php foreach (((float) $b['betrag'] >= 0) ? $kategorienEinnahme : $kategorienAusgabe as $kat): ?>
                                                    <option value="<?= (int) $kat['id'] ?>" <?= (int) $b['kategorie_id'] === (int) $kat['id'] ? 'selected' : '' ?>><?= e($kat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Beleg">
                                    <?php if ((float) $b['betrag'] < 0): ?>
                                        <?php if (isset($belegeNachKontobewegung[(int) $b['id']])): $zugeordneterBeleg = $belegeNachKontobewegung[(int) $b['id']]; ?>
                                            <span class="badge badge-angenommen">&#10003; Beleg</span>
                                            <div style="margin-top:4px;">
                                                <a href="konto_beleg_datei.php?id=<?= (int) $zugeordneterBeleg['id'] ?>" target="_blank" rel="noopener">ansehen</a>
                                                <form method="post" class="inline-form" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                    <input type="hidden" name="aktion" value="beleg_trennen">
                                                    <input type="hidden" name="beleg_id" value="<?= (int) $zugeordneterBeleg['id'] ?>">
                                                    <button type="submit" class="btn-link" onclick="return confirm('Zuordnung wirklich aufheben?');">&middot; lösen</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-abgelehnt">Beleg fehlt</span>
                                            <?php if (!empty($unzugeordneteBelege)): ?>
                                                <form method="post" class="inline-form" style="margin-top:4px;">
                                                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                    <input type="hidden" name="aktion" value="beleg_zuordnen">
                                                    <input type="hidden" name="kontobewegung_id" value="<?= (int) $b['id'] ?>">
                                                    <select name="beleg_id" onchange="this.value!=='0' && this.form.submit()">
                                                        <option value="0">hochgeladenen Beleg zuordnen &hellip;</option>
                                                        <?php foreach ($unzugeordneteBelege as $ub): ?>
                                                            <option value="<?= (int) $ub['id'] ?>"><?= e($ub['original_dateiname'] ?? $ub['dateiname']) ?><?= $ub['betrag'] !== null ? ' (' . number_format((float) $ub['betrag'], 2, ',', '.') . ' €)' : '' ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Spendenbescheinigung">
                                    <?php if ((float) $b['betrag'] >= 0 && $spendenKategorieId > 0 && (int) $b['kategorie_id'] === $spendenKategorieId): ?>
                                        <?php if ($b['spendenbescheinigung_ausgestellt_am'] !== null): ?>
                                            <span class="badge badge-angenommen">ausgestellt <?= e((new DateTime($b['spendenbescheinigung_ausgestellt_am']))->format('d.m.Y')) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-neu">offen</span>
                                        <?php endif; ?>
                                        <div style="margin-top:4px;">
                                            <?php if (empty($fehlendeSpendenDaten)): ?>
                                                <a href="spendenbescheinigung.php?id=<?= (int) $b['id'] ?>" target="_blank" rel="noopener">Bescheinigung erstellen</a>
                                            <?php else: ?>
                                                <span class="text-muted" title="Es fehlen noch: <?= e(implode('; ', $fehlendeSpendenDaten)) ?>">Bescheinigung erstellen (inaktiv)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Belege</h2>
            <p class="text-muted">Rechnungen/Kassenzettel lassen sich hier jederzeit hochladen, auch bevor der passende Kontoauszug vorliegt &ndash; die Zuordnung zur Ausgabe erfolgt dann separat (automatischer Vorschlag bei übereinstimmendem Betrag, sonst oben in der Buchungsliste manuell).</p>

            <?php if (!empty($belegVorschlaege)): ?>
                <h3>Zuordnungsvorschläge</h3>
                <?php foreach ($belegVorschlaege as $vorschlag): $vb = $vorschlag['beleg']; $vk = $vorschlag['kontobewegung']; ?>
                    <div class="alert alert-warning" style="display:flex; flex-wrap:wrap; align-items:center; gap:10px;">
                        <span>
                            Beleg „<?= e($vb['original_dateiname'] ?? $vb['dateiname']) ?>"
                            (<?= number_format((float) $vb['betrag'], 2, ',', '.') ?> €) könnte zur Ausgabe
                            „<?= e((string) $vk['verwendungszweck']) ?>" vom
                            <?= e((new DateTime($vk['buchungsdatum']))->format('d.m.Y')) ?>
                            (<?= number_format(abs((float) $vk['betrag']), 2, ',', '.') ?> €) gehören.
                        </span>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                            <input type="hidden" name="aktion" value="beleg_zuordnen">
                            <input type="hidden" name="beleg_id" value="<?= (int) $vb['id'] ?>">
                            <input type="hidden" name="kontobewegung_id" value="<?= (int) $vk['id'] ?>">
                            <button type="submit" class="btn btn-secondary">Ja, zuordnen</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h3>Hochgeladene, noch nicht zugeordnete Belege</h3>
            <?php if (empty($unzugeordneteBelege)): ?>
                <p class="text-muted">Keine.</p>
            <?php else: ?>
                <div style="overflow-x:auto; margin-bottom:20px;">
                <table class="tabelle-karten">
                    <thead>
                        <tr>
                            <th>Hochgeladen am</th>
                            <th>Datei</th>
                            <th>Betrag</th>
                            <th>Datum</th>
                            <th>Beschreibung</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unzugeordneteBelege as $ub): ?>
                            <tr>
                                <td data-label="Hochgeladen am"><?= e((new DateTime($ub['hochgeladen_am']))->format('d.m.Y H:i')) ?></td>
                                <td data-label="Datei"><a href="konto_beleg_datei.php?id=<?= (int) $ub['id'] ?>" target="_blank" rel="noopener"><?= e($ub['original_dateiname'] ?? $ub['dateiname']) ?></a></td>
                                <td data-label="Betrag"><?= $ub['betrag'] !== null ? number_format((float) $ub['betrag'], 2, ',', '.') . ' €' : '<span class="text-muted">&ndash;</span>' ?></td>
                                <td data-label="Datum"><?= $ub['datum'] !== null ? e((new DateTime($ub['datum']))->format('d.m.Y')) : '<span class="text-muted">&ndash;</span>' ?></td>
                                <td data-label="Beschreibung"><?= e((string) $ub['beschreibung']) ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="aktion" value="beleg_loeschen">
                                        <input type="hidden" name="beleg_id" value="<?= (int) $ub['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Diesen Beleg wirklich löschen?');">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

            <h3>Beleg hochladen</h3>
            <p class="text-muted">Betrag und Datum sind optional, helfen der App aber bei der automatischen Zuordnung zur passenden Ausgabe.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="beleg_hochladen">
                <div class="form-row">
                    <div>
                        <label for="belegBetrag">Betrag <span class="text-muted">(optional, €)</span></label>
                        <input type="text" id="belegBetrag" name="betrag" inputmode="decimal" placeholder="z.B. 89,90">
                    </div>
                    <div>
                        <label for="belegDatum">Datum <span class="text-muted">(optional)</span></label>
                        <input type="date" id="belegDatum" name="datum">
                    </div>
                </div>
                <label for="belegBeschreibung">Beschreibung <span class="text-muted">(optional)</span></label>
                <input type="text" id="belegBeschreibung" name="beschreibung" placeholder="z.B. Reinigungsfirma Mai">
                <label for="belegDatei">Datei</label>
                <input type="file" id="belegDatei" name="beleg" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                <div style="margin-top:12px;">
                    <button type="submit" class="btn">Hochladen</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Kontoauszüge &ndash; <?= e($monatsNamen[$monatAuswahl]) ?> <?= (int) $jahrAuswahl ?></h2>

            <?php if (empty($auszuegeMonat)): ?>
                <p>Für diesen Monat wurde noch kein Kontoauszug hochgeladen.</p>
            <?php else: ?>
                <div style="overflow-x:auto; margin-bottom:20px;">
                <table class="tabelle-karten">
                    <thead>
                        <tr>
                            <th>Hochgeladen am</th>
                            <th>Format</th>
                            <th>Auszug Nr.</th>
                            <th>Anfangssaldo</th>
                            <th>Endsaldo</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auszuegeMonat as $a): ?>
                            <tr>
                                <td data-label="Hochgeladen am"><?= e((new DateTime($a['hochgeladen_am']))->format('d.m.Y H:i')) ?></td>
                                <td data-label="Format"><?= $a['format'] === 'camt053' ? 'CAMT.053' : 'MT940' ?></td>
                                <td data-label="Auszug Nr."><?= $a['auszugsnummer'] !== null ? (int) $a['auszugsnummer'] : '<span class="text-muted">nicht erkannt</span>' ?></td>
                                <td data-label="Anfangssaldo"><?= $a['anfangssaldo'] !== null ? number_format((float) $a['anfangssaldo'], 2, ',', '.') . ' €' : '–' ?></td>
                                <td data-label="Endsaldo"><?= $a['endsaldo'] !== null ? number_format((float) $a['endsaldo'], 2, ',', '.') . ' €' : '–' ?></td>
                                <td>
                                    <a class="btn btn-secondary" href="kontoauszug_datei.php?id=<?= (int) $a['id'] ?>">Herunterladen</a>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="aktion" value="auszug_loeschen">
                                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Diesen Kontoauszug und alle daraus übernommenen Buchungen wirklich löschen?');">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

            <h3>Kontoauszug hochladen</h3>
            <p class="text-muted">Unterstützt werden die Standardformate CAMT.053 (XML) und MT940 &ndash; Jahr/Monat sowie alle Buchungen werden automatisch aus der Datei übernommen, es gibt keine manuelle Zahleneingabe.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="upload">
                <input type="file" name="auszug" accept=".xml,.sta,.txt" required>
                <div style="margin-top:12px;">
                    <button type="submit" class="btn">Hochladen</button>
                </div>
            </form>
        </div>
    </main>
    <script src="../../../../assets/js/menue.js" defer></script>
</body>
</html>
