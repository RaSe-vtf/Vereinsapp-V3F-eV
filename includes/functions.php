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

const ROLLEN_LABELS = [
    'vollmitglied' => 'Vollmitglied',
    'trainingsmitglied' => 'Trainingsmitglied',
    'vorstandsmitglied' => 'Vorstandsmitglied',
    'ehrenmitglied' => 'Ehrenmitglied',
    'foerdermitglied' => 'Fördermitglied',
];

function rollenLabel(string $rolle): string
{
    return ROLLEN_LABELS[$rolle] ?? $rolle;
}

function generateInitialPasswort(): string
{
    return bin2hex(random_bytes(5));
}

/**
 * Verschickt eine Rundmail per Bcc an die angegebenen Adressen, in Bloecken
 * von je 40 Empfaengern (schont Mailserver-Limits und schuetzt die
 * Empfaenger-Adressen der jeweils anderen Mitglieder).
 * Gibt zurueck, wie viele Empfaenger erfolgreich bzw. nicht erreicht wurden.
 */
function sendeRundmail(string $betreff, string $nachricht, array $empfaenger): array
{
    $betreff = str_replace(["\r", "\n"], ' ', trim($betreff));
    $betreffKodiert = '=?UTF-8?B?' . base64_encode($betreff) . '?=';

    $headers = "From: " . MAIL_ABSENDER_NAME . " <" . MAIL_ABSENDER_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . MAIL_ABSENDER_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $erfolgreich = 0;
    $fehlgeschlagen = 0;

    foreach (array_chunk(array_unique($empfaenger), 40) as $block) {
        $blockHeaders = $headers . 'Bcc: ' . implode(', ', $block) . "\r\n";
        if (mail(MAIL_ABSENDER_EMAIL, $betreffKodiert, $nachricht, $blockHeaders)) {
            $erfolgreich += count($block);
        } else {
            $fehlgeschlagen += count($block);
        }
    }

    return ['erfolgreich' => $erfolgreich, 'fehlgeschlagen' => $fehlgeschlagen];
}

function setFlash(string $typ, string $text): void
{
    $_SESSION['flash'] = ['typ' => $typ, 'text' => $text];
}

function takeFlash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
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
