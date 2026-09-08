<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../../includes/db.php';
require_once __DIR__ . '/../../../../../includes/functions.php';
require_once __DIR__ . '/../../../../../includes/auth.php';

requireVorstand('../../../../login.php', '../../../index.php');

$id = (int) ($_GET['id'] ?? 0);
$stmt = getPdo()->prepare('SELECT beleg_dateiname FROM barkasse_buchungen WHERE id = :id');
$stmt->execute(['id' => $id]);
$buchung = $stmt->fetch();

if (!$buchung || !$buchung['beleg_dateiname']) {
    http_response_code(404);
    exit;
}

// Dateiname stammt aus der DB und wurde beim Upload auf [a-f0-9]+.(jpg|pdf) beschraenkt.
$pfad = __DIR__ . '/../../../../../private/uploads/belege/' . basename($buchung['beleg_dateiname']);

if (!is_file($pfad)) {
    http_response_code(404);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($pfad) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($pfad));
header('Cache-Control: private, max-age=0, no-cache');
readfile($pfad);
