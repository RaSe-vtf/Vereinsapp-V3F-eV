-- Vereinsapp V3F e.V. - Datenbankschema
-- Import ueber phpMyAdmin im all-inkl KAS oder per mysql-CLI

CREATE TABLE IF NOT EXISTS antraege (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
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
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
