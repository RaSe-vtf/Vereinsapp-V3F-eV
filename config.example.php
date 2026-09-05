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
define('VEREIN_NAME', 'V3F e.V.');
define('APP_NAME', 'Vereinsapp V3F');

// --- Admin-Zugang (Vorstand) ---
// Passwort-Hash erzeugen, z.B. per Terminal:
//   php -r "echo password_hash('DeinPasswort', PASSWORD_DEFAULT), PHP_EOL;"
// und das Ergebnis hier eintragen.
define('ADMIN_PASSWORD_HASH', '$2y$10$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

// --- Sonstiges ---
// Zufaelligen, langen String eintragen (fuer Session-/CSRF-Sicherheit).
define('APP_SECRET', 'BITTE_DURCH_ZUFAELLIGEN_STRING_ERSETZEN');
