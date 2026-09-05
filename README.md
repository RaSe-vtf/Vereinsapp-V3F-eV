# Vereinsapp V3F e.V.

Webapp für V3F e.V.: Mitglieder stellen über ein Formular ihren Aufnahmeantrag,
der Vorstand nimmt ihn im Mitgliederbereich an, wodurch ein Mitgliedskonto mit
Rolle entsteht. Rollen steuern den Zugriff, z.B. sieht nur die Rolle
Vorstandsmitglied den Reiter "Vorstand".

Reines PHP + MySQL, ohne Node/Build-Schritt — läuft direkt auf all-inkl KAS
(oder jedem anderen klassischen PHP-Webhosting).

## Aktueller Stand

- Öffentlicher Aufnahmeantrag (`htdocs/antrag.php`) mit allen erfassten Daten,
  Foto-Upload und den Einverständniserklärungen (Satzung/Ordnungen,
  Kenntnisnahme Impressum & Datenschutz, optionale Freigabe für Social-Media-Fotos).
- Impressum & Datenschutzerklärung als Vorlage (öffentlich erreichbar, wie
  gesetzlich vorgeschrieben) — **muss noch mit den echten Vereinsdaten
  ausgefüllt werden**, siehe `htdocs/impressum.php` und `htdocs/datenschutz.php`.
- Jedes Mitglied hat ein eigenes Login (E-Mail + Passwort) und eine Rolle:
  Vollmitglied, Trainingsmitglied, Vorstandsmitglied, Ehrenmitglied,
  Fördermitglied.
- Mitgliederbereich (`htdocs/bereich/`) mit Tab-Navigation:
  - **Meine Daten**: für alle eingeloggten Mitglieder, eigene Daten ansehen,
    eigenes Passwort ändern.
  - **Vorstand**: nur sichtbar und aufrufbar für die Rolle Vorstandsmitglied.
    Enthält Aufnahmeanträge (ansehen, annehmen/ablehnen) und die
    Mitgliederverwaltung (Rolle ändern, Konto aktivieren/deaktivieren,
    Passwort neu vergeben).
- Nimmt der Vorstand einen Antrag an, wird automatisch ein Mitgliedskonto mit
  Rolle "Vollmitglied" angelegt (noch ohne Passwort). Der Vorstand vergibt in
  der Mitgliederverwaltung ein initiales Passwort, das einmalig angezeigt und
  manuell an das Mitglied weitergegeben wird (z.B. persönlich, Telefon,
  E-Mail außerhalb der App — ein automatischer E-Mail-Versand ist noch nicht
  eingebaut).
- Rollenänderungen und Deaktivierungen wirken sofort, auch bei bereits
  eingeloggten Sitzungen.
- Ein Vorstandsmitglied kann sich nicht selbst die Vorstandsrolle entziehen
  oder das eigene Konto deaktivieren (Schutz vor versehentlichem Aussperren).

## Projektstruktur

```
htdocs/                     -> Dieser Ordner wird als Dokumentenstamm der Domain eingerichtet
  index.php
  antrag.php                -> öffentlicher Aufnahmeantrag
  antrag_erfolg.php
  impressum.php
  datenschutz.php
  login.php / logout.php    -> Mitglieder-Login
  bereich/                  -> eingeloggter Bereich (alle Mitglieder)
    index.php               -> "Meine Daten" + Passwort ändern
    foto.php                -> liefert Fotos aus (eigenes Foto oder, für Vorstand, alle)
    vorstand/                -> nur Rolle Vorstandsmitglied
      antraege.php           -> Aufnahmeanträge verwalten
      antrag_ansehen.php
      mitglieder.php         -> Mitgliederverwaltung: Rolle, Status, Passwort
      mitglied_ansehen.php
  assets/                   -> CSS, Logo

includes/          -> gemeinsamer PHP-Code (liegt bewusst AUSSERHALB von htdocs)
private/           -> Konfiguration + hochgeladene Fotos (liegt AUSSERHALB von htdocs)
  config.php       -> wird lokal erstellt, nicht Teil des Repos
  uploads/fotos/   -> gespeicherte Mitgliederfotos

sql/schema.sql        -> Datenbank-Struktur zum Import (Tabellen: mitglieder, antraege)
scripts/create_mitglied.php -> Bootstrap: legt das allererste Vorstandsmitglied an
config.example.php    -> Vorlage für private/config.php
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
2. **Tabellen importieren**: `sql/schema.sql` über phpMyAdmin (im KAS
   verlinkt) in die neu angelegte Datenbank importieren.
3. **Konfiguration anlegen**: `config.example.php` nach `private/config.php`
   kopieren und ausfüllen (Datenbank-Zugangsdaten, Vereinsname).
4. **Dateien hochladen**: Das komplette Projekt per FTP/SFTP hochladen.
   Im KAS unter "Domains verwalten" das Web-Verzeichnis der Domain/Subdomain
   auf den Ordner `htdocs/` dieses Projekts setzen (nicht auf den
   Projekt-Hauptordner!).
5. **PHP-Version prüfen**: Im KAS unter "PHP-Einstellungen" mindestens PHP 8.0
   auswählen.
6. **Ersten Vorstandszugang anlegen** (einmalig, Henne-Ei-Problem: ohne
   Vorstandsmitglied kann niemand über die App selbst eines anlegen):
   - Mit SSH-Zugriff auf den Server: `php scripts/create_mitglied.php`
     ausführen und die Abfragen beantworten.
   - Ohne SSH-Zugriff: das Mitglied direkt per phpMyAdmin in die Tabelle
     `mitglieder` eintragen, mit `rolle = 'vorstandsmitglied'` und einem
     Passwort-Hash, den du lokal per
     `php -r "echo password_hash('DeinPasswort', PASSWORD_DEFAULT);"` erzeugst.
7. Aufrufen und testen: `https://deine-domain.de/` zeigt die Startseite,
   `.../login.php` das Mitglieder-Login.

## Rechtliches noch offen

- `htdocs/impressum.php` und `htdocs/datenschutz.php` enthalten Platzhalter
  (`[Vereinsname]`, `[Anschrift]`, usw.) und müssen vom Vorstand ausgefüllt
  und im Zweifel rechtlich geprüft werden, bevor die App live geht.

## Noch nicht eingebaut

- Automatischer E-Mail-Versand (z.B. Zugangsdaten direkt per Mail an neue
  Mitglieder, "Passwort vergessen"-Funktion). Aktuell übergibt der Vorstand
  das initiale Passwort manuell.
- Bearbeiten der eigenen Stammdaten durch Mitglieder selbst (aktuell nur
  Ansicht, Änderungen laufen über den Vorstand).

## Lokal testen

Mit installiertem PHP (>= 8.0) und MySQL/MariaDB:

```
php -S localhost:8000 -t htdocs
```

Datenbank vorher wie oben beschrieben anlegen, `private/config.php` mit
lokalen Zugangsdaten befüllen und mit `php scripts/create_mitglied.php`
den ersten Vorstandszugang anlegen.
