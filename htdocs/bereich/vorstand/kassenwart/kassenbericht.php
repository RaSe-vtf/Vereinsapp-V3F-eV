<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');
$pdo = getPdo();

stelleKassenberichtKategorienSicher($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.');
    } elseif (($_POST['aktion'] ?? '') === 'kategorie_hinzufuegen') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $typ = (string) ($_POST['typ'] ?? '');
        if ($name === '') {
            setFlash('error', 'Bitte einen Namen für die Kategorie angeben.');
        } elseif (!in_array($typ, ['einnahme', 'ausgabe'], true)) {
            setFlash('error', 'Ungültiger Kategorie-Typ.');
        } else {
            $pdo->prepare('INSERT INTO kassenbericht_kategorien (name, typ) VALUES (:name, :typ)')
                ->execute(['name' => $name, 'typ' => $typ]);
            setFlash('success', 'Kategorie wurde angelegt.');
        }
    } elseif (($_POST['aktion'] ?? '') === 'kategorie_deaktivieren' && isset($_POST['id'])) {
        $pdo->prepare('UPDATE kassenbericht_kategorien SET aktiv = 0 WHERE id = :id')->execute(['id' => (int) $_POST['id']]);
        setFlash('success', 'Kategorie wurde ausgeblendet.');
    } elseif (($_POST['aktion'] ?? '') === 'regel_hinzufuegen') {
        $stichwort = trim((string) ($_POST['stichwort'] ?? ''));
        $kategorieId = (int) ($_POST['kategorie_id'] ?? 0);
        if ($stichwort === '') {
            setFlash('error', 'Bitte ein Stichwort angeben.');
        } elseif ($kategorieId <= 0) {
            setFlash('error', 'Bitte eine Kategorie auswählen.');
        } else {
            lerneKassenberichtRegel($pdo, $stichwort, $kategorieId);
            wendeKassenberichtRegelnAufUnkategorisierteAn($pdo);
            setFlash('success', 'Regel wurde gespeichert und auf bestehende Buchungen angewendet.');
        }
    } elseif (($_POST['aktion'] ?? '') === 'regel_loeschen' && isset($_POST['id'])) {
        $pdo->prepare('DELETE FROM kassenbericht_regeln WHERE id = :id')->execute(['id' => (int) $_POST['id']]);
        setFlash('success', 'Regel wurde gelöscht.');
    } elseif (($_POST['aktion'] ?? '') === 'sonderposten_hinzufuegen') {
        $prognoseJahr = (int) ($_POST['prognose_jahr'] ?? 0);
        $bezeichnung = trim((string) ($_POST['bezeichnung'] ?? ''));
        $betragRoh = str_replace(',', '.', trim((string) ($_POST['betrag'] ?? '')));
        $typ = (string) ($_POST['typ'] ?? '');
        if ($bezeichnung === '') {
            setFlash('error', 'Bitte eine Bezeichnung für den Sonderposten angeben.');
        } elseif (!is_numeric($betragRoh) || (float) $betragRoh <= 0) {
            setFlash('error', 'Bitte einen gültigen Betrag größer als 0 angeben.');
        } elseif (!in_array($typ, ['einnahme', 'ausgabe'], true)) {
            setFlash('error', 'Ungültiger Typ.');
        } else {
            $pdo->prepare('INSERT INTO budget_sonderposten (jahr, bezeichnung, betrag, typ) VALUES (:jahr, :bezeichnung, :betrag, :typ)')
                ->execute([
                    'jahr' => $prognoseJahr,
                    'bezeichnung' => $bezeichnung,
                    'betrag' => number_format((float) $betragRoh, 2, '.', ''),
                    'typ' => $typ,
                ]);
            setFlash('success', 'Sonderposten wurde erfasst.');
        }
        header('Location: kassenbericht.php?jahr=' . (int) ($_GET['jahr'] ?? date('Y')) . '&prognose=' . $prognoseJahr . '#prognose');
        exit;
    } elseif (($_POST['aktion'] ?? '') === 'sonderposten_loeschen' && isset($_POST['id'])) {
        $prognoseJahr = (int) ($_POST['prognose_jahr'] ?? 0);
        $pdo->prepare('DELETE FROM budget_sonderposten WHERE id = :id')->execute(['id' => (int) $_POST['id']]);
        setFlash('success', 'Sonderposten wurde gelöscht.');
        header('Location: kassenbericht.php?jahr=' . (int) ($_GET['jahr'] ?? date('Y')) . '&prognose=' . $prognoseJahr . '#prognose');
        exit;
    }
    header('Location: kassenbericht.php?jahr=' . (int) ($_GET['jahr'] ?? date('Y')));
    exit;
}

wendeKassenberichtRegelnAufUnkategorisierteAn($pdo);

$heute = new DateTime();
$verfuegbareJahre = holeKassenberichtJahre($pdo);
$jahrAuswahl = isset($_GET['jahr']) ? (int) $_GET['jahr'] : (int) $heute->format('Y');

$daten = berechneKassenberichtJahresdaten($pdo, $jahrAuswahl);
$vorjahr = $jahrAuswahl - 1;
$datenVorjahr = in_array($vorjahr, $verfuegbareJahre, true) ? berechneKassenberichtJahresdaten($pdo, $vorjahr) : null;

// Diagramm 1: Einnahmen vs. Ausgaben gesamt - feste Statusfarben (gruen/rot),
// wie auch sonst in der App fuer +/- verwendet.
$segmenteGesamt = [
    ['name' => 'Einnahmen', 'summe' => $daten['gesamtEinnahmen'], 'farbe' => '#1f8a4c'],
    ['name' => 'Ausgaben', 'summe' => $daten['gesamtAusgaben'], 'farbe' => '#c0392b'],
];

$segmenteEinnahmen = [];
foreach ($daten['einnahmenNachKategorie'] as $i => $kat) {
    $segmenteEinnahmen[] = ['name' => $kat['name'], 'summe' => $kat['summe'], 'farbe' => kassenberichtFarbeFuerName($kat['name'], $i)];
}
$segmenteAusgaben = [];
foreach ($daten['ausgabenNachKategorie'] as $i => $kat) {
    $segmenteAusgaben[] = ['name' => $kat['name'], 'summe' => $kat['summe'], 'farbe' => kassenberichtFarbeFuerName($kat['name'], $i)];
}

$alleKategorien = $pdo->query('SELECT * FROM kassenbericht_kategorien ORDER BY typ, aktiv DESC, name')->fetchAll();

$alleRegeln = $pdo->query(
    'SELECT r.id, r.stichwort, k.name AS kategorie_name, k.typ
     FROM kassenbericht_regeln r
     JOIN kassenbericht_kategorien k ON k.id = r.kategorie_id
     ORDER BY r.stichwort'
)->fetchAll();

// Prognose fuers neue Haushaltsjahr (= Kalenderjahr): Basis ist das
// abgeschlossene Vorjahr 1:1 je Kategorie, darauf werden manuell erfasste
// Sonderposten addiert.
$prognoseJahr = isset($_GET['prognose']) ? (int) $_GET['prognose'] : (empty($verfuegbareJahre) ? (int) $heute->format('Y') + 1 : max($verfuegbareJahre) + 1);
$prognoseBasisJahr = $prognoseJahr - 1;
$prognoseBasis = in_array($prognoseBasisJahr, $verfuegbareJahre, true) ? berechneKassenberichtJahresdaten($pdo, $prognoseBasisJahr) : null;

$stmtSonderposten = $pdo->prepare('SELECT * FROM budget_sonderposten WHERE jahr = :jahr ORDER BY typ, bezeichnung');
$stmtSonderposten->execute(['jahr' => $prognoseJahr]);
$sonderposten = $stmtSonderposten->fetchAll();

$sonderpostenEinnahmen = array_sum(array_map(static fn (array $s): float => $s['typ'] === 'einnahme' ? (float) $s['betrag'] : 0.0, $sonderposten));
$sonderpostenAusgaben = array_sum(array_map(static fn (array $s): float => $s['typ'] === 'ausgabe' ? (float) $s['betrag'] : 0.0, $sonderposten));

$prognoseEinnahmen = ($prognoseBasis['gesamtEinnahmen'] ?? 0.0) + $sonderpostenEinnahmen;
$prognoseAusgaben = ($prognoseBasis['gesamtAusgaben'] ?? 0.0) + $sonderpostenAusgaben;

$flash = takeFlash();
$tiefe = '../../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = 'index.php';

function eGeld(float $betrag): string
{
    return number_format($betrag, 2, ',', '.') . ' €';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kassenbericht &ndash; Kassenwart &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
    <style>
        .kb-diagramme { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
        .kb-diagramm-box { text-align: center; }
        .kb-diagramm-box svg { max-width: 100%; height: auto; }
        .kb-legende { list-style: none; padding: 0; margin: 12px 0 0; text-align: left; font-size: 0.88rem; }
        .kb-legende li { display: flex; align-items: center; gap: 8px; padding: 3px 0; }
        .kb-legende .kb-punkt { width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; }
        .kb-legende .kb-betrag { margin-left: auto; font-weight: 600; white-space: nowrap; }
        .kb-vergleich-tabelle { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .kb-vergleich-tabelle th, .kb-vergleich-tabelle td { text-align: right; padding: 6px 10px; border-bottom: 1px solid var(--farbe-border); }
        .kb-vergleich-tabelle th:first-child, .kb-vergleich-tabelle td:first-child { text-align: left; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1200px;">
        <nav class="subnav">
            <a href="../antraege.php">Aufnahmeanträge</a>
            <a href="../mitglieder.php">Mitgliederverwaltung</a>
            <a href="../verteiler.php">E-Mail-Verteiler</a>
            <a href="index.php" class="active">Kassenwart</a>
            <a href="../vereinsdokumente.php">Vereinsdokumente</a>
            <a href="../protokolle.php">Protokolle</a>
        </nav>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
        <?php endif; ?>

        <div class="card">
            <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:baseline;">
                <h2 style="margin:0;">Kassenbericht <?= (int) $jahrAuswahl ?></h2>
                <div style="margin-left:auto; display:flex; gap:8px;">
                    <a class="btn btn-secondary" href="?jahr=<?= $jahrAuswahl - 1 ?>">&laquo; <?= $jahrAuswahl - 1 ?></a>
                    <a class="btn btn-secondary" href="?jahr=<?= $jahrAuswahl + 1 ?>"><?= $jahrAuswahl + 1 ?> &raquo;</a>
                </div>
            </div>
            <p class="text-muted" style="margin-bottom:0;">
                Einnahmen: <strong style="color:var(--farbe-success);"><?= eGeld($daten['gesamtEinnahmen']) ?></strong>
                &nbsp;&middot;&nbsp;
                Ausgaben: <strong style="color:var(--farbe-error);"><?= eGeld($daten['gesamtAusgaben']) ?></strong>
                &nbsp;&middot;&nbsp;
                Überschuss: <strong style="color:<?= $daten['ueberschuss'] >= 0 ? 'var(--farbe-success)' : 'var(--farbe-error)' ?>;"><?= eGeld($daten['ueberschuss']) ?></strong>
            </p>
        </div>

        <div class="card">
            <div class="kb-diagramme">
                <div class="kb-diagramm-box">
                    <h3>Einnahmen &amp; Ausgaben</h3>
                    <?= svgTortendiagramm($segmenteGesamt) ?>
                    <ul class="kb-legende">
                        <?php foreach ($segmenteGesamt as $s): ?>
                            <li><span class="kb-punkt" style="background:<?= e($s['farbe']) ?>;"></span><?= e($s['name']) ?><span class="kb-betrag"><?= eGeld($s['summe']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="kb-diagramm-box">
                    <h3>Verteilung der Einnahmen</h3>
                    <?= svgTortendiagramm($segmenteEinnahmen) ?>
                    <ul class="kb-legende">
                        <?php foreach ($segmenteEinnahmen as $s): ?>
                            <li><span class="kb-punkt" style="background:<?= e($s['farbe']) ?>;"></span><?= e($s['name']) ?><span class="kb-betrag"><?= eGeld($s['summe']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!empty($daten['top5Einnahmen'])): ?>
                        <h4 style="margin-bottom:4px;">Top 5 Einnahmen</h4>
                        <ul class="kb-legende">
                            <?php foreach ($daten['top5Einnahmen'] as $b): ?>
                                <li>
                                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e((new DateTime($b['datum']))->format('d.m.Y')) ?> &ndash; <?= e($b['beschreibung']) ?></span>
                                    <span class="kb-betrag"><?= eGeld((float) $b['betrag']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="kb-diagramm-box">
                    <h3>Verteilung der Ausgaben</h3>
                    <?= svgTortendiagramm($segmenteAusgaben) ?>
                    <ul class="kb-legende">
                        <?php foreach ($segmenteAusgaben as $s): ?>
                            <li><span class="kb-punkt" style="background:<?= e($s['farbe']) ?>;"></span><?= e($s['name']) ?><span class="kb-betrag"><?= eGeld($s['summe']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!empty($daten['top5Ausgaben'])): ?>
                        <h4 style="margin-bottom:4px;">Top 5 Ausgaben</h4>
                        <ul class="kb-legende">
                            <?php foreach ($daten['top5Ausgaben'] as $b): ?>
                                <li>
                                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e((new DateTime($b['datum']))->format('d.m.Y')) ?> &ndash; <?= e($b['beschreibung']) ?></span>
                                    <span class="kb-betrag"><?= eGeld((float) $b['betrag']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <p class="text-muted" style="margin-top:16px; margin-bottom:0;">Unkategorisierte Buchungen lassen sich bei <a href="kassenbuecher/vereinskonto.php">Vereinskonto</a> bzw. <a href="kassenbuecher/barkasse.php">Barkasse</a> einer Kategorie zuordnen.</p>
        </div>

        <?php if ($datenVorjahr !== null): ?>
        <div class="card">
            <h2 style="margin-top:0;">Vergleich zum Vorjahr</h2>
            <div style="overflow-x:auto;">
            <table class="kb-vergleich-tabelle">
                <thead>
                    <tr>
                        <th></th>
                        <th><?= (int) $vorjahr ?></th>
                        <th><?= (int) $jahrAuswahl ?></th>
                        <th>Differenz</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Einnahmen</td>
                        <td><?= eGeld($datenVorjahr['gesamtEinnahmen']) ?></td>
                        <td><?= eGeld($daten['gesamtEinnahmen']) ?></td>
                        <?php $diff = $daten['gesamtEinnahmen'] - $datenVorjahr['gesamtEinnahmen']; ?>
                        <td style="color:<?= $diff >= 0 ? 'var(--farbe-success)' : 'var(--farbe-error)' ?>;"><?= $diff >= 0 ? '+' : '' ?><?= eGeld($diff) ?></td>
                    </tr>
                    <tr>
                        <td>Ausgaben</td>
                        <td><?= eGeld($datenVorjahr['gesamtAusgaben']) ?></td>
                        <td><?= eGeld($daten['gesamtAusgaben']) ?></td>
                        <?php $diff = $daten['gesamtAusgaben'] - $datenVorjahr['gesamtAusgaben']; ?>
                        <td style="color:<?= $diff <= 0 ? 'var(--farbe-success)' : 'var(--farbe-error)' ?>;"><?= $diff >= 0 ? '+' : '' ?><?= eGeld($diff) ?></td>
                    </tr>
                    <tr>
                        <td>Überschuss</td>
                        <td><?= eGeld($datenVorjahr['ueberschuss']) ?></td>
                        <td><?= eGeld($daten['ueberschuss']) ?></td>
                        <?php $diff = $daten['ueberschuss'] - $datenVorjahr['ueberschuss']; ?>
                        <td style="color:<?= $diff >= 0 ? 'var(--farbe-success)' : 'var(--farbe-error)' ?>;"><?= $diff >= 0 ? '+' : '' ?><?= eGeld($diff) ?></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="card" id="prognose">
            <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:baseline;">
                <h2 style="margin:0;">Prognose Haushaltsjahr <?= (int) $prognoseJahr ?></h2>
                <div style="margin-left:auto; display:flex; gap:8px;">
                    <a class="btn btn-secondary" href="?jahr=<?= $jahrAuswahl ?>&prognose=<?= $prognoseJahr - 1 ?>#prognose">&laquo; <?= $prognoseJahr - 1 ?></a>
                    <a class="btn btn-secondary" href="?jahr=<?= $jahrAuswahl ?>&prognose=<?= $prognoseJahr + 1 ?>#prognose"><?= $prognoseJahr + 1 ?> &raquo;</a>
                </div>
            </div>

            <?php if ($prognoseBasis === null): ?>
                <p class="text-muted">Für das Basisjahr <?= (int) $prognoseBasisJahr ?> liegen noch keine Buchungen vor - die Prognose braucht ein abgeschlossenes Vorjahr als Grundlage.</p>
            <?php else: ?>
                <p class="text-muted">Basis ist das abgeschlossene Jahr <?= (int) $prognoseBasisJahr ?> (Einnahmen <?= eGeld($prognoseBasis['gesamtEinnahmen']) ?>, Ausgaben <?= eGeld($prognoseBasis['gesamtAusgaben']) ?>), unverändert fortgeschrieben. Sind für <?= (int) $prognoseJahr ?> größere abweichende Ausgaben geplant oder größere Einnahmen zu erwarten? Dann hier als Sonderposten eintragen, bevor die Prognose feststeht.</p>

                <h3>Sonderposten</h3>
                <?php if (empty($sonderposten)): ?>
                    <p class="text-muted">Noch keine Sonderposten für <?= (int) $prognoseJahr ?> erfasst.</p>
                <?php else: ?>
                    <div style="overflow-x:auto; margin-bottom:16px;">
                    <table class="tabelle-einzeilig">
                        <thead>
                            <tr>
                                <th>Bezeichnung</th>
                                <th>Typ</th>
                                <th>Betrag</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sonderposten as $s): ?>
                                <tr>
                                    <td><?= e($s['bezeichnung']) ?></td>
                                    <td><?= $s['typ'] === 'einnahme' ? 'Einnahme' : 'Ausgabe' ?></td>
                                    <td style="color:<?= $s['typ'] === 'einnahme' ? 'var(--farbe-success)' : 'var(--farbe-error)' ?>;"><?= eGeld((float) $s['betrag']) ?></td>
                                    <td>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="aktion" value="sonderposten_loeschen">
                                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                            <input type="hidden" name="prognose_jahr" value="<?= (int) $prognoseJahr ?>">
                                            <button type="submit" class="btn btn-secondary" onclick="return confirm('Diesen Sonderposten wirklich löschen?');">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="aktion" value="sonderposten_hinzufuegen">
                    <input type="hidden" name="prognose_jahr" value="<?= (int) $prognoseJahr ?>">

                    <label class="required" for="sp_bezeichnung">Bezeichnung</label>
                    <input type="text" id="sp_bezeichnung" name="bezeichnung" placeholder="z.B. neue Wettkampfausstattung" required>

                    <label class="required" for="sp_betrag">Betrag (€)</label>
                    <input type="text" id="sp_betrag" name="betrag" inputmode="decimal" placeholder="z.B. 500,00" required>

                    <label class="required" for="sp_typ">Typ</label>
                    <select id="sp_typ" name="typ" required>
                        <option value="ausgabe">Ausgabe</option>
                        <option value="einnahme">Einnahme</option>
                    </select>

                    <div style="margin-top:16px;">
                        <button type="submit" class="btn">Sonderposten hinzufügen</button>
                    </div>
                </form>

                <h3>Prognose-Übersicht</h3>
                <div style="overflow-x:auto;">
                <table class="kb-vergleich-tabelle">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Basis <?= (int) $prognoseBasisJahr ?></th>
                            <th>Sonderposten</th>
                            <th>Prognose <?= (int) $prognoseJahr ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Einnahmen</td>
                            <td><?= eGeld($prognoseBasis['gesamtEinnahmen']) ?></td>
                            <td><?= $sonderpostenEinnahmen > 0 ? '+' . eGeld($sonderpostenEinnahmen) : eGeld(0) ?></td>
                            <td style="font-weight:700; color:var(--farbe-success);"><?= eGeld($prognoseEinnahmen) ?></td>
                        </tr>
                        <tr>
                            <td>Ausgaben</td>
                            <td><?= eGeld($prognoseBasis['gesamtAusgaben']) ?></td>
                            <td><?= $sonderpostenAusgaben > 0 ? '+' . eGeld($sonderpostenAusgaben) : eGeld(0) ?></td>
                            <td style="font-weight:700; color:var(--farbe-error);"><?= eGeld($prognoseAusgaben) ?></td>
                        </tr>
                        <tr>
                            <td>Überschuss</td>
                            <td><?= eGeld($prognoseBasis['ueberschuss']) ?></td>
                            <td><?= eGeld($sonderpostenEinnahmen - $sonderpostenAusgaben) ?></td>
                            <?php $prognoseUeberschuss = $prognoseEinnahmen - $prognoseAusgaben; ?>
                            <td style="font-weight:700; color:<?= $prognoseUeberschuss >= 0 ? 'var(--farbe-success)' : 'var(--farbe-error)' ?>;"><?= eGeld($prognoseUeberschuss) ?></td>
                        </tr>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Automatische Zuordnung</h2>
            <p class="text-muted">Sobald ihr eine Buchung bei Vereinskonto oder Barkasse manuell einer Kategorie zuordnet, wird automatisch eine Regel für den Beteiligten/Empfänger gelernt oder aktualisiert - neue und bereits vorhandene unkategorisierte Buchungen mit demselben Stichwort werden dann automatisch zugeordnet. Hier lassen sich Regeln auch von Hand anlegen, z.B. ein Stichwort aus dem Verwendungszweck statt dem Beteiligten.</p>

            <?php if (empty($alleRegeln)): ?>
                <p>Noch keine Regeln gelernt.</p>
            <?php else: ?>
                <div style="overflow-x:auto; margin-bottom:16px;">
                <table class="tabelle-einzeilig">
                    <thead>
                        <tr>
                            <th>Stichwort</th>
                            <th>Kategorie</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alleRegeln as $regel): ?>
                            <tr>
                                <td><?= e($regel['stichwort']) ?></td>
                                <td><?= e($regel['kategorie_name']) ?> <span class="text-muted">(<?= $regel['typ'] === 'einnahme' ? 'Einnahme' : 'Ausgabe' ?>)</span></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="aktion" value="regel_loeschen">
                                        <input type="hidden" name="id" value="<?= (int) $regel['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Diese Regel wirklich löschen?');">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>

            <h3>Neue Regel</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="regel_hinzufuegen">

                <label class="required" for="regel_stichwort">Stichwort</label>
                <input type="text" id="regel_stichwort" name="stichwort" placeholder="z.B. Mitgliedsbeitrag" required>

                <label class="required" for="regel_kategorie">Kategorie</label>
                <select id="regel_kategorie" name="kategorie_id" required>
                    <?php foreach ($alleKategorien as $kat): ?>
                        <?php if ((bool) $kat['aktiv']): ?>
                            <option value="<?= (int) $kat['id'] ?>"><?= e($kat['name']) ?> (<?= $kat['typ'] === 'einnahme' ? 'Einnahme' : 'Ausgabe' ?>)</option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn">Regel speichern</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Kategorien verwalten</h2>
            <p class="text-muted">Kategorien für die Verteilungs-Diagramme oben. Ausgeblendete Kategorien bleiben bei bereits zugeordneten Buchungen erhalten.</p>

            <div style="overflow-x:auto; margin-bottom:16px;">
            <table class="tabelle-einzeilig">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Typ</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alleKategorien as $kat): ?>
                        <tr>
                            <td><?= e($kat['name']) ?></td>
                            <td><?= $kat['typ'] === 'einnahme' ? 'Einnahme' : 'Ausgabe' ?></td>
                            <td><?= (bool) $kat['aktiv'] ? '<span class="badge badge-angenommen">aktiv</span>' : '<span class="text-muted">ausgeblendet</span>' ?></td>
                            <td>
                                <?php if ((bool) $kat['aktiv']): ?>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="aktion" value="kategorie_deaktivieren">
                                        <input type="hidden" name="id" value="<?= (int) $kat['id'] ?>">
                                        <button type="submit" class="btn btn-secondary">Ausblenden</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <h3>Neue Kategorie</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="kategorie_hinzufuegen">

                <label class="required" for="kat_name">Name</label>
                <input type="text" id="kat_name" name="name" placeholder="z.B. Trikots" required>

                <label class="required" for="kat_typ">Typ</label>
                <select id="kat_typ" name="typ" required>
                    <option value="ausgabe">Ausgabe</option>
                    <option value="einnahme">Einnahme</option>
                </select>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn">Kategorie anlegen</button>
                </div>
            </form>
        </div>
    </main>
    <script src="../../../assets/js/menue.js" defer></script>
</body>
</html>
