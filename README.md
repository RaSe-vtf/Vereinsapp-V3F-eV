# Vereinsapp V3F e.V.

Webapp für V3F e.V.: Mitglieder stellen über ein Formular ihren Aufnahmeantrag,
der Vorstand nimmt ihn im Mitgliederbereich an, wodurch ein Mitgliedskonto mit
Rolle entsteht. Rollen steuern den Zugriff, z.B. sieht nur die Rolle
Vorstandsmitglied den Reiter "Geschäftsstelle".

Reines PHP + MySQL, ohne Node/Build-Schritt — läuft direkt auf all-inkl KAS
(oder jedem anderen klassischen PHP-Webhosting).

## Aktueller Stand

- **Startseite** (`htdocs/index.php`): Willkommenstext, direkt darunter das
  Mitglieder-Login (E-Mail + Passwort), darunter der Aufnahmeantrag-Bereich
  mit erklärendem Text und Button. Die eigenständige Seite `htdocs/login.php`
  bleibt technisch bestehen (z.B. als Ziel, wenn man ohne Login eine
  geschützte Seite aufruft), wird aber von der Startseite nicht mehr
  gesondert verlinkt.
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
  - **Meine Daten**: für alle eingeloggten Mitglieder, eigene Stammdaten
    (Name, Geburtsdatum/-ort, Adresse, Telefon, E-Mail, Instagram, Foto)
    selbst ändern bzw. ergänzen und eigenes Passwort ändern. Pflichtfelder
    bleiben Pflicht (gleiche Validierung wie beim Aufnahmeantrag), die
    E-Mail-Adresse wird auf Eindeutigkeit geprüft. Rolle, Kontostatus und
    Passwort-Reset bleiben Sache des Vorstands. Widerruf der
    Bildnutzungs-Einwilligung läuft bewusst nicht über einen Schalter in
    der App, sondern per E-Mail an den Vorstand (siehe Datenschutzerklärung).
  - **Geschäftsstelle**: nur sichtbar und aufrufbar für die Rolle
    Vorstandsmitglied (Reiter heißt bewusst nicht "Vorstand", um Bereich
    und Personen-Rolle sprachlich zu trennen). Enthält Aufnahmeanträge
    (ansehen, annehmen/ablehnen) und die Mitgliederverwaltung (Rolle ändern,
    Konto aktivieren/deaktivieren, mit Profilbild-Miniaturansicht und
    Nachname als Link zum Datenblatt) sowie den E-Mail-Verteiler.
    Konto löschen und Passwort zurücksetzen sind in den Admin-Bereich
    umgezogen (siehe unten).
  - **Admin**: nur sichtbar und aufrufbar für Mitglieder mit dem
    Admin-Flag (`ist_admin`, unabhängig von der Rolle — z.B. kann ein
    Vorstandsmitglied zusätzlich Admin sein). Enthält:
    - **Konten**: Passwort zurücksetzen, Konto endgültig löschen und
      Admin-Rechte an- bzw. abschalten. Ein Admin kann sich weder selbst
      löschen noch sich selbst das Admin-Recht entziehen (Schutz vor
      Aussperren) — das kann nur ein anderer Admin.
    - **Bilder**: Übersicht aller aktuell verwendeten Fotos (aus
      Aufnahmeanträgen und Mitgliederkonten) mit Verwendung, Abmessungen
      und Dateigröße. Bilder über 1600px an der längsten Kante gelten als
      "zu groß" und lassen sich einzeln oder alle zusammen per Button neu
      verarbeiten (gleiche Verkleinerung/Kompression wie beim Hochladen).
      Nach dem Verkleinern zeigt eine Meldung, wie viele Bilder geprüft,
      wie viele tatsächlich verkleinert und wie viel Speicherplatz dadurch
      eingespart wurde. Gedacht u.a. für Altbestände, die vor Einführung
      der automatischen Verkleinerung z.B. per FTP/phpMyAdmin eingespielt
      wurden.
- Nimmt der Vorstand einen Antrag an, wird automatisch ein Mitgliedskonto mit
  Rolle "Vollmitglied" angelegt (noch ohne Passwort). Der Vorstand vergibt in
  der Mitgliederverwaltung ein initiales Passwort, das einmalig angezeigt und
  manuell an das Mitglied weitergegeben wird (z.B. persönlich, Telefon,
  E-Mail außerhalb der App — ein automatischer E-Mail-Versand ist noch nicht
  eingebaut).
- Rollenänderungen und Deaktivierungen wirken sofort, auch bei bereits
  eingeloggten Sitzungen.
- Ein Vorstandsmitglied kann sich nicht selbst die Vorstandsrolle entziehen,
  das eigene Konto deaktivieren oder löschen (Schutz vor versehentlichem
  Aussperren).
- **Mitglied löschen** (Admin → Konten): entfernt das Mitgliedskonto
  endgültig aus der Datenbank (Login funktioniert danach nicht mehr). Der
  ursprüngliche Aufnahmeantrag bleibt als historischer Datensatz erhalten,
  verliert aber die Verknüpfung zum Konto.
- **E-Mail-Verteiler** (`.../bereich/vorstand/verteiler.php`): Rundmail an
  alle aktiven Mitglieder oder gezielt nach Rolle, verschickt per Bcc (die
  Mitglieder sehen die E-Mail-Adressen der anderen Empfänger nicht). Nutzt
  die native PHP-`mail()`-Funktion, wie sie auf all-inkl KAS standardmäßig
  zur Verfügung steht — siehe Hinweis zu Absenderadresse/Spam weiter unten.
- **Angemeldet bleiben**: Nach dem Login bleibt man dauerhaft eingeloggt,
  auch nach Schließen des Browsers oder auf einem neuen Gerätebesuch nach
  Monaten — kein wiederholtes Passwort-Eintippen nötig. Technisch über ein
  langlebiges, rotierendes Auto-Login-Token (Tabelle `anmelde_tokens`), das
  serverseitig geprüft wird. Explizites Abmelden, Deaktivieren/Löschen eines
  Kontos durch den Vorstand sowie Passwort-Änderung/-Reset beenden diesen
  Auto-Login sofort (siehe `includes/auth.php`).
- **App-Icon fürs Home-Bildschirm**: Favicon, Apple-Touch-Icon (180×180) und
  ein Web-App-Manifest (`htdocs/manifest.json`) sind eingerichtet, alle aus
  `htdocs/assets/img/logo.jpg` erzeugt. Fügt jemand die Seite auf dem Handy
  zum Home-Bildschirm hinzu, erscheint das echte App-Icon statt eines
  Seiten-Screenshots. Wird das Logo künftig ausgetauscht, müssen die
  generierten Icon-Dateien (`favicon-16/32.png`, `apple-touch-icon.png`,
  `icon-192/512.png`) neu erzeugt werden.
- **Foto-Verarbeitung beim Hochladen** (`verarbeiteUndSpeichereFoto()` in
  `includes/functions.php`, genutzt von Aufnahmeantrag, "Meine Daten" und
  dem Bootstrap-Skript): Fotos werden anhand der EXIF-Kameraausrichtung
  automatisch richtig gedreht und auf maximal 1600px an der längsten Kante
  verkleinert, als JPEG mit Qualität 82 gespeichert. Verhindert, dass
  unbearbeitete Handyfotos (oft mehrere MB) die Seite langsam machen oder
  seitlich/auf dem Kopf angezeigt werden.
- **Foto-Lupe**: Ein Klick auf ein Profilbild (Mitgliederliste, Datenblatt,
  "Meine Daten", Antrags-Ansicht) öffnet es vergrößert in einem Overlay
  (`htdocs/assets/js/lightbox.js`, ohne externe Abhängigkeiten).

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
      mitglieder.php         -> Mitgliederverwaltung: Rolle, Status
      mitglied_ansehen.php
      verteiler.php          -> E-Mail-Verteiler (Rundmail per Bcc)
    admin/                   -> nur Admin-Flag (ist_admin), unabhängig von der Rolle
      konten.php              -> Passwort zurücksetzen, Löschen, Admin-Rechte vergeben
      bilder.php              -> Foto-Übersicht, zu große Bilder prüfen und verkleinern
      foto.php                -> liefert Fotos für die Bilder-Übersicht aus (nur Admin)
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
   kopieren und ausfüllen (Datenbank-Zugangsdaten, Vereinsname,
   Absenderadresse für den E-Mail-Verteiler).
4. **Dateien hochladen**: Das komplette Projekt per FTP/SFTP hochladen.
   Im KAS unter "Domains verwalten" das Web-Verzeichnis der Domain/Subdomain
   auf den Ordner `htdocs/` dieses Projekts setzen (nicht auf den
   Projekt-Hauptordner!).
5. **PHP-Version prüfen**: Im KAS unter "PHP-Einstellungen" mindestens PHP 8.0
   auswählen. Die Erweiterungen `gd` und `exif` (für die automatische
   Foto-Verkleinerung/-Drehung) sind bei all-inkl standardmäßig aktiv.
   `htdocs/php.ini` ist bereits im Projekt enthalten und hebt beim Hochladen
   direkt `memory_limit`, `upload_max_filesize`, `post_max_size` und
   `max_execution_time` an (all-inkl liest eine `php.ini` im Webverzeichnis
   automatisch, ohne dass in KAS selbst etwas eingestellt werden muss).
6. **Ersten Vorstandszugang anlegen** (einmalig, Henne-Ei-Problem: ohne
   Vorstandsmitglied kann niemand über die App selbst eines anlegen):
   - Mit SSH-Zugriff auf den Server: `php scripts/create_mitglied.php`
     ausführen und die Abfragen beantworten (inkl. Pfad zu einem Foto auf
     dem Server/dem Rechner, von dem aus das Skript läuft). Das Skript legt
     dabei automatisch auch einen zugehörigen, bereits angenommenen
     Aufnahmeantrag an, damit das erste Vorstandsmitglied genau wie jedes
     andere Mitglied in der Anträge-Übersicht auftaucht.
   - Ohne SSH-Zugriff: das Mitglied direkt per phpMyAdmin in die Tabelle
     `mitglieder` eintragen, mit `rolle = 'vorstandsmitglied'`,
     `ist_admin = 1` (damit der Admin-Bereich erreichbar ist) und einem
     Passwort-Hash, den du lokal per
     `php -r "echo password_hash('DeinPasswort', PASSWORD_DEFAULT);"`
     erzeugst. In diesem Fall fehlt der zugehörige Aufnahmeantrag zunächst -
     bei Bedarf gesondert per SQL nachtragen.
7. Aufrufen und testen: `https://deine-domain.de/` zeigt die Startseite,
   `.../login.php` das Mitglieder-Login.

## Rechtliches noch offen

- `htdocs/impressum.php` und `htdocs/datenschutz.php` enthalten Platzhalter
  (`[Vereinsname]`, `[Anschrift]`, usw.) und müssen vom Vorstand ausgefüllt
  und im Zweifel rechtlich geprüft werden, bevor die App live geht.

## Noch nicht eingebaut

- Zugangsdaten/Rundmails werden über `mail()` verschickt (kein SMTP-Versand
  über einen externen Dienst). Neue Passwörter werden dem Mitglied aktuell
  weiterhin manuell mitgeteilt, nicht automatisch per Mail zugestellt.
- Ein Selbstbedienungs-"Passwort vergessen" für Mitglieder gibt es nicht;
  das Zurücksetzen läuft ausschließlich über einen Admin
  (Admin → Konten → "Passwort zurücksetzen").

## Hinweis zum E-Mail-Verteiler

Der Verteiler nutzt PHPs eingebaute `mail()`-Funktion, die auf all-inkl KAS
grundsätzlich funktioniert, aber ohne weitere Konfiguration leicht im
Spam-Ordner der Empfänger landen kann. Für bessere Zustellbarkeit:

- `MAIL_ABSENDER_EMAIL` in `private/config.php` auf eine echte Adresse der
  eigenen Domain setzen (keine Fantasie-Adresse).
- Im KAS-Bereich der Domain SPF (und wenn möglich DKIM) für die Absender-
  Domain einrichten.
- Bei größeren Mitgliederzahlen oder Zustellproblemen später auf einen
  SMTP-Versand (z.B. über einen Transaktionsmail-Dienst) umstellen.

## Lokal testen

Mit installiertem PHP (>= 8.0) und MySQL/MariaDB:

```
php -S localhost:8000 -t htdocs
```

Datenbank vorher wie oben beschrieben anlegen, `private/config.php` mit
lokalen Zugangsdaten befüllen und mit `php scripts/create_mitglied.php`
den ersten Vorstandszugang anlegen.
