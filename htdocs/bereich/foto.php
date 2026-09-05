<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$mitglied = requireMemberLogin('../login.php');

$typ = $_GET['typ'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($typ === 'antrag') {
    if ($mitglied['rolle'] !== 'vorstandsmitglied') {
        http_response_code(403);
        exit;
    }
    $stmt = getPdo()->prepare('SELECT foto_dateiname FROM antraege WHERE id = :id');
} elseif ($typ === 'mitglied') {
    if ($mitglied['rolle'] !== 'vorstandsmitglied' && $id !== (int) $mitglied['id']) {
        http_response_code(403);
        exit;
    }
    $stmt = getPdo()->prepare('SELECT foto_dateiname FROM mitglieder WHERE id = :id');
} else {
    http_response_code(400);
    exit;
}

$stmt->execute(['id' => $id]);
$datensatz = $stmt->fetch();

if (!$datensatz || !$datensatz['foto_dateiname']) {
    http_response_code(404);
    exit;
}

// Dateiname stammt aus der DB und wurde beim Upload auf [a-f0-9]+.(jpg|png|webp) beschraenkt.
$pfad = __DIR__ . '/../../private/uploads/fotos/' . basename($datensatz['foto_dateiname']);

if (!is_file($pfad)) {
    http_response_code(404);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($pfad) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($pfad));
header('Cache-Control: private, max-age=0, no-cache');
readfile($pfad);
