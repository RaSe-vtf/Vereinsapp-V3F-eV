<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = getPdo()->prepare('SELECT bezeichnung, dateiname FROM vereinsdokumente WHERE id = :id');
$stmt->execute(['id' => $id]);
$dokument = $stmt->fetch();

if (!$dokument) {
    http_response_code(404);
    exit;
}

// Dateiname stammt aus der DB und wurde beim Upload auf [a-f0-9]+.<endung> beschraenkt.
$pfad = __DIR__ . '/../private/uploads/vereinsdokumente/' . basename($dokument['dateiname']);

if (!is_file($pfad)) {
    http_response_code(404);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($pfad) ?: 'application/octet-stream';
$endung = strtolower(pathinfo($dokument['dateiname'], PATHINFO_EXTENSION)) ?: 'bin';
$anzeigename = preg_replace('/[^A-Za-z0-9 _.-]/', '', $dokument['bezeichnung']) . '.' . $endung;

// Nur Formate, die Browser direkt im Tab anzeigen koennen, inline oeffnen -
// alles andere (Word, PowerPoint, HTML, ...) als Download anbieten, sonst
// zeigt der Browser beim Versuch, es inline zu rendern, nur eine leere Seite.
$inlineFaehig = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
$disposition = in_array($mime, $inlineFaehig, true) ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $anzeigename . '"');
header('Content-Length: ' . (string) filesize($pfad));
header('Cache-Control: private, max-age=0, no-cache');
readfile($pfad);
