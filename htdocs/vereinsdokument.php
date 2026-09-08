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

// Dateiname stammt aus der DB und wurde beim Upload auf [a-f0-9]+.pdf beschraenkt.
$pfad = __DIR__ . '/../private/uploads/vereinsdokumente/' . basename($dokument['dateiname']);

if (!is_file($pfad)) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9 _.-]/', '', $dokument['bezeichnung']) . '.pdf"');
header('Content-Length: ' . (string) filesize($pfad));
header('Cache-Control: private, max-age=0, no-cache');
readfile($pfad);
