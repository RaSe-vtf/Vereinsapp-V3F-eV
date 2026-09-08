-- Vereinsapp - Datenbankschema
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

-- Kassenwart/Beitraege: feste Positionen (Mitgliedsbeitrag je Rolle, monatlich,
-- sowie Startpasskosten, jaehrlich). rolle = NULL + ist_startpass = 1 ist die
-- Startpass-Zeile; rolle gesetzt + ist_startpass = 0 ist der Mitgliedsbeitrag
-- der jeweiligen Rolle. Nur diese Positionen duerfen in den SEPA-Export.
CREATE TABLE IF NOT EXISTS beitragsposten (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bezeichnung VARCHAR(150) NOT NULL,
    betrag DECIMAL(10,2) NOT NULL,
    rolle ENUM('vollmitglied', 'trainingsmitglied', 'vorstandsmitglied', 'ehrenmitglied', 'foerdermitglied') NULL,
    ist_startpass TINYINT(1) NOT NULL DEFAULT 0,
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
ALTER TABLE beitragsposten ADD COLUMN IF NOT EXISTS ist_startpass TINYINT(1) NOT NULL DEFAULT 0 AFTER rolle;

-- Kassenbücher: hochgeladene Kontoauszüge (Vereinskonto) und die daraus
-- geparsten Einzelbuchungen. Kontostand/Einnahmen/Ausgaben ergeben sich
-- ausschließlich aus diesen Buchungen, es gibt bewusst keine manuelle
-- Eingabe von Beträgen beim Vereinskonto (Fehlerquelle).
CREATE TABLE IF NOT EXISTS kontoauszuege (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dateiname VARCHAR(255) NOT NULL,
    format ENUM('camt053', 'mt940') NOT NULL,
    jahr SMALLINT UNSIGNED NOT NULL,
    monat TINYINT UNSIGNED NOT NULL,
    anfangssaldo DECIMAL(10,2) NULL,
    endsaldo DECIMAL(10,2) NULL,
    hochgeladen_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jahr_monat (jahr, monat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kassenbericht: feste Kategorienliste fuer Einnahmen/Ausgaben, damit sich
-- Kontobewegungen und Barkasse-Buchungen fuer die Verteilungs-Diagramme
-- gruppieren lassen. Wird per PHP beim ersten Aufruf des Kassenberichts mit
-- Standardkategorien befuellt (kein SQL-Seed, siehe functions.php), der
-- Kassenwart kann dort weitere ergaenzen. "aktiv = 0" blendet eine Kategorie
-- nur aus der Auswahl aus, bereits zugeordnete Buchungen behalten sie.
CREATE TABLE IF NOT EXISTS kassenbericht_kategorien (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    typ ENUM('einnahme', 'ausgabe') NOT NULL,
    aktiv TINYINT(1) NOT NULL DEFAULT 1,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_typ (typ)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kontobewegungen (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    auszug_id INT UNSIGNED NOT NULL,
    buchungsdatum DATE NOT NULL,
    betrag DECIMAL(10,2) NOT NULL,
    verwendungszweck VARCHAR(500) NULL,
    beteiligter VARCHAR(200) NULL,
    kategorie_id INT UNSIGNED NULL,
    ist_bargeld_verdacht TINYINT(1) NOT NULL DEFAULT 0,
    in_barkasse_uebernommen TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_auszug_id (auszug_id),
    KEY idx_buchungsdatum (buchungsdatum),
    KEY idx_kategorie_id (kategorie_id),
    CONSTRAINT fk_kontobewegungen_auszug FOREIGN KEY (auszug_id) REFERENCES kontoauszuege (id) ON DELETE CASCADE,
    CONSTRAINT fk_kontobewegungen_kategorie FOREIGN KEY (kategorie_id) REFERENCES kassenbericht_kategorien (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Barkasse: Zugänge entweder als bestätigte Übernahme einer Kontobewegung
-- (Bargeldabhebung vom Vereinskonto) oder als manuelle Einnahme (z.B.
-- Bar-Spende, die nie über die Bank lief). Ausgaben immer manuell, mit
-- Pflicht-Beleg.
CREATE TABLE IF NOT EXISTS barkasse_buchungen (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    typ ENUM('einnahme_konto', 'einnahme_manuell', 'ausgabe') NOT NULL,
    datum DATE NOT NULL,
    betrag DECIMAL(10,2) NOT NULL,
    beschreibung VARCHAR(500) NULL,
    empfaenger VARCHAR(200) NULL,
    beleg_dateiname VARCHAR(255) NULL,
    kontobewegung_id INT UNSIGNED NULL,
    kategorie_id INT UNSIGNED NULL,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_datum (datum),
    KEY idx_kontobewegung_id (kontobewegung_id),
    KEY idx_kategorie_id (kategorie_id),
    CONSTRAINT fk_barkasse_kontobewegung FOREIGN KEY (kontobewegung_id) REFERENCES kontobewegungen (id) ON DELETE SET NULL,
    CONSTRAINT fk_barkasse_kategorie FOREIGN KEY (kategorie_id) REFERENCES kassenbericht_kategorien (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kassenbericht-Prognose: manuell erfasste Sonderposten je Haushaltsjahr
-- (= Kalenderjahr), die zur fortgeschriebenen Vorjahresbasis addiert werden.
CREATE TABLE IF NOT EXISTS budget_sonderposten (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    jahr SMALLINT UNSIGNED NOT NULL,
    bezeichnung VARCHAR(200) NOT NULL,
    betrag DECIMAL(10,2) NOT NULL,
    typ ENUM('einnahme', 'ausgabe') NOT NULL,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jahr (jahr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vereinsdokumente (Satzung, Ordnungen): Historie statt Ersetzen - jeder
-- Upload legt einen neuen Eintrag an. "Aktuell" wird je Kombination aus
-- Bezeichnung UND Original-Dateiname bestimmt (juengstes Datum gewinnt),
-- damit mehrere verschiedene Dateien unter derselben Bezeichnung (z.B.
-- "Vereinsordnungen" mit Wahlordnung.pdf, Beitragsordnung.pdf, ...) als
-- eigenstaendige Dokumente erhalten bleiben, statt sich gegenseitig als
-- "Historie" zu verdraengen - nur ein erneuter Upload mit demselben
-- Dateinamen gilt als neue Fassung desselben Dokuments. Aeltere Fassungen
-- bleiben erhalten und sind im Vorstandsbereich weiterhin herunterladbar,
-- bis sie dort geloescht werden.
CREATE TABLE IF NOT EXISTS vereinsdokumente (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bezeichnung VARCHAR(150) NOT NULL,
    dateiname VARCHAR(255) NOT NULL,
    original_dateiname VARCHAR(255) NULL,
    hochgeladen_von_id INT UNSIGNED NULL,
    hochgeladen_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bezeichnung (bezeichnung),
    CONSTRAINT fk_vereinsdokumente_mitglied FOREIGN KEY (hochgeladen_von_id) REFERENCES mitglieder (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE vereinsdokumente ADD COLUMN IF NOT EXISTS original_dateiname VARCHAR(255) NULL AFTER dateiname;
ALTER TABLE kontobewegungen ADD COLUMN IF NOT EXISTS kategorie_id INT UNSIGNED NULL AFTER beteiligter;
ALTER TABLE barkasse_buchungen ADD COLUMN IF NOT EXISTS kategorie_id INT UNSIGNED NULL AFTER empfaenger;
