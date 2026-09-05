# Vereinsapp V3F e.V.

Webapp für V3F e.V.: Mitglieder stellen über ein Formular ihren Aufnahmeantrag,
der Vorstand sieht eingegangene Anträge in einem passwortgeschützten Bereich.

Reines PHP + MySQL, ohne Node/Build-Schritt — läuft direkt auf all-inkl KAS
(oder jedem anderen klassischen PHP-Webhosting).

## Aktueller Stand (Phase 1 / Demo)

- Öffentlicher Aufnahmeantrag (`htdocs/antrag.php`) mit allen erfassten Daten,
  Foto-Upload und den Einverständniserklärungen (Satzung/Ordnungen,
  Kenntnisnahme Impressum & Datenschutz, optionale Freigabe für Social-Media-Fotos).
- Impressum & Datenschutzerklärung als Vorlage (öffentlich erreichbar, wie
  gesetzlich vorgeschrieben) — **muss noch mit den echten Vereinsdaten
  ausgefüllt werden**, siehe `htdocs/impressum.php` und `htdocs/datenschutz.php`.
- Vorstandsbereich (`htdocs/admin/`) mit einfachem Passwortschutz: Anträge
  ansehen, Foto ansehen, Status setzen (neu/angenommen/abgelehnt).
- **Noch kein** vollständiges Mitglieder-Login-System — das ist für eine
  spätere Ausbaustufe vorgesehen (laut Absprache erstmal Demo ohne Login).

## Projektstruktur

```
htdocs/            -> Dieser Ordner wird als Dokumentenstamm der Domain eingerichtet
  index.php
  antrag.php
  antrag_erfolg.php
  impressum.php
  datenschutz.php
  admin/           -> Vorstandsbereich, passwortgeschützt
  assets/          -> CSS, Logo

includes/          -> gemeinsamer PHP-Code (liegt bewusst AUSSERHALB von htdocs)
private/           -> Konfiguration + hochgeladene Fotos (liegt AUSSERHALB von htdocs)
  config.php       -> wird lokal erstellt, nicht Teil des Repos
  uploads/fotos/   -> gespeicherte Mitgliederfotos

sql/schema.sql     -> Datenbank-Struktur zum Import
config.example.php -> Vorlage für private/config.php
```

Wichtig: Nur `htdocs/` soll öffentlich über den Webserver erreichbar sein.
`includes/` und `private/` liegen eine Ebene höher und sind dadurch – sofern
das Domain-Verzeichnis in KAS korrekt auf `htdocs/` zeigt – von außen gar
nicht erreichbar. Als zusätzliche Absicherung liegt trotzdem eine
`.htaccess` mit `Require all denied` in `private/`.

## Einrichtung auf all-inkl KAS

1. **Datenbank anlegen**: Im KAS unter "Datenbanken verwalten" eine neue
   MySQL-Datenbank anlegen und dir Datenbankname, Benutzer und Passwort
   notieren.
2. **Tabelle importieren**: `sql/schema.sql` über phpMyAdmin (im KAS
   verlinkt) in die neu angelegte Datenbank importieren.
3. **Konfiguration anlegen**: `config.example.php` nach `private/config.php`
   kopieren und ausfüllen (Datenbank-Zugangsdaten, Vereinsname, Admin-Passwort).
   Admin-Passwort-Hash erzeugen:
   ```
   php -r "echo password_hash('DeinPasswort', PASSWORD_DEFAULT), PHP_EOL;"
   ```
   Das Ergebnis in `ADMIN_PASSWORD_HASH` eintragen.
4. **Dateien hochladen**: Das komplette Projekt per FTP/SFTP hochladen.
   Im KAS unter "Domains verwalten" das Web-Verzeichnis der Domain/Subdomain
   auf den Ordner `htdocs/` dieses Projekts setzen (nicht auf den
   Projekt-Hauptordner!).
5. **PHP-Version prüfen**: Im KAS unter "PHP-Einstellungen" mindestens PHP 8.0
   auswählen.
6. Aufrufen und testen: `https://deine-domain.de/` zeigt die Startseite,
   `.../admin/login.php` den Vorstandsbereich.

## Rechtliches noch offen

- `htdocs/impressum.php` und `htdocs/datenschutz.php` enthalten Platzhalter
  (`[Vereinsname]`, `[Anschrift]`, usw.) und müssen vom Vorstand ausgefüllt
  und im Zweifel rechtlich geprüft werden, bevor die App live geht.

## Lokal testen

Mit installiertem PHP (>= 8.0) und MySQL/MariaDB:

```
php -S localhost:8000 -t htdocs
```

Datenbank vorher wie oben beschrieben anlegen und `private/config.php`
mit lokalen Zugangsdaten befüllen.
