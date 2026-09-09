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
 * Liefert alle aktuell referenzierten Foto-Dateinamen aus Aufnahmeanträgen
 * und Mitgliederkonten (dedupliziert).
 */
function ermittleAlleFotoDateinamen(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT DISTINCT foto_dateiname FROM (
            SELECT foto_dateiname FROM mitglieder WHERE foto_dateiname IS NOT NULL
            UNION
            SELECT foto_dateiname FROM antraege WHERE foto_dateiname IS NOT NULL
        ) AS alle"
    );
    return array_map('basename', array_column($stmt->fetchAll(), 'foto_dateiname'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrfToken($_POST['csrf_token'] ?? null)) {
    $geprueft = 0;
    $verkleinert = 0;
    $eingespartBytes = 0;

    foreach (ermittleAlleFotoDateinamen($pdo) as $dateiname) {
        $pfad = $fotoOrdner . $dateiname;
        if (!is_file($pfad)) {
            continue;
        }
        $geprueft++;

        $groesse = @getimagesize($pfad);
        if ($groesse !== false && $groesse[0] <= FOTO_MAX_KANTE && $groesse[1] <= FOTO_MAX_KANTE) {
            continue;
        }

        $vorherBytes = filesize($pfad);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($pfad);
        try {
            verarbeiteUndSpeichereFoto($pfad, $mime, $pfad);
            clearstatcache(true, $pfad);
            $eingespartBytes += max(0, $vorherBytes - filesize($pfad));
            $verkleinert++;
        } catch (\Throwable $e) {
            // Diese eine Datei ueberspringen, restliche Verarbeitung fortsetzen.
        }
    }

    if ($geprueft === 0) {
        setFlash('success', 'Keine Bilder vorhanden.');
    } else {
        $text = "$geprueft Bild(er) geprüft, $verkleinert verkleinert";
        $text .= $eingespartBytes > 0 ? ', ' . formatiereDateigroesse($eingespartBytes) . ' eingespart.' : '.';
        setFlash('success', $text);
    }

    header('Location: bilder.php');
    exit;
}

$flash = takeFlash();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bilder &ndash; Admin &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php
    $tiefe = '../';
    $seitenUntertitel = 'Admin';
    $aktivReiter = 'admin';
    $zurueck = '../home.php';
    require __DIR__ . '/../../../includes/kopf.php';
    ?>

    <main class="container" style="max-width:1040px;">
        <nav class="subnav">
            <a href="konten.php">Konten</a>
            <a href="bilder.php" class="active">Bilder</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Bestehende Bilder optimieren</h2>
            <p class="text-muted">Verkleinert und komprimiert alle bereits hochgeladenen Bilder (Profilfotos, Aufnahmeantrag-Fotos) auf maximal <?= FOTO_MAX_KANTE ?>px Kantenlänge &ndash; genau wie es automatisch beim Hochladen passiert. Bereits kleine Bilder werden nicht verändert. Das kann je nach Anzahl der Bilder eine Weile dauern; die Seite lädt dabei automatisch weiter, bis alles fertig ist.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <button type="submit" class="btn">Bilder jetzt prüfen und optimieren</button>
            </form>
        </div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
