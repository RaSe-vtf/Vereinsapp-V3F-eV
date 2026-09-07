<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

requireAdmin('../../login.php', '../index.php');

$dateiname = basename((string) ($_GET['datei'] ?? ''));
if ($dateiname === '') {
    http_response_code(400);
    exit;
}

$pfad = __DIR__ . '/../../../private/uploads/fotos/' . $dateiname;

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
