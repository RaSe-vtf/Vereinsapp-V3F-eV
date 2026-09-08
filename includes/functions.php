<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Vereinsname mit geschuetzten Leerzeichen, damit er auf Bildschirmtext
 * nie umbricht und immer als ein zusammenhaengendes "Wort" erscheint.
 */
function vereinNameNowrap(): string
{
    return str_replace(' ', "\u{00A0}", VEREIN_NAME);
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

const SHIRT_GROESSEN = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

/**
 * Telefonnummer fuer das oeffentliche Sportlerprofil maskiert (nur die
 * letzten 4 Ziffern, zur WhatsApp-Zuordnung) - die vollstaendige Nummer
 * bleibt der Vereinsverwaltung vorbehalten.
 */
function maskiereTelefon(string $telefon): string
{
    $ziffern = preg_replace('/\D/', '', $telefon) ?? '';
    if (strlen($ziffern) < 4) {
        return '••••';
    }
    return '•••• ' . substr($ziffern, -4);
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

/**
 * Erkennt das Format eines Kontoauszugs (CAMT.053-XML oder MT940) und
 * parst ihn zu Anfangssaldo, Endsaldo und den einzelnen Buchungen. Deckt
 * die beiden gaengigen, bankunabhaengigen Standardformate ab - weicht eine
 * Bank in der Praxis davon ab (z.B. eigenes CSV-Format), wird das anhand
 * eines echten Beispiels gesondert ergaenzt.
 */
function parseKontoauszug(string $inhalt): array
{
    $getrimmt = ltrim($inhalt);
    if (str_starts_with($getrimmt, '<?xml') || str_starts_with($getrimmt, '<Document')) {
        return array_merge(['format' => 'camt053'], parseKontoauszugCamt053($inhalt));
    }
    if (preg_match('/^:20:/m', $inhalt)) {
        return array_merge(['format' => 'mt940'], parseKontoauszugMt940($inhalt));
    }
    throw new RuntimeException('Das Dateiformat konnte nicht erkannt werden (erwartet: CAMT.053-XML oder MT940).');
}

/**
 * Parst einen CAMT.053-Kontoauszug (ISO 20022 XML). Die Elemente tragen
 * i.d.R. keinen Namespace-Prefix, daher funktioniert getElementsByTagName()
 * hier ohne Namespace-Handling.
 */
function parseKontoauszugCamt053(string $inhalt): array
{
    $dom = new DOMDocument();
    $vorherigeEinstellung = libxml_use_internal_errors(true);
    $geladen = $dom->loadXML($inhalt);
    libxml_use_internal_errors($vorherigeEinstellung);
    if (!$geladen) {
        throw new RuntimeException('Die Datei konnte nicht als CAMT.053-XML gelesen werden.');
    }

    $liesBetrag = static function (DOMElement $knoten): ?float {
        $betragKnoten = $knoten->getElementsByTagName('Amt')->item(0);
        $indKnoten = $knoten->getElementsByTagName('CdtDbtInd')->item(0);
        if ($betragKnoten === null) {
            return null;
        }
        $betrag = (float) str_replace(',', '.', $betragKnoten->textContent);
        return ($indKnoten && trim($indKnoten->textContent) === 'DBIT') ? -$betrag : $betrag;
    };

    $anfangssaldo = null;
    $endsaldo = null;
    foreach ($dom->getElementsByTagName('Bal') as $bal) {
        $codeKnoten = $bal->getElementsByTagName('Cd')->item(0);
        $code = $codeKnoten ? trim($codeKnoten->textContent) : '';
        $betrag = $liesBetrag($bal);
        if ($betrag === null) {
            continue;
        }
        if ($code === 'OPBD') {
            $anfangssaldo = $betrag;
        } elseif ($code === 'CLBD') {
            $endsaldo = $betrag;
        }
    }

    $buchungen = [];
    foreach ($dom->getElementsByTagName('Ntry') as $ntry) {
        $betrag = $liesBetrag($ntry);
        if ($betrag === null) {
            continue;
        }

        $datum = null;
        $bookgDt = $ntry->getElementsByTagName('BookgDt')->item(0);
        if ($bookgDt) {
            $dtKnoten = $bookgDt->getElementsByTagName('Dt')->item(0) ?? $bookgDt->getElementsByTagName('DtTm')->item(0);
            if ($dtKnoten) {
                $datum = substr(trim($dtKnoten->textContent), 0, 10);
            }
        }
        if ($datum === null) {
            continue;
        }

        $verwendungszweckTeile = [];
        foreach ($ntry->getElementsByTagName('Ustrd') as $ustrd) {
            $verwendungszweckTeile[] = trim($ustrd->textContent);
        }

        $beteiligter = '';
        foreach (['Dbtr', 'Cdtr'] as $rollenTag) {
            foreach ($ntry->getElementsByTagName($rollenTag) as $partei) {
                $nameKnoten = $partei->getElementsByTagName('Nm')->item(0);
                if ($nameKnoten) {
                    $beteiligter = trim($nameKnoten->textContent);
                    break 2;
                }
            }
        }

        $buchungen[] = [
            'datum' => $datum,
            'betrag' => $betrag,
            'verwendungszweck' => implode(' ', array_filter($verwendungszweckTeile)),
            'beteiligter' => $beteiligter,
        ];
    }

    return ['anfangssaldo' => $anfangssaldo, 'endsaldo' => $endsaldo, 'buchungen' => $buchungen];
}

/**
 * Parst einen MT940-Kontoauszug (SWIFT-Feldformat). Deckt die gaengigen
 * Felder ab (:60F:/:60M: Anfangssaldo, :61: Buchungszeile, :86: Verwendungs-
 * zweck, :62F:/:62M: Endsaldo). Bank-Besonderheiten bei :61:/:86: werden bei
 * Bedarf spaeter anhand echter Auszuege ergaenzt.
 */
function parseKontoauszugMt940(string $inhalt): array
{
    $zeilen = preg_split('/\r\n|\r|\n/', $inhalt) ?: [];
    $anfangssaldo = null;
    $endsaldo = null;
    $buchungen = [];
    $aktuelleBuchung = null;

    $parseSaldoZeile = static function (string $wert): ?float {
        if (!preg_match('/^[CD]\d{6}[A-Z]{3}([\d,]+)$/', $wert, $treffer)) {
            return null;
        }
        $betrag = (float) str_replace(',', '.', $treffer[1]);
        return $wert[0] === 'D' ? -$betrag : $betrag;
    };

    foreach ($zeilen as $zeile) {
        if (preg_match('/^:60[FM]:(.+)$/', $zeile, $treffer)) {
            $anfangssaldo = $parseSaldoZeile(trim($treffer[1]));
        } elseif (preg_match('/^:62[FM]:(.+)$/', $zeile, $treffer)) {
            $endsaldo = $parseSaldoZeile(trim($treffer[1]));
        } elseif (preg_match('/^:61:(\d{6})(?:\d{4})?([CD]|R[CD])[A-Z]?([\d,]+)/', $zeile, $treffer)) {
            if ($aktuelleBuchung !== null) {
                $buchungen[] = $aktuelleBuchung;
            }
            $jjmmtt = $treffer[1];
            $datum = '20' . substr($jjmmtt, 0, 2) . '-' . substr($jjmmtt, 2, 2) . '-' . substr($jjmmtt, 4, 2);
            $betrag = (float) str_replace(',', '.', $treffer[3]);
            if (str_starts_with($treffer[2], 'D')) {
                $betrag = -$betrag;
            }
            $aktuelleBuchung = ['datum' => $datum, 'betrag' => $betrag, 'verwendungszweck' => '', 'beteiligter' => ''];
        } elseif (preg_match('/^:86:(.+)$/', $zeile, $treffer)) {
            if ($aktuelleBuchung !== null) {
                $aktuelleBuchung['verwendungszweck'] = trim($aktuelleBuchung['verwendungszweck'] . ' ' . $treffer[1]);
            }
        } elseif ($aktuelleBuchung !== null && $zeile !== '' && $zeile[0] !== ':') {
            $aktuelleBuchung['verwendungszweck'] = trim($aktuelleBuchung['verwendungszweck'] . ' ' . $zeile);
        }
    }
    if ($aktuelleBuchung !== null) {
        $buchungen[] = $aktuelleBuchung;
    }

    return ['anfangssaldo' => $anfangssaldo, 'endsaldo' => $endsaldo, 'buchungen' => $buchungen];
}

/**
 * Heuristik fuer moegliche Bargeldabhebungen (Geldautomat o.ae.) innerhalb
 * der Kontobewegungen, als Vorschlag fuer die Barkassen-Uebernahme. Der
 * Kassenwart bestaetigt jeden Vorschlag einzeln, es wird nichts automatisch
 * uebernommen.
 */
function istBargeldabhebungVerdacht(float $betrag, string $verwendungszweck): bool
{
    if ($betrag >= 0) {
        return false;
    }
    $text = mb_strtolower($verwendungszweck);
    foreach (['geldautomat', 'bargeldauszahlung', 'barauszahlung', 'auszahlung girocard', 'bargeld', 'ec-cash auszahlung'] as $stichwort) {
        if (str_contains($text, $stichwort)) {
            return true;
        }
    }
    return false;
}

/**
 * Validiert und speichert einen hochgeladenen Kontoauszug, parst ihn direkt
 * und gibt die geparsten Daten plus den gespeicherten Dateinamen zurueck.
 */
function handleKontoauszugUpload(array $file): array
{
    $maxBytes = 5 * 1024 * 1024; // 5 MB

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Bitte eine Auszugsdatei auswählen.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Beim Hochladen der Datei ist ein Fehler aufgetreten.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Ungültiger Datei-Upload.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Die Datei darf maximal 5 MB groß sein.');
    }

    $inhalt = file_get_contents($file['tmp_name']);
    if ($inhalt === false) {
        throw new RuntimeException('Die Datei konnte nicht gelesen werden.');
    }

    $geparst = parseKontoauszug($inhalt);

    $zielOrdner = __DIR__ . '/../private/uploads/kontoauszuege/';
    if (!is_dir($zielOrdner) && !mkdir($zielOrdner, 0750, true) && !is_dir($zielOrdner)) {
        throw new RuntimeException('Speicherort für Kontoauszüge konnte nicht angelegt werden.');
    }

    $endung = $geparst['format'] === 'camt053' ? 'xml' : 'sta';
    $dateiname = bin2hex(random_bytes(16)) . '.' . $endung;
    if (!copy($file['tmp_name'], $zielOrdner . $dateiname)) {
        throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
    }

    return array_merge($geparst, ['dateiname' => $dateiname]);
}

/**
 * Validiert und speichert einen hochgeladenen Barkassen-Beleg (Rechnung als
 * Foto oder PDF). Fotos werden wie Mitgliederfotos automatisch ausgerichtet
 * und komprimiert, PDFs unveraendert gespeichert.
 */
function handleBelegUpload(array $file): string
{
    $erlaubteTypen = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $maxBytes = 10 * 1024 * 1024; // 10 MB

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Bitte einen Beleg auswählen.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Beim Hochladen des Belegs ist ein Fehler aufgetreten.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Ungültiger Datei-Upload.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Der Beleg darf maximal 10 MB groß sein.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $erlaubteTypen, true)) {
        throw new RuntimeException('Bitte nur JPG-, PNG-, WebP-Bilder oder PDF-Dateien als Beleg hochladen.');
    }

    $zielOrdner = __DIR__ . '/../private/uploads/belege/';
    if (!is_dir($zielOrdner) && !mkdir($zielOrdner, 0750, true) && !is_dir($zielOrdner)) {
        throw new RuntimeException('Speicherort für Belege konnte nicht angelegt werden.');
    }

    if ($mime === 'application/pdf') {
        $dateiname = bin2hex(random_bytes(16)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $zielOrdner . $dateiname)) {
            throw new RuntimeException('Der Beleg konnte nicht gespeichert werden.');
        }
    } else {
        $dateiname = bin2hex(random_bytes(16)) . '.jpg';
        verarbeiteUndSpeichereFoto($file['tmp_name'], $mime, $zielOrdner . $dateiname);
    }

    return $dateiname;
}

/**
 * Validiert und speichert ein hochgeladenes Vereinsdokument (Satzung,
 * Ordnung). Es gibt bewusst keine Formatbeschraenkung beim Upload:
 * - PDF bleibt PDF.
 * - Bilder (JPG/PNG/WebP) werden automatisch in eine einseitige PDF
 *   gewandelt, damit sie sich wie ein Dokument oeffnen/verlinken lassen.
 * - Alle anderen Formate (z.B. Word/ODT) werden unveraendert im
 *   Originalformat gespeichert - eine echte Wandlung dafuer braeuchte ein
 *   externes Programm wie LibreOffice, das sich auf dem Webspace ohne
 *   SSH-Zugang nicht installieren laesst.
 */
function handleVereinsdokumentUpload(array $file): array
{
    $maxBytes = 20 * 1024 * 1024; // 20 MB

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Bitte eine Datei auswählen.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Beim Hochladen der Datei ist ein Fehler aufgetreten.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Ungültiger Datei-Upload.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Die Datei darf maximal 20 MB groß sein.');
    }

    $originalDateiname = mb_substr(basename((string) ($file['name'] ?? '')), 0, 255);

    $zielOrdner = __DIR__ . '/../private/uploads/vereinsdokumente/';
    if (!is_dir($zielOrdner) && !mkdir($zielOrdner, 0750, true) && !is_dir($zielOrdner)) {
        throw new RuntimeException('Speicherort für Vereinsdokumente konnte nicht angelegt werden.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $bildLader = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];

    if ($mime === 'application/pdf') {
        $dateiname = bin2hex(random_bytes(16)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $zielOrdner . $dateiname)) {
            throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
        }
        return ['dateiname' => $dateiname, 'original_dateiname' => $originalDateiname];
    }

    if (isset($bildLader[$mime])) {
        $dateiname = bin2hex(random_bytes(16)) . '.pdf';
        file_put_contents($zielOrdner . $dateiname, wandleBildInEinseitigePdf($file['tmp_name'], $mime));
        return ['dateiname' => $dateiname, 'original_dateiname' => $originalDateiname];
    }

    $endung = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!preg_match('/^[a-z0-9]{1,10}$/', $endung)) {
        $endung = 'bin';
    }
    $dateiname = bin2hex(random_bytes(16)) . '.' . $endung;
    if (!move_uploaded_file($file['tmp_name'], $zielOrdner . $dateiname)) {
        throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
    }

    return ['dateiname' => $dateiname, 'original_dateiname' => $originalDateiname];
}

/**
 * Wandelt ein hochgeladenes Bild in eine einseitige PDF-Datei (reines PHP,
 * ohne externe Programme). Richtet JPEGs anhand der EXIF-Ausrichtung aus
 * und verkleinert grosse Bilder wie beim Fotoupload.
 */
function wandleBildInEinseitigePdf(string $quellPfad, string $mime): string
{
    stelleAusreichendFotoSpeicherSicher();

    $lader = match ($mime) {
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        default => null,
    };
    $bild = $lader !== null ? @$lader($quellPfad) : false;
    if ($bild === false) {
        throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
    }

    if ($mime === 'image/jpeg') {
        $exif = @exif_read_data($quellPfad);
        if ($exif !== false && isset($exif['Orientation'])) {
            $bild = korrigiereFotoAusrichtung($bild, (int) $exif['Orientation']);
        }
    }

    $breite = imagesx($bild);
    $hoehe = imagesy($bild);
    if ($breite > FOTO_MAX_KANTE || $hoehe > FOTO_MAX_KANTE) {
        $faktor = FOTO_MAX_KANTE / max($breite, $hoehe);
        $neueBreite = (int) round($breite * $faktor);
        $neueHoehe = (int) round($hoehe * $faktor);
        $verkleinert = imagecreatetruecolor($neueBreite, $neueHoehe);
        $weiss = imagecolorallocate($verkleinert, 255, 255, 255);
        imagefill($verkleinert, 0, 0, $weiss);
        imagecopyresampled($verkleinert, $bild, 0, 0, 0, 0, $neueBreite, $neueHoehe, $breite, $hoehe);
        imagedestroy($bild);
        $bild = $verkleinert;
        $breite = $neueBreite;
        $hoehe = $neueHoehe;
    }

    ob_start();
    imagejpeg($bild, null, FOTO_JPEG_QUALITAET);
    $jpegDaten = (string) ob_get_clean();
    imagedestroy($bild);

    return baueEinseitigePdfMitJpeg($jpegDaten, $breite, $hoehe);
}

/**
 * Baut von Hand eine minimale, gueltige PDF-Datei mit genau einer Seite,
 * die ein einzelnes JPEG-Bild seitenfuellend enthaelt (DCTDecode-Filter -
 * JPEG-Bytes koennen unveraendert eingebettet werden, kein externes
 * PDF-Tool noetig).
 */
function baueEinseitigePdfMitJpeg(string $jpegDaten, int $breitePx, int $hoehePx): string
{
    $dpiAnnahme = 150.0;
    $breitePt = round($breitePx * 72 / $dpiAnnahme, 2);
    $hoehePt = round($hoehePx * 72 / $dpiAnnahme, 2);

    $inhaltStream = sprintf("q\n%.2F 0 0 %.2F 0 0 cm\n/Im0 Do\nQ", $breitePt, $hoehePt);

    $objekte = [];
    $objekte[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objekte[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objekte[3] = sprintf(
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /Im0 5 0 R >> >> /Contents 4 0 R >>',
        $breitePt,
        $hoehePt
    );
    $objekte[4] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($inhaltStream), $inhaltStream);
    $objekte[5] = sprintf(
        "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
        $breitePx,
        $hoehePx,
        strlen($jpegDaten),
        $jpegDaten
    );

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objekte as $nr => $inhalt) {
        $offsets[$nr] = strlen($pdf);
        $pdf .= "$nr 0 obj\n$inhalt\nendobj\n";
    }
    $xrefStart = strlen($pdf);
    $anzahl = count($objekte) + 1;
    $pdf .= "xref\n0 $anzahl\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objekte); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size $anzahl /Root 1 0 R >>\nstartxref\n$xrefStart\n%%EOF";

    return $pdf;
}
