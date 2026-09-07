<?php
declare(strict_types=1);

/**
 * Bootstrap-Skript: legt das allererste Vorstandsmitglied an.
 * Notwendig, weil ohne existierendes Vorstandsmitglied niemand über die
 * Mitgliederverwaltung ein Konto anlegen könnte (Henne-Ei-Problem).
 *
 * Legt dabei bewusst BEIDES an - einen Aufnahmeantrag (Status "angenommen")
 * und das Mitgliedskonto, miteinander verknüpft - genau wie es passiert,
 * wenn der Vorstand später einen normalen Antrag über die Weboberfläche
 * annimmt. So taucht auch das erste Vorstandsmitglied ganz normal in der
 * Anträge-Übersicht auf, inklusive Foto und dokumentierten Einverständnissen.
 *
 * Aufruf per SSH auf dem Server (oder lokal gegen die Datenbank):
 *   php scripts/create_mitglied.php
 *
 * Falls kein SSH-Zugang zum Hosting besteht, dieses Skript einmalig
 * temporär in htdocs/ hochladen, im Browser aufrufen (Eingabe per GET/POST
 * ergaenzen) und danach sofort wieder löschen - oder das Mitglied direkt
 * per SQL/phpMyAdmin anlegen und den Passwort-Hash lokal per
 * `php -r "echo password_hash('...', PASSWORD_DEFAULT);"` erzeugen.
 */

if (PHP_SAPI !== 'cli') {
    die('Dieses Skript ist nur für die Kommandozeile gedacht.');
}

require_once __DIR__ . '/../includes/db.php';

function frage(string $text): string
{
    echo $text;
    $eingabe = fgets(STDIN);
    return trim($eingabe === false ? '' : $eingabe);
}

$vorname = frage('Vorname: ');
$nachname = frage('Nachname: ');
$email = frage('E-Mail: ');
$geburtsdatum = frage('Geburtsdatum (JJJJ-MM-TT): ');
$geburtsort = frage('Geburtsort: ');
$adresse = frage('Straße und Hausnummer: ');
$plz = frage('PLZ: ');
$ort = frage('Ort: ');
$telefon = frage('Telefon: ');
$instagram = frage('Instagram-Benutzername (optional, ohne @, sonst leer lassen): ');
$instagram = $instagram !== '' ? ltrim($instagram, '@') : null;
$fotoPfad = frage('Pfad zu einem Foto (JPG/PNG/WebP) auf diesem Rechner: ');
$bildnutzung = strtolower(frage('Einverständnis zur Nutzung von Fotos/Videos für Social Media? (ja/nein): '));
$passwort = frage('Initiales Passwort (mind. 8 Zeichen): ');

if (strlen($passwort) < 8) {
    die("Abbruch: Passwort muss mindestens 8 Zeichen lang sein.\n");
}

if (!is_file($fotoPfad)) {
    die("Abbruch: Foto nicht gefunden unter: $fotoPfad\n");
}

$erlaubteTypen = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($fotoPfad);
if (!isset($erlaubteTypen[$mime])) {
    die("Abbruch: Foto muss JPG, PNG oder WebP sein.\n");
}

$zielOrdner = __DIR__ . '/../private/uploads/fotos/';
if (!is_dir($zielOrdner) && !mkdir($zielOrdner, 0750, true) && !is_dir($zielOrdner)) {
    die("Abbruch: Speicherort für Fotos konnte nicht angelegt werden.\n");
}
$fotoDateiname = bin2hex(random_bytes(16)) . '.' . $erlaubteTypen[$mime];
if (!copy($fotoPfad, $zielOrdner . $fotoDateiname)) {
    die("Abbruch: Foto konnte nicht kopiert werden.\n");
}

$pdo = getPdo();

$antragStmt = $pdo->prepare(
    'INSERT INTO antraege
        (vorname, nachname, geburtsdatum, geburtsort, strasse_hausnummer, plz, ort, telefon, email, instagram, foto_dateiname, einverstaendnis_satzung, einverstaendnis_datenschutz, einverstaendnis_bildnutzung, status)
     VALUES
        (:vorname, :nachname, :geburtsdatum, :geburtsort, :strasse_hausnummer, :plz, :ort, :telefon, :email, :instagram, :foto_dateiname, 1, 1, :bildnutzung, "angenommen")'
);
$antragStmt->execute([
    'vorname' => $vorname,
    'nachname' => $nachname,
    'geburtsdatum' => $geburtsdatum,
    'geburtsort' => $geburtsort,
    'strasse_hausnummer' => $adresse,
    'plz' => $plz,
    'ort' => $ort,
    'telefon' => $telefon,
    'email' => $email,
    'instagram' => $instagram,
    'foto_dateiname' => $fotoDateiname,
    'bildnutzung' => ($bildnutzung === 'ja' ? 1 : 0),
]);
$antragId = (int) $pdo->lastInsertId();

$mitgliedStmt = $pdo->prepare(
    'INSERT INTO mitglieder
        (antrag_id, vorname, nachname, geburtsdatum, geburtsort, strasse_hausnummer, plz, ort, telefon, email, instagram, foto_dateiname, rolle, passwort_hash, aktiv)
     VALUES
        (:antrag_id, :vorname, :nachname, :geburtsdatum, :geburtsort, :strasse_hausnummer, :plz, :ort, :telefon, :email, :instagram, :foto_dateiname, "vorstandsmitglied", :passwort_hash, 1)'
);
$mitgliedStmt->execute([
    'antrag_id' => $antragId,
    'vorname' => $vorname,
    'nachname' => $nachname,
    'geburtsdatum' => $geburtsdatum,
    'geburtsort' => $geburtsort,
    'strasse_hausnummer' => $adresse,
    'plz' => $plz,
    'ort' => $ort,
    'telefon' => $telefon,
    'email' => $email,
    'instagram' => $instagram,
    'foto_dateiname' => $fotoDateiname,
    'passwort_hash' => password_hash($passwort, PASSWORD_DEFAULT),
]);
$mitgliedId = (int) $pdo->lastInsertId();

$pdo->prepare('UPDATE antraege SET mitglied_id = :mitglied_id WHERE id = :id')
    ->execute(['mitglied_id' => $mitgliedId, 'id' => $antragId]);

echo "Vorstandsmitglied angelegt (ID $mitgliedId) mit zugehörigem Antrag (ID $antragId). Login unter /login.php möglich.\n";
