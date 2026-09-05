<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function checkCsrfToken(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validiert und speichert das hochgeladene Foto.
 * Gibt den gespeicherten Dateinamen zurueck oder wirft eine RuntimeException.
 */
function handleFotoUpload(array $file): string
{
    $erlaubteTypen = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $maxBytes = 6 * 1024 * 1024; // 6 MB

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Bitte ein Foto auswaehlen.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Beim Hochladen des Fotos ist ein Fehler aufgetreten.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Das Foto darf maximal 6 MB gross sein.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset($erlaubteTypen[$mime])) {
        throw new RuntimeException('Bitte nur JPG-, PNG- oder WebP-Bilder hochladen.');
    }

    $zielOrdner = __DIR__ . '/../private/uploads/fotos/';
    if (!is_dir($zielOrdner) && !mkdir($zielOrdner, 0750, true) && !is_dir($zielOrdner)) {
        throw new RuntimeException('Speicherort fuer Fotos konnte nicht angelegt werden.');
    }

    $dateiname = bin2hex(random_bytes(16)) . '.' . $erlaubteTypen[$mime];
    $zielPfad = $zielOrdner . $dateiname;

    if (!move_uploaded_file($file['tmp_name'], $zielPfad)) {
        throw new RuntimeException('Foto konnte nicht gespeichert werden.');
    }

    return $dateiname;
}
