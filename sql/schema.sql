-- Vereinsapp V3F e.V. - Datenbankschema
-- Import ueber phpMyAdmin im all-inkl KAS oder per mysql-CLI
--
-- Diese Datei ist bei jeder Auslieferung vollstaendig und idempotent: sie
-- kann jederzeit erneut komplett importiert werden, egal ob die Datenbank
-- ganz neu ist oder schon einen aelteren Stand hat. Bereits vorhandene
-- Tabellen/Spalten werden uebersprungen, fehlende werden ergaenzt. Es muss
-- nie mehr manuell zwischen "ganze Datei" und "einzelnen Befehlen" gewaehlt
-- werden - immer einfach die ganze Datei importieren.

CREATE TABLE IF NOT EXISTS mitglieder (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    antrag_id INT UNSIGNED NULL,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    geburtsdatum DATE NOT NULL,
    geburtsort VARCHAR(150) NOT NULL,
    strasse_hausnummer VARCHAR(200) NOT NULL,
    plz VARCHAR(10) NOT NULL,
    ort VARCHAR(150) NOT NULL,
    telefon VARCHAR(50) NOT NULL,
    email VARCHAR(190) NOT NULL,
    instagram VARCHAR(100) NULL,
    foto_dateiname VARCHAR(255) NULL,
    shirt_groesse VARCHAR(10) NULL,
    portraet TEXT NULL,
    rolle ENUM('vollmitglied', 'trainingsmitglied', 'vorstandsmitglied', 'ehrenmitglied', 'foerdermitglied') NOT NULL DEFAULT 'vollmitglied',
    ist_admin TINYINT(1) NOT NULL DEFAULT 0,
    passwort_hash VARCHAR(255) NULL,
    sepa_kontoinhaber VARCHAR(200) NULL,
    sepa_iban VARCHAR(34) NULL,
    sepa_bic VARCHAR(11) NULL,
    sepa_mandatsreferenz VARCHAR(35) NULL,
    sepa_erteilt_am DATETIME NULL,
    sepa_erste_lastschrift_erfolgt TINYINT(1) NOT NULL DEFAULT 0,
    aktiv TINYINT(1) NOT NULL DEFAULT 1,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS antraege (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mitglied_id INT UNSIGNED NULL,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    geburtsdatum DATE NOT NULL,
    geburtsort VARCHAR(150) NOT NULL,
    strasse_hausnummer VARCHAR(200) NOT NULL,
    plz VARCHAR(10) NOT NULL,
    ort VARCHAR(150) NOT NULL,
    telefon VARCHAR(50) NOT NULL,
    email VARCHAR(190) NOT NULL,
    instagram VARCHAR(100) NULL,
    foto_dateiname VARCHAR(255) NOT NULL,
    shirt_groesse VARCHAR(10) NULL,
    portraet TEXT NULL,
    passwort_hash VARCHAR(255) NULL,
    einverstaendnis_satzung TINYINT(1) NOT NULL DEFAULT 0,
    einverstaendnis_datenschutz TINYINT(1) NOT NULL DEFAULT 0,
    einverstaendnis_bildnutzung TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('neu', 'angenommen', 'abgelehnt') NOT NULL DEFAULT 'neu',
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mitglied_id (mitglied_id),
    CONSTRAINT fk_antraege_mitglied FOREIGN KEY (mitglied_id) REFERENCES mitglieder (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kassenwart: frei konfigurierbare Beitragsposten (Mitgliedsbeitrag je Rolle,
-- weitere Kostenpunkte wie Startpassgebuehren). rolle = NULL bedeutet: gilt
-- fuer alle aktiven Mitglieder mit Mandat, unabhaengig von deren Rolle.
CREATE TABLE IF NOT EXISTS beitragsposten (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bezeichnung VARCHAR(150) NOT NULL,
    betrag DECIMAL(10,2) NOT NULL,
    rolle ENUM('vollmitglied', 'trainingsmitglied', 'vorstandsmitglied', 'ehrenmitglied', 'foerdermitglied') NULL,
    aktiv TINYINT(1) NOT NULL DEFAULT 1,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Angemeldet bleiben": pro Gerät/Browser ein Token, damit Mitglieder sich
-- nicht bei jedem Besuch erneut mit Passwort anmelden müssen.
CREATE TABLE IF NOT EXISTS anmelde_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mitglied_id INT UNSIGNED NOT NULL,
    selector CHAR(24) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zuletzt_verwendet_am DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_selector (selector),
    KEY idx_mitglied_id (mitglied_id),
    CONSTRAINT fk_anmelde_tokens_mitglied FOREIGN KEY (mitglied_id) REFERENCES mitglieder (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrationen fuer bereits bestehende Datenbanken: ergaenzt Spalten, die in
-- frueheren Versionen von mitglieder/antraege noch nicht existierten. Bei
-- einer ganz neuen Datenbank sind diese Spalten durch CREATE TABLE oben
-- bereits vorhanden, ADD COLUMN IF NOT EXISTS ist dann einfach ein No-Op.
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS ist_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER rolle;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS sepa_kontoinhaber VARCHAR(200) NULL AFTER passwort_hash;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS sepa_iban VARCHAR(34) NULL AFTER sepa_kontoinhaber;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS sepa_bic VARCHAR(11) NULL AFTER sepa_iban;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS sepa_mandatsreferenz VARCHAR(35) NULL AFTER sepa_bic;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS sepa_erteilt_am DATETIME NULL AFTER sepa_mandatsreferenz;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS sepa_erste_lastschrift_erfolgt TINYINT(1) NOT NULL DEFAULT 0 AFTER sepa_erteilt_am;
ALTER TABLE antraege ADD COLUMN IF NOT EXISTS passwort_hash VARCHAR(255) NULL AFTER foto_dateiname;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS shirt_groesse VARCHAR(10) NULL AFTER foto_dateiname;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS portraet TEXT NULL AFTER shirt_groesse;
ALTER TABLE antraege ADD COLUMN IF NOT EXISTS shirt_groesse VARCHAR(10) NULL AFTER foto_dateiname;
ALTER TABLE antraege ADD COLUMN IF NOT EXISTS portraet TEXT NULL AFTER shirt_groesse;
