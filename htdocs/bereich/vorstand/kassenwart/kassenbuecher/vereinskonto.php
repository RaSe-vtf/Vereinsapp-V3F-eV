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
    }
    $jahrRedirect = $jahr ?? (isset($_GET['jahr']) ? (int) $_GET['jahr'] : (int) date('Y'));
    $monatRedirect = $monat ?? (isset($_GET['monat']) ? (int) $_GET['monat'] : (int) date('n'));
    header('Location: vereinskonto.php?jahr=' . $jahrRedirect . '&monat=' . $monatRedirect);
    exit;
}

wendeKassenberichtRegelnAufUnkategorisierteAn($pdo);

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
    <link rel="stylesheet" href="../../../../assets/css/style.css?v=2">
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
            <a href="index.php">Kassenbücher</a>
            <a href="vereinskonto.php" class="active">Vereinskonto</a>
            <a href="barkasse.php">Barkasse</a>
        </nav>

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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
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
