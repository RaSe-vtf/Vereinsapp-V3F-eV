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
    vorstandsamt ENUM('vorsitz', 'stellv_vorsitz', 'kassenwart', 'beisitzer') NULL,
    ist_admin TINYINT(1) NOT NULL DEFAULT 0,
    passwort_hash VARCHAR(255) NULL,
    sepa_kontoinhaber VARCHAR(200) NULL,
    sepa_iban VARCHAR(34) NULL,
    sepa_bic VARCHAR(11) NULL,
    sepa_mandatsreferenz VARCHAR(35) NULL,
    sepa_erteilt_am DATETIME NULL,
    sepa_erste_lastschrift_erfolgt TINYINT(1) NOT NULL DEFAULT 0,
    aktiv TINYINT(1) NOT NULL DEFAULT 1,
    kuendigung_eingegangen_am DATE NULL,
    austrittsdatum DATE NULL,
    kuendigungsgrund TEXT NULL,
    ausgetreten_am DATETIME NULL,
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
    gewuenschte_rolle ENUM('vollmitglied', 'trainingsmitglied', 'foerdermitglied') NOT NULL DEFAULT 'vollmitglied',
    vertreter_name VARCHAR(200) NULL,
    vertreter_anschrift VARCHAR(300) NULL,
    einverstaendnis_vertreter TINYINT(1) NOT NULL DEFAULT 0,
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
    auszugsnummer SMALLINT UNSIGNED NULL,
    anfangssaldo DECIMAL(10,2) NULL,
    endsaldo DECIMAL(10,2) NULL,
    hochgeladen_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jahr_monat (jahr, monat),
    UNIQUE KEY uniq_jahr_auszugsnummer (jahr, auszugsnummer)
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

-- Lernende Zuordnungsregeln fuer die automatische Kategorisierung: ein
-- Stichwort (typischerweise der Beteiligte/Empfaenger einer Buchung) wird
-- beim manuellen Kategorisieren automatisch gelernt/aktualisiert und beim
-- naechsten Auftreten (Kontoauszug-Import, Barkasse-Erfassung, oder als
-- Nachtrag fuer bereits vorhandene unkategorisierte Buchungen) automatisch
-- angewendet - bleibt weiterhin manuell aenderbar.
CREATE TABLE IF NOT EXISTS kassenbericht_regeln (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    stichwort VARCHAR(190) NOT NULL,
    kategorie_id INT UNSIGNED NOT NULL,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_stichwort (stichwort),
    CONSTRAINT fk_kassenbericht_regeln_kategorie FOREIGN KEY (kategorie_id) REFERENCES kassenbericht_kategorien (id) ON DELETE CASCADE
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

-- Loeschprotokoll fuer die Barkasse: dauerhafte, unveraenderliche Dokumentation
-- jeder Loeschung (wer, wann, was genau, warum) - die App fuehrt auf dieser
-- Tabelle nie ein UPDATE oder DELETE aus, nur INSERT. Der geloeschte
-- Datensatz selbst ist danach weg, daher haelt "beschreibung" eine Kopie
-- der wichtigsten Felder fest.
CREATE TABLE IF NOT EXISTS finanz_loeschprotokoll (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    geloescht_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    mitglied_id INT UNSIGNED NULL,
    quelle VARCHAR(50) NOT NULL,
    datensatz_id INT UNSIGNED NOT NULL,
    beschreibung TEXT NOT NULL,
    grund TEXT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_geloescht_am (geloescht_am),
    CONSTRAINT fk_finanz_loeschprotokoll_mitglied FOREIGN KEY (mitglied_id) REFERENCES mitglieder (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE kontoauszuege ADD COLUMN IF NOT EXISTS auszugsnummer SMALLINT UNSIGNED NULL AFTER monat;
ALTER TABLE kontoauszuege ADD UNIQUE INDEX IF NOT EXISTS uniq_jahr_auszugsnummer (jahr, auszugsnummer);

-- Notizbuch: freie Notizseiten der Geschaeftsstelle (Ueberschrift + Freitext,
-- z.B. auch per Diktierfunktion eingetippt). Jede Zeile ist eine eigene Seite.
CREATE TABLE IF NOT EXISTS notizen (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titel VARCHAR(200) NOT NULL,
    inhalt LONGTEXT NOT NULL,
    mitglied_id INT UNSIGNED NULL,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aktualisiert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_aktualisiert_am (aktualisiert_am),
    CONSTRAINT fk_notizen_mitglied FOREIGN KEY (mitglied_id) REFERENCES mitglieder (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bilder je Notizseite. Beim Loeschen einer Notiz werden die Bild-Zeilen
-- per ON DELETE CASCADE automatisch mitgeloescht - die App raeumt zusaetzlich
-- die zugehoerigen Dateien in private/uploads/notizen/ auf.
CREATE TABLE IF NOT EXISTS notiz_bilder (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    notiz_id INT UNSIGNED NOT NULL,
    dateiname VARCHAR(255) NOT NULL,
    hochgeladen_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notiz_id (notiz_id),
    CONSTRAINT fk_notiz_bilder_notiz FOREIGN KEY (notiz_id) REFERENCES notizen (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aufnahmeantrag: gewuenschte Mitgliedschaftsart sowie Angaben zum
-- gesetzlichen Vertreter bei minderjaehrigen Antragstellern (§ 5 Abs. 1
-- der Satzung).
ALTER TABLE antraege ADD COLUMN IF NOT EXISTS gewuenschte_rolle ENUM('vollmitglied', 'trainingsmitglied', 'foerdermitglied') NOT NULL DEFAULT 'vollmitglied' AFTER passwort_hash;
ALTER TABLE antraege ADD COLUMN IF NOT EXISTS vertreter_name VARCHAR(200) NULL AFTER gewuenschte_rolle;
ALTER TABLE antraege ADD COLUMN IF NOT EXISTS vertreter_anschrift VARCHAR(300) NULL AFTER vertreter_name;
ALTER TABLE antraege ADD COLUMN IF NOT EXISTS einverstaendnis_vertreter TINYINT(1) NOT NULL DEFAULT 0 AFTER vertreter_anschrift;

-- Mitglieder-Austritt (Kuendigung): austrittsdatum wird automatisch nach
-- § 6 Abs. 2 der Satzung berechnet (Frist von sechs Wochen zum Quartalsende)
-- und ist nicht frei eingebbar - siehe berechneAustrittsdatumNachSatzung()
-- in includes/functions.php. ausgetreten_am wird gesetzt, sobald die
-- automatische Deaktivierung (Seitenaufruf oder Cronjob) tatsaechlich
-- stattgefunden hat.
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS kuendigung_eingegangen_am DATE NULL AFTER aktiv;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS austrittsdatum DATE NULL AFTER kuendigung_eingegangen_am;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS kuendigungsgrund TEXT NULL AFTER austrittsdatum;
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS ausgetreten_am DATETIME NULL AFTER kuendigungsgrund;

-- Vorstandsamt (§ 11 Abs. 2/3 der Satzung): unabhaengig vom Mitgliedschafts-
-- status (rolle) - ein Vorstandsmitglied kann eines der drei Aemter des
-- geschaeftsfuehrenden Vorstands (Vorsitz, Kassenwart) mit Vertretungsmacht
-- nach § 26 BGB innehaben, oder als Beisitzer/in dem erweiterten Vorstand
-- ohne Vertretungsmacht angehoeren. Jedes der drei Hauptaemter darf laut
-- Satzung nur einmal vergeben sein - das setzt die App durch (siehe
-- mitglieder.php, Aktion "amt_aendern").
ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS vorstandsamt ENUM('vorsitz', 'stellv_vorsitz', 'kassenwart', 'beisitzer') NULL AFTER rolle;
