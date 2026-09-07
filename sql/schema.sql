-- Vereinsapp V3F e.V. - Datenbankschema
-- Import ueber phpMyAdmin im all-inkl KAS oder per mysql-CLI

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
    rolle ENUM('vollmitglied', 'trainingsmitglied', 'vorstandsmitglied', 'ehrenmitglied', 'foerdermitglied') NOT NULL DEFAULT 'vollmitglied',
    ist_admin TINYINT(1) NOT NULL DEFAULT 0,
    passwort_hash VARCHAR(255) NULL,
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
    einverstaendnis_satzung TINYINT(1) NOT NULL DEFAULT 0,
    einverstaendnis_datenschutz TINYINT(1) NOT NULL DEFAULT 0,
    einverstaendnis_bildnutzung TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('neu', 'angenommen', 'abgelehnt') NOT NULL DEFAULT 'neu',
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mitglied_id (mitglied_id),
    CONSTRAINT fk_antraege_mitglied FOREIGN KEY (mitglied_id) REFERENCES mitglieder (id) ON DELETE SET NULL
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
