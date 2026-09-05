<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdminLogin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = getPdo()->prepare('SELECT foto_dateiname FROM antraege WHERE id = :id');
$stmt->execute(['id' => $id]);
$antrag = $stmt->fetch();

if (!$antrag || !$antrag['foto_dateiname']) {
    http_response_code(404);
    exit;
}

// Dateiname stammt aus der DB und wurde beim Upload auf [a-f0-9]+.(jpg|png|webp) beschraenkt.
$pfad = __DIR__ . '/../../private/uploads/fotos/' . basename($antrag['foto_dateiname']);

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
