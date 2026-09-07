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

function formatiereDateigroesse(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }
    return number_format($bytes / 1024, 0, ',', '.') . ' KB';
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

const FOTO_MAX_KANTE = 1600;
const FOTO_JPEG_QUALITAET = 82;
const FOTO_MIN_MEMORY_LIMIT = 256 * 1024 * 1024; // 256 MB

/**
 * Wandelt PHP-Ini-Groessenangaben wie "128M" oder "1G" in Bytes um.
 */
function phpGroesseInBytes(string $wert): int
{
    $wert = trim($wert);
    if ($wert === '' || $wert === '-1') {
        return -1; // kein Limit
    }
    $einheit = strtoupper(substr($wert, -1));
    $zahl = (int) $wert;
    return match ($einheit) {
        'G' => $zahl * 1024 * 1024 * 1024,
        'M' => $zahl * 1024 * 1024,
        'K' => $zahl * 1024,
        default => (int) $wert,
    };
}

/**
 * Erhoeht das PHP-Speicherlimit fuer die aktuelle Ausfuehrung, falls es
 * fuer die Bildverarbeitung knapp bemessen ist. Wirkungslos (aber
 * unschaedlich), falls der Host ini_set dafuer nicht erlaubt.
 */
function stelleAusreichendFotoSpeicherSicher(): void
{
    $aktuell = ini_get('memory_limit');
    if ($aktuell === false) {
        return;
    }
    $aktuellBytes = phpGroesseInBytes($aktuell);
    if ($aktuellBytes !== -1 && $aktuellBytes < FOTO_MIN_MEMORY_LIMIT) {
        @ini_set('memory_limit', '256M');
    }
}

/**
 * Validiert und speichert das hochgeladene Foto: richtet es anhand der
 * EXIF-Kameraausrichtung automatisch korrekt aus, verkleinert es auf
 * maximal FOTO_MAX_KANTE Pixel an der laengsten Kante und speichert es als
 * komprimiertes JPEG - damit Handyfotos (oft mehrere MB) nicht unveraendert
 * abgelegt werden und die App langsam machen.
 * Gibt den gespeicherten Dateinamen zurueck oder wirft eine RuntimeException.
 */
function handleFotoUpload(array $file): string
{
    $erlaubteTypen = ['image/jpeg', 'image/png', 'image/webp'];
    $maxBytes = 10 * 1024 * 1024; // 10 MB

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Bitte ein Foto auswaehlen.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Beim Hochladen des Fotos ist ein Fehler aufgetreten.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Ungültiger Foto-Upload.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Das Foto darf maximal 10 MB groß sein.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $erlaubteTypen, true)) {
        throw new RuntimeException('Bitte nur JPG-, PNG- oder WebP-Bilder hochladen.');
    }

    $zielOrdner = __DIR__ . '/../private/uploads/fotos/';
    if (!is_dir($zielOrdner) && !mkdir($zielOrdner, 0750, true) && !is_dir($zielOrdner)) {
        throw new RuntimeException('Speicherort für Fotos konnte nicht angelegt werden.');
    }

    $dateiname = bin2hex(random_bytes(16)) . '.jpg';
    verarbeiteUndSpeichereFoto($file['tmp_name'], $mime, $zielOrdner . $dateiname);

    return $dateiname;
}

/**
 * Laedt ein Bild von $quellPfad, korrigiert die EXIF-Ausrichtung (nur
 * JPEG traegt diese Information), verkleinert es bei Bedarf und speichert
 * es als JPEG unter $zielPfad. Wird sowohl vom Web-Upload als auch vom
 * CLI-Bootstrap-Skript genutzt, damit beide Wege gleich behandelt werden.
 */
function verarbeiteUndSpeichereFoto(string $quellPfad, string $mime, string $zielPfad): void
{
    // Grosse Handyfotos (10+ Megapixel) brauchen beim Dekodieren/Drehen/
    // Verkleinern kurzzeitig viel Speicher. Falls das Server-Limit knapp
    // ist, hier fuer diesen Ablauf grosszuegiger anfordern (schadet nicht,
    // falls der Host das ohnehin nicht erlaubt, bleibt es beim Ausgangswert).
    stelleAusreichendFotoSpeicherSicher();

    $lader = match ($mime) {
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        default => null,
    };
    if ($lader === null || !function_exists($lader)) {
        throw new RuntimeException('Dieses Bildformat wird auf dem Server nicht unterstützt.');
    }

    $bild = $lader($quellPfad);
    if ($bild === false) {
        throw new RuntimeException('Foto konnte nicht gelesen werden.');
    }

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($quellPfad);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $bild = korrigiereFotoAusrichtung($bild, $orientation);
    }

    $breite = imagesx($bild);
    $hoehe = imagesy($bild);
    if ($breite > FOTO_MAX_KANTE || $hoehe > FOTO_MAX_KANTE) {
        $faktor = min(FOTO_MAX_KANTE / $breite, FOTO_MAX_KANTE / $hoehe);
        $neueBreite = max(1, (int) round($breite * $faktor));
        $neueHoehe = max(1, (int) round($hoehe * $faktor));

        $verkleinert = imagecreatetruecolor($neueBreite, $neueHoehe);
        imagecopyresampled($verkleinert, $bild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);
        imagedestroy($bild);
        $bild = $verkleinert;
    }

    $gespeichert = imagejpeg($bild, $zielPfad, FOTO_JPEG_QUALITAET);
    imagedestroy($bild);

    if (!$gespeichert) {
        throw new RuntimeException('Foto konnte nicht gespeichert werden.');
    }
}

/**
 * Dreht ein GD-Bild passend zum EXIF-Orientation-Wert einer JPEG-Datei
 * (z.B. seitlich oder auf dem Kopf aufgenommene Handyfotos).
 */
function korrigiereFotoAusrichtung(\GdImage $bild, int $orientation): \GdImage
{
    $gedreht = match ($orientation) {
        3 => imagerotate($bild, 180, 0),
        6 => imagerotate($bild, -90, 0),
        8 => imagerotate($bild, 90, 0),
        default => null,
    };

    if ($gedreht === false || $gedreht === null) {
        return $bild;
    }

    imagedestroy($bild);
    return $gedreht;
}
