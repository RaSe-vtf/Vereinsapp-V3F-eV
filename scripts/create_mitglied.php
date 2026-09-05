<?php
declare(strict_types=1);

/**
 * Bootstrap-Skript: legt das allererste Vorstandsmitglied an.
 * Notwendig, weil ohne existierendes Vorstandsmitglied niemand über die
 * Mitgliederverwaltung ein Konto anlegen könnte (Henne-Ei-Problem).
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
$passwort = frage('Initiales Passwort (mind. 8 Zeichen): ');

if (strlen($passwort) < 8) {
    die("Abbruch: Passwort muss mindestens 8 Zeichen lang sein.\n");
}

$stmt = getPdo()->prepare(
    'INSERT INTO mitglieder
        (vorname, nachname, geburtsdatum, geburtsort, strasse_hausnummer, plz, ort, telefon, email, rolle, passwort_hash, aktiv)
     VALUES
        (:vorname, :nachname, :geburtsdatum, :geburtsort, :strasse_hausnummer, :plz, :ort, :telefon, :email, "vorstandsmitglied", :passwort_hash, 1)'
);
$stmt->execute([
    'vorname' => $vorname,
    'nachname' => $nachname,
    'geburtsdatum' => $geburtsdatum,
    'geburtsort' => $geburtsort,
    'strasse_hausnummer' => $adresse,
    'plz' => $plz,
    'ort' => $ort,
    'telefon' => $telefon,
    'email' => $email,
    'passwort_hash' => password_hash($passwort, PASSWORD_DEFAULT),
]);

echo 'Vorstandsmitglied angelegt (ID ' . getPdo()->lastInsertId() . "). Login unter /login.php möglich.\n";
