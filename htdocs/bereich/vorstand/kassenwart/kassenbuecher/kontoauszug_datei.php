<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../../includes/db.php';
require_once __DIR__ . '/../../../../../includes/functions.php';
require_once __DIR__ . '/../../../../../includes/auth.php';

requireVorstand('../../../../login.php', '../../../index.php');

$id = (int) ($_GET['id'] ?? 0);
$stmt = getPdo()->prepare('SELECT dateiname, format FROM kontoauszuege WHERE id = :id');
$stmt->execute(['id' => $id]);
$auszug = $stmt->fetch();

if (!$auszug) {
    http_response_code(404);
    exit;
}

// Dateiname stammt aus der DB und wurde beim Upload auf [a-f0-9]+.(xml|sta) beschraenkt.
$pfad = __DIR__ . '/../../../../../private/uploads/kontoauszuege/' . basename($auszug['dateiname']);

if (!is_file($pfad)) {
    http_response_code(404);
    exit;
}

$mime = $auszug['format'] === 'camt053' ? 'application/xml' : 'text/plain';

header('Content-Type: ' . $mime . '; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . basename($auszug['dateiname']) . '"');
header('Content-Length: ' . (string) filesize($pfad));
header('Cache-Control: private, max-age=0, no-cache');
readfile($pfad);
