<?php
/**
 * Vorlage fuer die Konfiguration.
 *
 * Kopiere diese Datei nach: private/config.php
 * (Diese Kopie NICHT ins Git-Repository einchecken - sie enthaelt Zugangsdaten!)
 *
 * Zugangsdaten fuer die Datenbank findest du im all-inkl KAS unter
 * "Datenbanken verwalten" (Host ist bei all-inkl in der Regel einfach "localhost").
 */

// --- Datenbank ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'dxxxxxx_verein');
define('DB_USER', 'dxxxxxx_verein');
define('DB_PASS', 'HIER_DEIN_DB_PASSWORT');

// --- App ---
define('VEREIN_NAME', 'Vonsys Tri Family e.V.');
define('APP_NAME', 'Vereinsapp');

// Jedes Mitglied loggt sich mit eigener E-Mail + eigenem Passwort ein
// (siehe Mitgliederverwaltung im Vorstandsbereich sowie scripts/create_mitglied.php
// zum Anlegen des allerersten Vorstandszugangs).

// --- E-Mail-Verteiler ---
// Absenderadresse fuer Rundmails an Mitglieder. Muss eine echte, zur Domain
// gehoerende Adresse sein, sonst landen die Mails leicht im Spam-Ordner.
define('MAIL_ABSENDER_EMAIL', 'verein@deine-domain.de');
define('MAIL_ABSENDER_NAME', 'Vonsys Tri Family e.V.');

// --- SEPA-Lastschriftmandat ---
// Gläubiger-Identifikationsnummer des Vereins (beim Bundesamt für Wirtschaft
// und Ausfuhrkontrolle zu beantragen: https://extranet.bundesbank.de/scpm/).
// Erscheint auf dem SEPA-Mandat, das neue Mitglieder beim ersten Login
// ausfüllen müssen (siehe htdocs/bereich/sepa_mandat.php).
define('SEPA_GLAEUBIGER_ID', 'DE00ZZZ00000000000');

// --- SEPA-Export (Vereinskonto) ---
// IBAN (und optional BIC) des Vereinskontos, auf das die per SEPA-Export
// erzeugten Lastschriften eingezogen werden (Kassenwart-Bereich). BIC kann
// leer bleiben, dann wird in der Exportdatei "NOTPROVIDED" eingetragen.
define('VEREIN_IBAN', 'DE00000000000000000000');
define('VEREIN_BIC', '');
