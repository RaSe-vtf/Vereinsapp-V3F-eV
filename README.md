# Vereinsapp

Webapp für Vonsys Tri Family e.V.: Mitglieder stellen über ein Formular ihren Aufnahmeantrag,
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
  Foto-Upload, einem selbst gewählten Passwort (mind. 8 Zeichen, mit
  Wiederholung) und den Einverständniserklärungen (Satzung/Ordnungen,
  Kenntnisnahme Impressum & Datenschutz, optionale Freigabe für
  Social-Media-Fotos). Direkt über der Einverständniserklärung verlinkt das
  Formular auf die tatsächlichen, vom Vorstand hochgeladenen Satzungs-/
  Ordnungs-PDFs (`htdocs/vereinsdokument.php`, siehe **Vereinsdokumente**
  weiter unten) — bewusst ohne Login-Pflicht, da Antragsteller noch keinen
  Zugang haben; nach der Aufnahme sind diese Dateien im normalen
  Mitgliederbereich nicht mehr verlinkt, nur der Vorstand kommt über die
  Geschäftsstelle weiterhin dauerhaft heran. Das Passwort gilt sofort,
  sobald der Vorstand den Antrag annimmt — ein separater Schritt zur
  Passwortvergabe entfällt damit.
- Impressum & Datenschutzerklärung als Vorlage (öffentlich erreichbar, wie
  gesetzlich vorgeschrieben) — **muss noch mit den echten Vereinsdaten
  ausgefüllt werden**, siehe `htdocs/impressum.php` und `htdocs/datenschutz.php`.
- Jedes Mitglied hat ein eigenes Login (E-Mail + Passwort) und eine Rolle:
  Vollmitglied, Trainingsmitglied, Vorstandsmitglied, Ehrenmitglied,
  Fördermitglied.
- **SEPA-Lastschriftmandat** (`htdocs/bereich/sepa_mandat.php`): Beim ersten
  Login nach der Aufnahme (und bei jedem Bestandsmitglied, das noch kein
  Mandat erteilt hat — Rolle spielt keine Rolle, betrifft auch Vorstand/
  Admin) führt die App zwingend zu dieser Seite, bevor irgendetwas anderes
  in der App möglich ist. Erfasst Kontoinhaber, IBAN (mit Format- und
  Prüfziffer-Validierung) und optional BIC, generiert eine Mandatsreferenz
  und zeigt den SEPA-Mandatstext mit Bezug auf Beitragsordnung und
  Startpassregelung der DTU. Das Gate sitzt zentral in
  `requireMemberLogin()` (`includes/auth.php`) und greift dadurch
  automatisch auf allen geschützten Seiten. Die Gläubiger-
  Identifikationsnummer wird über die Konstante `SEPA_GLAEUBIGER_ID` in
  `private/config.php` gepflegt (**muss noch mit der echten, beim
  Bundesamt für Wirtschaft und Ausfuhrkontrolle beantragten Nummer befüllt
  werden**, siehe Platzhalter in `config.example.php`). Kontoinhaber, IBAN,
  BIC und Mandatsreferenz sind für den Vorstand im Datenblatt eines
  Mitglieds einsehbar (`.../vorstand/mitglied_ansehen.php`) sowie unter
  "Meine Daten" nur lesbar. Die Bankverbindung ist dort bewusst nicht
  direkt änderbar — für eine Änderung führt ein Button erneut zur
  Mandatsseite (gleiche Validierung, gleiche Zustimmung erneut nötig).
  Wird dabei ein bereits bestehendes Mandat geändert (nicht beim
  allerersten Ausfüllen), verschickt die App automatisch eine E-Mail an
  `MAIL_ABSENDER_EMAIL` mit Name und neuer Bankverbindung, damit der
  Vorstand informiert ist.
- **Navigation im eingeloggten Bereich**: Das Logo oben links führt immer zur
  Startseite `bereich/home.php` mit einer Kachel pro Bereich (Meine Daten,
  Sportlerprofile, Geschäftsstelle, Admin — je nachdem, was die Rolle/das
  Admin-Flag erlaubt).
  Oben rechts im Banner öffnet ein Menü-Symbol (☰) ein Dropdown mit denselben
  Bereichen sowie "Abmelden" ganz unten. Unterhalb des Banners zeigt jede
  Seite oben rechts einen kleinen "← Zurück"-Pfeil zur jeweils nächst höheren
  Seite (z.B. von einem Datenblatt zurück zur Liste, von einer Bereichsseite
  zurück zur Startseite). Technisch über den gemeinsamen Baustein
  `includes/kopf.php` und `htdocs/assets/js/menue.js`.
- Mitgliederbereich (`htdocs/bereich/`):
  - **Meine Daten**: für alle eingeloggten Mitglieder, eigene Stammdaten
    (Name, Geburtsdatum/-ort, Adresse, Telefon, E-Mail, Instagram, Foto,
    Shirt-Größe, Kurzporträt) selbst ändern bzw. ergänzen und eigenes
    Passwort ändern. Pflichtfelder bleiben Pflicht (gleiche Validierung wie
    beim Aufnahmeantrag), die E-Mail-Adresse wird auf Eindeutigkeit geprüft.
    Rolle, Kontostatus und Passwort-Reset bleiben Sache des Vorstands.
    Widerruf der Bildnutzungs-Einwilligung läuft bewusst nicht über einen
    Schalter in der App, sondern per E-Mail an den Vorstand (siehe
    Datenschutzerklärung).
  - **Sportlerprofile** (`sportlerprofile.php` / `sportlerprofil.php`): für
    alle eingeloggten Mitglieder sichtbare Übersicht (Kachel je Sportler
    mit Foto, Vorname, Nachname) und Detailseite je Mitglied. Zeigt nur
    Angaben, die für die öffentliche Vorstellung gedacht sind: Foto,
    Shirt-Größe (als Tag), Geburtsdatum, Mitglied seit, Heimatort (nur
    Ort, nicht die volle Adresse), Handynummer maskiert auf die letzten 4
    Ziffern (zur WhatsApp-Zuordnung) sowie Instagram und ein kurzes
    Porträt (im Aufnahmeantrag Pflichtfelder). Verwaltungsinterne Daten (volle Adresse, volle
    Telefonnummer, Geburtsort, E-Mail, Rolle, Passwort-Status,
    Bankverbindung) erscheinen hier bewusst nicht. Auf dem eigenen Profil
    gibt es zusätzlich einen "Profil bearbeiten"-Link zu "Meine Daten".
    Mitgliederfotos sind dafür in `foto.php` für alle eingeloggten
    Mitglieder freigegeben (nicht mehr nur Vorstand/eigenes Foto).
  - **Geschäftsstelle**: nur sichtbar und aufrufbar für die Rolle
    Vorstandsmitglied (Reiter heißt bewusst nicht "Vorstand", um Bereich
    und Personen-Rolle sprachlich zu trennen). Enthält:
    - **Aufnahmeanträge**: zeigt standardmäßig alle Anträge (Filter "Alle"
      voreingestellt), die Filter-Buttons (Alle/Neu/Angenommen/Abgelehnt)
      sind kompakte Pillen oberhalb der Liste. Annehmen/Ablehnen/
      Zurücksetzen-Buttons gibt es nur noch bei Status "Neu" — ist einmal
      entschieden, ist der Antrag ein abgeschlossener, historischer
      Vorgang. Beim Annehmen übernimmt das neue Mitgliedskonto automatisch
      das beim Aufnahmeantrag selbst gewählte Passwort (Login funktioniert
      direkt danach). Nur bei Alt-Anträgen ohne gespeichertes Passwort
      (vor Einführung dieser Funktion gestellt) wird ersatzweise eines
      generiert und einmalig angezeigt. Annehmen und Ablehnen verschicken
      automatisch eine E-Mail an den Antragsteller (Willkommens- bzw.
      Absage-Text, siehe `sendeEinzelMail()` in `includes/functions.php`).
    - **Mitgliederverwaltung**: Tabelle mit allen Stammdaten pro Person
      (Foto, Bildnutzung-Einwilligung, Vor-/Nachname, Geburtsdatum/-ort,
      Adresse, Telefon, E-Mail, Instagram, Rolle, Status, Passwort-Status,
      Mitglied seit) sowie Rolle ändern und Konto aktivieren/deaktivieren.
      Die Spalte "Bildnutzung" zeigt ja/nein anhand der beim Aufnahmeantrag
      gegebenen (freiwilligen) Einwilligung zur Social-Media-Nutzung von
      Fotos/Videos (per LEFT JOIN auf `antraege.einverstaendnis_bildnutzung`
      über `mitglieder.antrag_id` - ohne verknüpften Antrag gilt sicherheits-
      halber "nein"), damit der Vorstand vor einem Social-Media-Post sieht,
      wer widersprochen hat bzw. nicht zugestimmt hat. Nachname verlinkt
      weiterhin zusätzlich auf das ausführliche Datenblatt. Konto löschen
      und Passwort zurücksetzen sind in den Admin-Bereich umgezogen (siehe
      unten).
    - **E-Mail-Verteiler**: klassische Liste aller aktiven Mitglieder
      (Nachname, Vorname, Rolle, E-Mail) mit eigener Checkbox je Zeile -
      wer die Rundmail bekommen soll, wird direkt in der Liste angehakt.
      In der Kopfzeile eine "Alle sichtbaren auswählen"-Checkbox sowie ein
      Filter-Rollup bei "Rolle" (Dropdown zum Ein-/Ausblenden von Zeilen
      nach Rolle, rein zum schnelleren Finden/Auswählen - ändert nichts an
      bereits gesetzten Häkchen). Der Zähler über der Liste zeigt die
      Anzahl tatsächlich ausgewählter Empfänger, unabhängig vom Filter.
    - **Kassenwart** (`.../vorstand/kassenwart/`): eigener Unterbereich,
      Zugriff wie der Rest der Geschäftsstelle an die Rolle
      Vorstandsmitglied gebunden. Der Link "Kassenwart" in der
      Geschäftsstelle-Navigation führt auf `index.php` mit vier Kacheln
      (Bankverbindungen/Beiträge/SEPA-Export/Kassenbücher, analog den
      Kacheln auf der Startseite); innerhalb der Unterseiten von
      Bankverbindungen/Beiträge/SEPA-Export bleibt zusätzlich eine schlichte
      Textzeile zum direkten Wechseln untereinander erhalten.
      - **Bankverbindungen**: Liste aller aktiven Mitglieder mit Rolle,
        Kontoinhaber, IBAN, BIC, Mandatsreferenz und Erteilungsdatum; fehlt
        ein Mandat, steht dort "kein Mandat hinterlegt". Die angezeigte
        Rolle ist die **Rolle im Bankbereich** (siehe `bankRolle()` in
        `includes/functions.php`): Admins und Vorstandsmitglieder gelten
        hier unabhängig von ihrer sonstigen Rolle als Vollmitglieder — das
        gilt ebenso für die Beitragszuordnung im SEPA-Export.
      - **Beiträge**: feste Positionen statt freier Verwaltung — oben
        "Mitgliedsbeiträge" mit einem monatlichen €-Betrag je Mitgliederart,
        darunter "Startpässe" mit den jährlichen Startpasskosten (z.B. DTU).
        Nur diese Beträge sind im SEPA-Mandat abgedeckt und dürfen in den
        SEPA-Export; andere Zahlungen laufen immer als direkte Überweisung
        außerhalb der App.
      - **SEPA-Export**: Checkbox-Auswahl, welche aktiven Beiträge in
        diesen Lauf einfließen, plus Fälligkeitstermin (mind. 5 Tage
        Vorlauf). Eine Live-Vorschau (ohne Neuladen) zeigt Anzahl und
        Gesamtbetrag der einbezogenen Mitglieder sowie, wie viele mangels
        Mandat übersprungen werden. Der Button erzeugt eine
        SEPA-Sammellastschrift-Datei im Format **pain.008.001.02** (ISO
        20022) zum direkten Hochladen im Online-Banking
        (`erzeugeSepaLastschriftDatei()` in `includes/functions.php`).
        Erst- und Folgelastschriften (FRST/RCUR) werden dabei automatisch
        pro Mitglied unterschieden (`sepa_erste_lastschrift_erfolgt`) und
        stehen laut Spezifikation in getrennten Blöcken. Benötigt eine
        gültige Vereins-IBAN (`VEREIN_IBAN`, optional `VEREIN_BIC`) in
        `private/config.php` — noch Platzhalter, siehe `CLAUDE.md`.
      - **Kassenbücher** (`.../kassenwart/kassenbuecher/`): eigene
        Hub-Seite mit zwei Kacheln, Vereinskonto und Barkasse. Beide zeigen
        oben den aktuellen Stand mit dem +/- des laufenden Jahres daneben,
        darunter Reiter für die Kalendermonate, darunter Einnahmen/Ausgaben
        des gewählten Monats.
        - **Vereinskonto**: komplett ohne manuelle Zahleneingabe, um
          Übertragungsfehler auszuschließen. Kontostand, Jahres-+/- sowie
          die Einnahmen/Ausgaben je Monat ergeben sich ausschließlich aus
          hochgeladenen Kontoauszügen. Unterstützt werden die beiden
          bankunabhängigen Standardformate **CAMT.053** (ISO-20022-XML) und
          **MT940** (SWIFT) — Jahr/Monat des Auszugs sowie alle Buchungen
          (Datum, Betrag, Verwendungszweck, Beteiligter) werden automatisch
          geparst (`parseKontoauszug()` in `includes/functions.php`).
          Bietet eine Bank ein anderes Format an, muss dafür anhand eines
          echten Auszugs ein passender Import ergänzt werden. Die
          gespeicherten Auszugsdateien je Monat stehen zum Download bereit
          (`kontoauszug_datei.php`).
        - **Barkasse**: Zugänge entweder als bestätigte Übernahme einer
          Kontobewegung (`istBargeldabhebungVerdacht()` erkennt anhand des
          Verwendungszwecks mögliche Geldautomaten-Abhebungen als Vorschlag,
          der Kassenwart bestätigt jeden Vorschlag einzeln statt einen Betrag
          einzutippen) oder als manuelle Einnahme (z.B. eine Bar-Spende, die
          nie über die Bank lief). Ausgaben sind immer manuell und verlangen
          zwingend Empfänger, Betrag und einen Beleg-Upload (Foto oder PDF,
          `beleg_datei.php` zum Ansehen). Andere Kassenwart-Zahlungen laufen
          weiterhin außerhalb der App als direkte Überweisung.
    - **Vereinsdokumente** (`.../vorstand/vereinsdokumente.php`): Satzung und
      Ordnungen hoch- und herunterladen (nur PDF). Pro Bezeichnung (z.B.
      "Satzung", "Beitragsordnung") wird eine Historie geführt statt Dateien
      zu ersetzen — die jeweils jüngste Fassung je Bezeichnung gilt als
      aktuell, ältere Fassungen bleiben bis zum manuellen Löschen erhalten.
      Der Download selbst läuft über `htdocs/vereinsdokument.php` (bewusst
      ohne Login-Pflicht, siehe oben beim Aufnahmeantrag) — verwaltet
      (hoch-/herunterladen, löschen) werden die Dokumente aber nur über
      diese Geschäftsstelle-Seite, also weiterhin nur vom Vorstand.
  - **Admin**: nur sichtbar und aufrufbar für Mitglieder mit dem
    Admin-Flag (`ist_admin`, unabhängig von der Rolle — z.B. kann ein
    Vorstandsmitglied zusätzlich Admin sein). Enthält:
    - **Konten**: Passwort zurücksetzen (z.B. wenn ein Mitglied sein
      Passwort vergessen hat — die eigentliche Passwortvergabe passiert
      ja schon beim Aufnahmeantrag), Konto endgültig löschen und
      Admin-Rechte an- bzw. abschalten. Ein Admin kann sich weder selbst
      löschen noch sich selbst das Admin-Recht entziehen (Schutz vor
      Aussperren) — das kann nur ein anderer Admin.
    - **Bilder**: ein einziger Button "Bilder jetzt prüfen und
      optimieren". Prüft alle aktuell verwendeten Fotos (aus
      Aufnahmeanträgen und Mitgliederkonten) und verkleinert/komprimiert
      automatisch alle, die über 1600px an der längsten Kante liegen
      (gleiche Verarbeitung wie beim Hochladen) — bereits passende
      Bilder werden nicht angerührt. Danach eine Meldung, wie viele
      Bilder geprüft, wie viele tatsächlich verkleinert und wie viel
      Speicherplatz dadurch eingespart wurde. Gedacht u.a. für
      Altbestände, die vor Einführung der automatischen Verkleinerung
      z.B. per FTP/phpMyAdmin eingespielt wurden.
- Nimmt der Vorstand einen Antrag an, wird automatisch ein Mitgliedskonto mit
  Rolle "Vollmitglied" angelegt, mit dem beim Aufnahmeantrag selbst gewählten
  Passwort — das Mitglied kann sich damit sofort einloggen.
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
  einzeln per Checkbox ausgewählte aktive Mitglieder, verschickt per Bcc
  (die Mitglieder sehen die E-Mail-Adressen der anderen Empfänger nicht).
  Der Rollen-Filter in der Kopfzeile blendet Zeilen nur zum leichteren
  Finden/Auswählen ein oder aus, er verändert keine Häkchen.
  Nutzt die native PHP-`mail()`-Funktion, wie sie auf all-inkl KAS
  standardmäßig zur Verfügung steht — siehe Hinweis zu Absenderadresse/Spam
  weiter unten.
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
  vereinsdokument.php       -> Download eines Vereinsdokuments (ohne Login-Pflicht)
  antrag_erfolg.php
  impressum.php
  datenschutz.php
  login.php / logout.php    -> Mitglieder-Login
  bereich/                  -> eingeloggter Bereich (alle Mitglieder)
    home.php                -> Startseite mit Kacheln (Logo-Ziel, Menü-Ziel bei "Meine Daten" etc.)
    index.php               -> "Meine Daten" + Passwort ändern
    sportlerprofile.php     -> Sportlerprofile: Übersicht aller aktiven Mitglieder
    sportlerprofil.php      -> Sportlerprofile: Detailansicht eines Mitglieds
    sepa_mandat.php         -> Pflicht-Gate: SEPA-Lastschriftmandat vor erstem Zugriff
    foto.php                -> liefert Mitgliederfotos aus (alle eingeloggten Mitglieder) bzw. Antragsfotos (nur Vorstand)
    vorstand/                -> nur Rolle Vorstandsmitglied
      antraege.php           -> Aufnahmeanträge verwalten (Standardfilter: alle)
      antrag_ansehen.php
      mitglieder.php         -> Mitgliederverwaltung: alle Stammdaten, Rolle, Status
      mitglied_ansehen.php
      verteiler.php          -> E-Mail-Verteiler mit Live-Empfängervorschau
      vereinsdokumente.php   -> Satzung/Ordnungen hochladen/verwalten (mit Historie)
      kassenwart/
        index.php             -> Kassenwart-Startseite mit 4 Kacheln
        bankverbindungen.php -> Liste Kontoinhaber/IBAN/BIC aller aktiven Mitglieder
        beitraege.php          -> Mitgliedsbeiträge je Rolle + Startpasskosten
        export.php             -> SEPA-Sammellastschrift (pain.008.001.02) erzeugen
        kassenbuecher/
          index.php            -> Hub-Seite mit 2 Kacheln
          vereinskonto.php     -> Kontostand/Buchungen aus hochgeladenen Kontoauszügen
          kontoauszug_datei.php -> Download einer gespeicherten Auszugsdatei
          barkasse.php         -> Kassenstand, Konto-Übernahmen, manuelle Ein-/Ausgaben
          beleg_datei.php      -> Ansicht eines Barkassen-Belegs (Foto/PDF)
    admin/                   -> nur Admin-Flag (ist_admin), unabhängig von der Rolle
      konten.php              -> Passwort zurücksetzen, Löschen, Admin-Rechte vergeben
      bilder.php              -> Ein-Klick-Button: bestehende Bilder prüfen und verkleinern
  assets/
    css/style.css           -> Styles, u.a. Menü/Zurück-Pfeil/Kacheln
    js/lightbox.js          -> Foto-Lupe
    js/menue.js             -> Auf-/Zuklappen des Menü-Symbols im Banner

includes/          -> gemeinsamer PHP-Code (liegt bewusst AUSSERHALB von htdocs)
  kopf.php         -> gemeinsamer Banner/Menü/Zurück-Pfeil-Baustein für bereich/**
private/           -> Konfiguration + hochgeladene Dateien (liegt AUSSERHALB von htdocs)
  config.php       -> wird lokal erstellt, nicht Teil des Repos
  uploads/fotos/   -> gespeicherte Mitgliederfotos
  uploads/kontoauszuege/ -> gespeicherte Kontoauszugsdateien (Vereinskonto)
  uploads/belege/  -> gespeicherte Barkassen-Belege (Foto/PDF)
  uploads/vereinsdokumente/ -> gespeicherte Satzung/Ordnungen (PDF)

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
   verlinkt) in die neu angelegte Datenbank importieren. Die Datei ist
   idempotent aufgebaut (`CREATE TABLE IF NOT EXISTS` bzw.
   `ADD COLUMN IF NOT EXISTS`): bei jeder späteren Auslieferung mit
   Schema-Änderung reicht es, dieselbe Datei erneut komplett zu
   importieren – bereits vorhandene Tabellen/Spalten werden übersprungen,
   fehlende ergänzt, bestehende Daten bleiben unangetastet.
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
