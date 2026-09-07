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

/**
 * Rolle eines Mitglieds fuer den Bankbereich (Bankverbindungen-Liste,
 * Zuordnung der Beitragsposten im SEPA-Export): Admins und
 * Vorstandsmitglieder gelten hier unabhaengig von ihrer sonstigen Rolle
 * als Vollmitglieder.
 */
function bankRolle(array $mitglied): string
{
    if (!empty($mitglied['ist_admin']) || $mitglied['rolle'] === 'vorstandsmitglied') {
        return 'vollmitglied';
    }
    return $mitglied['rolle'];
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

/**
 * Verschickt eine einzelne E-Mail direkt an einen Empfaenger (kein Bcc-
 * Verteiler) - genutzt fuer die automatische Annahme-/Ablehnungs-Mail an
 * Antragsteller. Gibt zurueck, ob der Versand erfolgreich war.
 */
function sendeEinzelMail(string $empfaengerEmail, string $betreff, string $nachricht): bool
{
    $betreff = str_replace(["\r", "\n"], ' ', trim($betreff));
    $betreffKodiert = '=?UTF-8?B?' . base64_encode($betreff) . '?=';

    $headers = "From: " . MAIL_ABSENDER_NAME . " <" . MAIL_ABSENDER_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . MAIL_ABSENDER_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($empfaengerEmail, $betreffKodiert, $nachricht, $headers);
}

/**
 * Prueft eine IBAN auf gueltiges Format und korrekte Pruefziffer (Modulo 97
 * nach ISO 7064), ohne bcmath oder andere Erweiterungen zu benoetigen.
 */
function istGueltigeIban(string $iban): bool
{
    $iban = strtoupper(str_replace(' ', '', $iban));
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
        return false;
    }

    $umgestellt = substr($iban, 4) . substr($iban, 0, 4);
    $numerisch = '';
    foreach (str_split($umgestellt) as $zeichen) {
        $numerisch .= ctype_alpha($zeichen) ? (string) (ord($zeichen) - 55) : $zeichen;
    }

    $rest = 0;
    foreach (str_split($numerisch) as $ziffer) {
        $rest = ($rest * 10 + (int) $ziffer) % 97;
    }

    return $rest === 1;
}

/**
 * Erzeugt eine SEPA-Lastschrift-Sammeldatei im Format pain.008.001.02 zum
 * Hochladen im Online-Banking. $lastschriften ist eine Liste von Arrays mit
 * den Schluesseln: name, iban, bic (kann leer sein), betrag (float),
 * mandatsreferenz, mandatsdatum (Y-m-d), sequenztyp ('FRST'|'RCUR') und
 * verwendungszweck. Erst- (FRST) und Folgelastschriften (RCUR) muessen laut
 * Spezifikation in getrennten PmtInf-Bloecken stehen und werden hier
 * automatisch entsprechend gruppiert.
 */
function erzeugeSepaLastschriftDatei(string $faelligkeitsdatum, array $lastschriften): string
{
    $dok = new DOMDocument('1.0', 'UTF-8');
    $dok->formatOutput = true;

    $root = $dok->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.008.001.02', 'Document');
    $dok->appendChild($root);

    $init = $dok->createElement('CstmrDrctDbtInitn');
    $root->appendChild($init);

    $jetzt = new DateTime();
    $msgId = 'MSG' . $jetzt->format('YmdHis');
    $gesamtbetrag = array_sum(array_column($lastschriften, 'betrag'));

    $grpHdr = $dok->createElement('GrpHdr');
    $grpHdr->appendChild($dok->createElement('MsgId', $msgId));
    $grpHdr->appendChild($dok->createElement('CreDtTm', $jetzt->format('Y-m-d\TH:i:s')));
    $grpHdr->appendChild($dok->createElement('NbOfTxs', (string) count($lastschriften)));
    $grpHdr->appendChild($dok->createElement('CtrlSum', number_format($gesamtbetrag, 2, '.', '')));
    $initgPty = $dok->createElement('InitgPty');
    $initgPty->appendChild($dok->createElement('Nm', VEREIN_NAME));
    $grpHdr->appendChild($initgPty);
    $init->appendChild($grpHdr);

    $gruppen = ['FRST' => [], 'RCUR' => []];
    foreach ($lastschriften as $l) {
        $gruppen[$l['sequenztyp']][] = $l;
    }

    $blockNr = 0;
    foreach ($gruppen as $sequenztyp => $eintraege) {
        if (empty($eintraege)) {
            continue;
        }
        $blockNr++;
        $blockBetrag = array_sum(array_column($eintraege, 'betrag'));

        $pmtInf = $dok->createElement('PmtInf');
        $pmtInf->appendChild($dok->createElement('PmtInfId', $msgId . '-' . $blockNr));
        $pmtInf->appendChild($dok->createElement('PmtMtd', 'DD'));
        $pmtInf->appendChild($dok->createElement('BtchBookg', 'true'));
        $pmtInf->appendChild($dok->createElement('NbOfTxs', (string) count($eintraege)));
        $pmtInf->appendChild($dok->createElement('CtrlSum', number_format($blockBetrag, 2, '.', '')));

        $pmtTpInf = $dok->createElement('PmtTpInf');
        $svcLvl = $dok->createElement('SvcLvl');
        $svcLvl->appendChild($dok->createElement('Cd', 'SEPA'));
        $pmtTpInf->appendChild($svcLvl);
        $lclInstrm = $dok->createElement('LclInstrm');
        $lclInstrm->appendChild($dok->createElement('Cd', 'CORE'));
        $pmtTpInf->appendChild($lclInstrm);
        $pmtTpInf->appendChild($dok->createElement('SeqTp', $sequenztyp));
        $pmtInf->appendChild($pmtTpInf);

        $pmtInf->appendChild($dok->createElement('ReqdColltnDt', $faelligkeitsdatum));

        $cdtr = $dok->createElement('Cdtr');
        $cdtr->appendChild($dok->createElement('Nm', VEREIN_NAME));
        $pmtInf->appendChild($cdtr);

        $cdtrAcct = $dok->createElement('CdtrAcct');
        $cdtrAcctId = $dok->createElement('Id');
        $cdtrAcctId->appendChild($dok->createElement('IBAN', str_replace(' ', '', defined('VEREIN_IBAN') ? VEREIN_IBAN : '')));
        $cdtrAcct->appendChild($cdtrAcctId);
        $pmtInf->appendChild($cdtrAcct);

        $cdtrAgt = $dok->createElement('CdtrAgt');
        $cdtrFinInstnId = $dok->createElement('FinInstnId');
        $vereinBic = defined('VEREIN_BIC') ? VEREIN_BIC : '';
        if ($vereinBic !== '') {
            $cdtrFinInstnId->appendChild($dok->createElement('BIC', $vereinBic));
        } else {
            $othr = $dok->createElement('Othr');
            $othr->appendChild($dok->createElement('Id', 'NOTPROVIDED'));
            $cdtrFinInstnId->appendChild($othr);
        }
        $cdtrAgt->appendChild($cdtrFinInstnId);
        $pmtInf->appendChild($cdtrAgt);

        $pmtInf->appendChild($dok->createElement('ChrgBr', 'SLEV'));

        $cdtrSchmeId = $dok->createElement('CdtrSchmeId');
        $id = $dok->createElement('Id');
        $prvtId = $dok->createElement('PrvtId');
        $othrScheme = $dok->createElement('Othr');
        $othrScheme->appendChild($dok->createElement('Id', defined('SEPA_GLAEUBIGER_ID') ? SEPA_GLAEUBIGER_ID : ''));
        $schmeNm = $dok->createElement('SchmeNm');
        $schmeNm->appendChild($dok->createElement('Prtry', 'SEPA'));
        $othrScheme->appendChild($schmeNm);
        $prvtId->appendChild($othrScheme);
        $id->appendChild($prvtId);
        $cdtrSchmeId->appendChild($id);
        $pmtInf->appendChild($cdtrSchmeId);

        foreach ($eintraege as $index => $l) {
            $txInf = $dok->createElement('DrctDbtTxInf');

            $pmtId = $dok->createElement('PmtId');
            $pmtId->appendChild($dok->createElement('EndToEndId', $msgId . '-' . $blockNr . '-' . ($index + 1)));
            $txInf->appendChild($pmtId);

            $instdAmt = $dok->createElement('InstdAmt', number_format($l['betrag'], 2, '.', ''));
            $instdAmt->setAttribute('Ccy', 'EUR');
            $txInf->appendChild($instdAmt);

            $drctDbtTx = $dok->createElement('DrctDbtTx');
            $mndtRltdInf = $dok->createElement('MndtRltdInf');
            $mndtRltdInf->appendChild($dok->createElement('MndtId', $l['mandatsreferenz']));
            $mndtRltdInf->appendChild($dok->createElement('DtOfSgntr', $l['mandatsdatum']));
            $drctDbtTx->appendChild($mndtRltdInf);
            $txInf->appendChild($drctDbtTx);

            $dbtrAgt = $dok->createElement('DbtrAgt');
            $dbtrFinInstnId = $dok->createElement('FinInstnId');
            if (!empty($l['bic'])) {
                $dbtrFinInstnId->appendChild($dok->createElement('BIC', $l['bic']));
            } else {
                $othrDbtr = $dok->createElement('Othr');
                $othrDbtr->appendChild($dok->createElement('Id', 'NOTPROVIDED'));
                $dbtrFinInstnId->appendChild($othrDbtr);
            }
            $dbtrAgt->appendChild($dbtrFinInstnId);
            $txInf->appendChild($dbtrAgt);

            $dbtr = $dok->createElement('Dbtr');
            $dbtr->appendChild($dok->createElement('Nm', $l['name']));
            $txInf->appendChild($dbtr);

            $dbtrAcct = $dok->createElement('DbtrAcct');
            $dbtrAcctId = $dok->createElement('Id');
            $dbtrAcctId->appendChild($dok->createElement('IBAN', str_replace(' ', '', $l['iban'])));
            $dbtrAcct->appendChild($dbtrAcctId);
            $txInf->appendChild($dbtrAcct);

            if (!empty($l['verwendungszweck'])) {
                $rmtInf = $dok->createElement('RmtInf');
                $rmtInf->appendChild($dok->createElement('Ustrd', $l['verwendungszweck']));
                $txInf->appendChild($rmtInf);
            }

            $pmtInf->appendChild($txInf);
        }

        $init->appendChild($pmtInf);
    }

    return $dok->saveXML();
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
