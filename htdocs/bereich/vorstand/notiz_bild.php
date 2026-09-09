<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

requireVorstand('../../login.php', '../index.php');

$id = (int) ($_GET['id'] ?? 0);

$stmt = getPdo()->prepare('SELECT dateiname FROM notiz_bilder WHERE id = :id');
$stmt->execute(['id' => $id]);
$bild = $stmt->fetch();

if (!$bild) {
    http_response_code(404);
    exit;
}

// Dateiname stammt aus der DB und wurde beim Upload auf [a-f0-9]+.jpg beschraenkt.
$pfad = __DIR__ . '/../../../private/uploads/notizen/' . basename($bild['dateiname']);

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
