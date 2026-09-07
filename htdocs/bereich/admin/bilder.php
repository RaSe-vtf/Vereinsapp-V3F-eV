<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireAdmin('../../login.php', '../index.php');
$pdo = getPdo();
$fotoOrdner = __DIR__ . '/../../../private/uploads/fotos/';

/**
 * Liefert alle aktuell referenzierten Fotos mit Verwendung, Pfad und -
 * falls die Datei existiert - Abmessungen/Dateigroesse.
 */
function ermittleFotoUebersicht(PDO $pdo, string $fotoOrdner): array
{
    $stmt = $pdo->query(
        "SELECT foto_dateiname, GROUP_CONCAT(DISTINCT name SEPARATOR ', ') AS verwendet_von
         FROM (
             SELECT foto_dateiname, CONCAT(vorname, ' ', nachname) AS name FROM mitglieder WHERE foto_dateiname IS NOT NULL
             UNION ALL
             SELECT foto_dateiname, CONCAT(vorname, ' ', nachname) AS name FROM antraege WHERE foto_dateiname IS NOT NULL
         ) AS alle
         GROUP BY foto_dateiname
         ORDER BY foto_dateiname"
    );
    $zeilen = $stmt->fetchAll();

    $ergebnis = [];
    foreach ($zeilen as $zeile) {
        $dateiname = basename($zeile['foto_dateiname']);
        $pfad = $fotoOrdner . $dateiname;
        $eintrag = [
            'dateiname' => $dateiname,
            'verwendet_von' => $zeile['verwendet_von'],
            'existiert' => is_file($pfad),
            'breite' => null,
            'hoehe' => null,
            'bytes' => null,
            'zu_gross' => false,
        ];
        if ($eintrag['existiert']) {
            $groesse = @getimagesize($pfad);
            if ($groesse !== false) {
                $eintrag['breite'] = $groesse[0];
                $eintrag['hoehe'] = $groesse[1];
                $eintrag['zu_gross'] = $groesse[0] > FOTO_MAX_KANTE || $groesse[1] > FOTO_MAX_KANTE;
            }
            $eintrag['bytes'] = filesize($pfad);
        }
        $ergebnis[] = $eintrag;
    }
    return $ergebnis;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrfToken($_POST['csrf_token'] ?? null)) {
    $aktion = $_POST['aktion'] ?? '';

    if ($aktion === 'verkleinern_einzeln' && !empty($_POST['dateiname'])) {
        $zielDateien = [basename((string) $_POST['dateiname'])];
    } elseif ($aktion === 'verkleinern_alle') {
        $uebersicht = ermittleFotoUebersicht($pdo, $fotoOrdner);
        $zielDateien = array_map(
            fn (array $e) => $e['dateiname'],
            array_filter($uebersicht, fn (array $e) => $e['existiert'] && $e['zu_gross'])
        );
    } else {
        $zielDateien = [];
    }

    $geprueft = 0;
    $verkleinert = 0;
    $eingespartBytes = 0;

    foreach ($zielDateien as $dateiname) {
        $geprueft++;
        $pfad = $fotoOrdner . $dateiname;
        if (!is_file($pfad)) {
            continue;
        }
        $vorherBytes = filesize($pfad);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($pfad);
        try {
            verarbeiteUndSpeichereFoto($pfad, $mime, $pfad);
            clearstatcache(true, $pfad);
            $nachherBytes = filesize($pfad);
            $eingespartBytes += max(0, $vorherBytes - $nachherBytes);
            $verkleinert++;
        } catch (\Throwable $e) {
            // Diese eine Datei ueberspringen, restliche Batch-Verarbeitung fortsetzen.
        }
    }

    if ($geprueft === 0) {
        setFlash('success', 'Keine zu großen Bilder gefunden – alles bereits optimal.');
    } else {
        $text = "$geprueft Bild(er) geprüft, $verkleinert verkleinert";
        if ($eingespartBytes > 0) {
            $text .= ', ' . formatiereDateigroesse($eingespartBytes) . ' eingespart.';
        } else {
            $text .= '.';
        }
        setFlash('success', $text);
    }

    header('Location: bilder.php');
    exit;
}

$uebersicht = ermittleFotoUebersicht($pdo, $fotoOrdner);
$anzahlZuGross = count(array_filter($uebersicht, fn (array $e) => $e['existiert'] && $e['zu_gross']));
$flash = takeFlash();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bilder &ndash; Admin &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <header class="top-header">
        <div class="top-header__inner">
            <img class="top-header__logo" src="../../assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <div>
                <div class="top-header__title"><?= e(APP_NAME) ?></div>
                <div class="top-header__subtitle"><?= e($mitglied['vorname'] . ' ' . $mitglied['nachname']) ?> &middot; Admin</div>
            </div>
            <div style="margin-left:auto;">
                <a href="../../logout.php" class="btn btn-secondary">Abmelden</a>
            </div>
        </div>
    </header>

    <main class="container" style="max-width:1040px;">
        <nav class="tabs">
            <a href="../index.php">Meine Daten</a>
            <?php if ($mitglied['rolle'] === 'vorstandsmitglied'): ?>
                <a href="../vorstand/antraege.php">Geschäftsstelle</a>
            <?php endif; ?>
            <a href="konten.php" class="active">Admin</a>
        </nav>

        <nav class="subnav">
            <a href="konten.php">Konten</a>
            <a href="bilder.php" class="active">Bilder</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Bilder</h2>
            <p class="text-muted">Alle aktuell verwendeten Fotos aus Aufnahmeanträgen und Mitgliederkonten. Bilder über <?= FOTO_MAX_KANTE ?>px an der längsten Kante werden beim Verkleinern automatisch auf diese Größe gebracht (gleiche Verarbeitung wie beim Hochladen).</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
            <?php endif; ?>

            <?php if ($anzahlZuGross > 0): ?>
                <form method="post" style="margin-bottom:16px;">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="aktion" value="verkleinern_alle">
                    <button type="submit" class="btn"><?= $anzahlZuGross ?> zu große Bild(er) jetzt alle verkleinern</button>
                </form>
            <?php endif; ?>

            <?php if (empty($uebersicht)): ?>
                <p>Noch keine Fotos vorhanden.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Vorschau</th>
                            <th>Verwendet von</th>
                            <th>Abmessungen</th>
                            <th>Größe</th>
                            <th>Status</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uebersicht as $e): ?>
                            <tr>
                                <td>
                                    <?php if ($e['existiert']): ?>
                                        <img class="foto-thumb foto-zoombar" src="foto.php?datei=<?= urlencode($e['dateiname']) ?>" alt="Vorschau">
                                    <?php else: ?>
                                        <span class="text-muted">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($e['verwendet_von']) ?></td>
                                <td><?= $e['breite'] ? e($e['breite'] . '×' . $e['hoehe']) : '&ndash;' ?></td>
                                <td><?= $e['bytes'] !== null ? e(formatiereDateigroesse($e['bytes'])) : '&ndash;' ?></td>
                                <td>
                                    <?php if (!$e['existiert']): ?>
                                        <span class="badge badge-abgelehnt">Datei fehlt</span>
                                    <?php elseif ($e['zu_gross']): ?>
                                        <span class="badge badge-neu">zu groß</span>
                                    <?php else: ?>
                                        <span class="badge badge-angenommen">ok</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($e['existiert'] && $e['zu_gross']): ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="dateiname" value="<?= e($e['dateiname']) ?>">
                                            <input type="hidden" name="aktion" value="verkleinern_einzeln">
                                            <button type="submit" class="btn btn-secondary">Verkleinern</button>
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
    </main>
    <script src="../../assets/js/lightbox.js" defer></script>
</body>
</html>
