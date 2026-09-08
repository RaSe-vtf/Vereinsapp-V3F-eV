<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');
$pdo = getPdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.');
    } elseif (($_POST['aktion'] ?? '') === 'hochladen') {
        $bezeichnung = trim((string) ($_POST['bezeichnung'] ?? ''));
        if ($bezeichnung === '') {
            setFlash('error', 'Bitte eine Bezeichnung angeben.');
        } else {
            try {
                $hochgeladen = handleVereinsdokumentUpload($_FILES['datei'] ?? []);
                $pdo->prepare('INSERT INTO vereinsdokumente (bezeichnung, dateiname, original_dateiname, hochgeladen_von_id) VALUES (:bezeichnung, :dateiname, :original_dateiname, :hochgeladen_von_id)')
                    ->execute([
                        'bezeichnung' => $bezeichnung,
                        'dateiname' => $hochgeladen['dateiname'],
                        'original_dateiname' => $hochgeladen['original_dateiname'],
                        'hochgeladen_von_id' => $mitglied['id'],
                    ]);
                setFlash('success', 'Dokument wurde hochgeladen.');
            } catch (Throwable $e) {
                setFlash('error', $e->getMessage());
            }
        }
    } elseif (($_POST['aktion'] ?? '') === 'loeschen' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('SELECT dateiname FROM vereinsdokumente WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('DELETE FROM vereinsdokumente WHERE id = :id')->execute(['id' => $id]);
            $pfad = __DIR__ . '/../../../private/uploads/vereinsdokumente/' . basename($row['dateiname']);
            if (is_file($pfad)) {
                unlink($pfad);
            }
            setFlash('success', 'Dokument wurde gelöscht.');
        }
    }
    header('Location: vereinsdokumente.php');
    exit;
}

$alleDokumente = $pdo->query(
    'SELECT v.*, m.vorname, m.nachname
     FROM vereinsdokumente v
     LEFT JOIN mitglieder m ON m.id = v.hochgeladen_von_id
     ORDER BY v.bezeichnung, COALESCE(v.original_dateiname, v.dateiname), v.hochgeladen_am DESC, v.id DESC'
)->fetchAll();

$bekannteBezeichnungen = array_values(array_unique(array_column($alleDokumente, 'bezeichnung')));
sort($bekannteBezeichnungen, SORT_NATURAL | SORT_FLAG_CASE);

// Nur die drei festen Kategorie-Ueberschriften (Satzung/Ordnungen/Sonstiges) -
// keine zusaetzliche Bezeichnung-Ueberschrift darunter, alle Dokumente einer
// Kategorie stehen in einer gemeinsamen Tabelle (Bezeichnung als Spalte).
$kategorien = array_fill_keys(VEREINSDOKUMENT_KATEGORIEN, []);
foreach ($alleDokumente as $doc) {
    $kategorien[kategorisiereVereinsdokument($doc['bezeichnung'])][] = $doc;
}

$flash = takeFlash();
$tiefe = '../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = '../home.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vereinsdokumente &ndash; Geschäftsstelle &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
    <style>
        /* Vereinsdokumente-Tabelle hat viele Spalten mit teils langen
           Bezeichnungen/Dateinamen - kompakter als die App-weite Tabellen-
           Grundschrift, damit auf einem Laptop-Bildschirm alles ohne
           horizontales Scrollen sichtbar ist. */
        .vd-tabelle { font-size: 0.78rem; }
        .vd-tabelle th,
        .vd-tabelle td { padding: 5px 7px; }
        .vd-tabelle .btn,
        .vd-tabelle .btn-secondary { padding: 3px 7px; font-size: 0.72rem; }
        .vd-tabelle .badge { padding: 2px 7px; font-size: 0.7rem; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">
        <nav class="subnav">
            <a href="antraege.php">Aufnahmeanträge</a>
            <a href="mitglieder.php">Mitgliederverwaltung</a>
            <a href="verteiler.php">E-Mail-Verteiler</a>
            <a href="kassenwart/index.php">Kassenwart</a>
            <a href="vereinsdokumente.php" class="active">Vereinsdokumente</a>
            <a href="protokolle.php">Protokolle</a>
        </nav>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 style="margin-top:0;">Vereinsdokumente</h2>
            <p class="text-muted">Satzung und Ordnungen. Die jeweils neueste Fassung je Datei wird im öffentlichen Aufnahmeantrag verlinkt, ältere Fassungen bleiben hier als Historie erhalten. Mehrere unterschiedliche Dateien dürfen dieselbe Bezeichnung tragen (z.B. "Vereinsordnungen") - nur ein erneuter Upload mit demselben Dateinamen gilt als neue Fassung derselben Datei.</p>

            <?php if (empty($alleDokumente)): ?>
                <p>Noch keine Dokumente hochgeladen.</p>
            <?php else: ?>
                <?php foreach ($kategorien as $kategorieName => $dokumente): ?>
                    <?php if (empty($dokumente)) continue; ?>
                    <h2><?= e($kategorieName) ?></h2>
                    <div style="overflow-x:auto; margin-bottom:20px;">
                    <table class="tabelle-einzeilig vd-tabelle">
                        <thead>
                            <tr>
                                <th>Bezeichnung</th>
                                <th>Dateiname</th>
                                <th>Datum</th>
                                <th>Von</th>
                                <th>Format</th>
                                <th>Status</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $gesehen = []; ?>
                            <?php foreach ($dokumente as $doc): ?>
                                <?php
                                $schluessel = $doc['bezeichnung'] . '|' . ($doc['original_dateiname'] ?? $doc['dateiname']);
                                $istAktuell = !isset($gesehen[$schluessel]);
                                $gesehen[$schluessel] = true;
                                ?>
                                <tr>
                                    <td><?= e($doc['bezeichnung']) ?></td>
                                    <td><?= $doc['original_dateiname'] !== null ? e($doc['original_dateiname']) : '&ndash;' ?></td>
                                    <td><?= e((new DateTime($doc['hochgeladen_am']))->format('d.m.Y H:i')) ?></td>
                                    <td><?= $doc['vorname'] !== null ? e($doc['vorname'] . ' ' . $doc['nachname']) : '&ndash;' ?></td>
                                    <td><?= e(strtoupper(pathinfo($doc['dateiname'], PATHINFO_EXTENSION))) ?></td>
                                    <td><?= $istAktuell ? '<span class="badge badge-angenommen">aktuell</span>' : '<span class="badge badge-neu">Historie</span>' ?></td>
                                    <td>
                                        <a class="btn btn-secondary" href="../../vereinsdokument.php?id=<?= (int) $doc['id'] ?>">Herunterladen</a>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="aktion" value="loeschen">
                                            <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                                            <button type="submit" class="btn btn-secondary" onclick="return confirm('Diese Datei &quot;<?= e($doc['original_dateiname'] ?? $doc['dateiname']) ?>&quot; (<?= e($doc['bezeichnung']) ?>) wirklich löschen?');">Löschen</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h3>Neue Datei hochladen</h3>
            <p class="text-muted">Lädst du erneut eine Datei mit demselben Dateinamen wie eine bereits vorhandene hoch, wird sie als deren aktuelle Fassung geführt (die vorherige bleibt als Historie erhalten). Ein anderer Dateiname unter derselben Bezeichnung gilt dagegen als eigenständiges, zusätzliches Dokument. Es gibt keine Formatbeschränkung: PDF bleibt PDF, Bilder (JPG/PNG/WebP) werden automatisch in eine PDF-Seite gewandelt, andere Formate (z.B. Word) werden im Originalformat gespeichert.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="hochladen">

                <label class="required" for="bezeichnung">Bezeichnung</label>
                <input type="text" id="bezeichnung" name="bezeichnung" list="bekannte-bezeichnungen" placeholder="z.B. Satzung, Beitragsordnung, Wahlordnung" required>
                <datalist id="bekannte-bezeichnungen">
                    <?php foreach ($bekannteBezeichnungen as $b): ?>
                        <option value="<?= e($b) ?>">
                    <?php endforeach; ?>
                </datalist>

                <label class="required" for="datei">Datei</label>
                <input type="file" id="datei" name="datei" required>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn">Hochladen</button>
                </div>
            </form>
        </div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
