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

// Jedes Mitglied loggt sich mit eigener E-Mail + eigenem Passwort ein
// (siehe Mitgliederverwaltung im Vorstandsbereich sowie scripts/create_mitglied.php
// zum Anlegen des allerersten Vorstandszugangs).
