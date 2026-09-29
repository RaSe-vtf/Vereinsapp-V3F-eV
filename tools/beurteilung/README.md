# Beurteilungs-Tool (Vordruck BPOL 4 00 069)

Befüllt den Vordruck „Dienstliche Beurteilung in der Bundespolizei“ automatisch
aus der Notenübersicht (Excel). Läuft komplett offline in einer einzigen
HTML-Datei (Safari, Chrome, Edge), ohne Installation und ohne Makros.
Hat nichts mit der Vereinsapp zu tun und liegt nur hier im Repository.

## Ablauf für Nutzer

1. Notenübersicht öffnen, Blatt **„Beurteilungen erstellen“** ausfüllen
   (Art, Stichtag, Zeitraum, Auswahl, Beurteilende), speichern und schließen
   (eine in Excel geöffnete Datei ist im Auswahlfenster des Browsers gesperrt).
2. `Beurteilung.html` per Doppelklick öffnen, Excel-Datei hineinziehen → ZIP mit
   einer PDF pro Person im Download-Ordner.
3. Beurteilende ergänzen alles Weitere im PDF (Adobe Acrobat Reader, nicht
   „Vorschau“ am Mac).
4. Ausgefüllte PDF in `Beurteilung.html` unter „Beurteilung fertigstellen“
   hineinziehen → `…_fertig.pdf`: überlange Begründung der Gesamtnote wird auf
   Fortsetzungsseiten hinter Seite 5 weitergeführt, zu lange Texte in anderen
   Feldern werden gemeldet.

Eingetragen werden: Beurteilungsart, Anlass, Stichtag, Zeitraum, Beamtin/Beamter,
Name, Geburtsdatum, Amtsbezeichnung mit Besoldungsgruppe (z.B. „Polizeiobermeister (A8)“),
letzte Ernennung, Dienststelle/Organisationseinheit, Funktionsbezeichnung/-wertigkeit
(Seite 1 und Nr. 4.1.2 auf Seite 2) mit den prägenden Tätigkeiten aus dem Blatt
„Funktionen“, bis zu 6 Kooperationsgespräche (Spalte mit `;` getrennt), Gespräch
vor Beurteilung, Schwerbehinderung ja/nein, Erst-/Zweitbeurteilende, die vier
Teilnoten (1.1, 2, 4.2, 4.3) und die Gesamtnote (Spalte „neue RBU“) – bei Regel-
und Anlassbeurteilung auf Seite 5 und unter „G Gesamtbewertung“ auf Seite 3, beim
Beurteilungsbeitrag nie. Sind eine Anlassbeurteilung und/oder bis zu drei
Beurteilungsbeiträge mit x und Zeitraum eingetragen, kommt bei jeder
Beurteilungsart ein Textbaustein in „Allgemeine Bemerkungen“ (Seite 5): bei einem
Eintrag „Die Anlassbeurteilung vom … bis … wurde in dieser Beurteilung
berücksichtigt.“, bei mehreren „Folgende Beurteilungen wurden …:“ mit einer
zeitlich geordneten Aufzählung. ALB-Gesamtnote und -Teilnoten bleiben nur in Excel.
Alles bleibt im PDF von den Beurteilenden änderbar.

## Statusämter (fest, vom Nutzer bestätigt)

| Kürzel | Amtsbezeichnung | Besoldungsgruppe |
|---|---|---|
| PM | Polizeimeister/-in | A7 |
| POM | Polizeiobermeister/-in | A8 |
| PHM | Polizeihauptmeister/-in | A9m |
| PHMZ | Polizeihauptmeister/-in | A9mZ |
| PK | Polizeikommissar/-in | A9g |
| POK | Polizeioberkommissar/-in | A10 |
| PHK | Polizeihauptkommissar/-in | A11 |
| PHKZ | Polizeihauptkommissar/-in | A12 |
| EPHK | Erste/r Polizeihauptkommissar/-in | A13 |

Form im PDF: „Polizeiobermeister (A8)“. Diese Werte sind endgültig und
sollen nicht mehr geändert werden. Sie stehen in `AMTSBEZEICHNUNGEN`
(`build/excel_aufbereiten.py`), werden bei jedem Aufbereiten fest geschrieben
und sind im Blatt „Einstellungen“ grau und per Blattschutz (ohne Kennwort)
gesperrt.

## Änderungen am Vordruck (bewusst nur diese)

- Alle Text- und Auswahlfelder: feste Schriftgröße 10 pt statt „automatisch“
  (Fußzeile 8 pt) – Text schrumpft nicht mehr.
- „Begründung der Gesamtnote“: darf länger als das Feld werden (Fortsetzung
  beim Fertigstellen). Dafür wird der Berechtigungsschutz entfernt.

## Bauen

```sh
pip install pymupdf openpyxl
python3 build/vordruck_vorbereiten.py <4_00_069_Vordruck_blanko.pdf> src/vordruck_vorbereitet.pdf
python3 build/html_bauen.py src/vordruck_vorbereitet.pdf dist/Beurteilung.html
python3 build/excel_aufbereiten.py <Notenübersicht.xlsx> <Notenübersicht_neu.xlsx> [--vorlage <früher_aufbereitet.xlsx>]
python3 build/funktionen_importieren.py <Anforderungsprofile/ oder .zip> <Notenübersicht_neu.xlsx> [...]
```

`funktionen_importieren.py` liest Anforderungsprofile (teilausgefüllte Vordrucke
BPOL 4 00 069: `f.funktion.1`, `f.funktion.2`, `f.taetigkeit.1`) und schreibt sie ins
Blatt „Funktionen“ (gleichnamige ersetzt, neue angehängt, sortiert nach Wertigkeit).
Die Profile selbst liegen nicht im Repository.

Die Notenblätter werden dabei in die Reihenfolge des Vordrucks sortiert
(Personalangaben, Zug, Funktion, Beteiligung, Schwerbehinderung, Teilnoten,
Gesamtnote, Anlassbeurteilung (x, von, bis, Gesamtnote – vormals „aktueller ALB“ –,
Statusamt, 4 Teilnoten), Beurteilungsbeiträge 1–3 (x, von, bis); danach
Subsidiärmerkmale, vorletzte Beurteilung, Teilzeit, „PDF“ zuletzt). Alle Formelbezüge, Auswahllisten, Breiten und Gruppenüberschriften
werden mit umgesetzt; die Punktetabelle (A1 = 6 … C = 1) liegt ausgeblendet rechts.

Jedes Notenblatt hat eine feste Tabelle für 60 Personen (Zeilen 14–73). Jede
Rechenspalte trägt in jeder Zeile dieselbe Formel (Zeile ohne Namen: leer; fehlende
Angabe: „n.N.“): Lfd.Nr. = Anzahl Namen bis zur Zeile, Amtsbez. = Reitername
(+ „in“ bei Geschlecht w), Summe = Punkte der 4 Teilnoten (nur wenn alle 4 gültig),
Monate/Monate (zur Hälfte) = DATEDIF zum Stichtag S2 (fehlt Datum/Stichtag oder
liegt das Datum danach: n.N.), ges. = Summe beider. Die Rechenhilfen (4 Punkte-
spalten, Punktetabelle) werden hinter „PDF“ in allen Blättern gleich neu angelegt
und ausgeblendet; die Statistik oben zählt genau die 60 Tabellenzeilen. Danach werden alle Blätter ohne Kennwort geschützt:
frei sind nur die Eingabezellen (Datenzeilen außer Rechenspalten, Stichtag S2,
gelbe Felder der Tool-Blätter); Spaltenbreite, Zeilenhöhe, Formatieren und Filtern
bleiben erlaubt.

`excel_aufbereiten.py` ist wiederholbar: über eine schon aufbereitete Datei
laufen gelassen, bleiben Startseite, Beurteiler, Funktionen und Einstellungen
erhalten. Kommt eine Rohfassung ohne diese Blätter, übernimmt `--vorlage` sie
aus einer früher aufbereiteten Datei.
Neue Personen, Zeilen oder Notenblätter erkennt das Tool ohnehin selbst
(Spalten werden über die Überschriften gefunden).

Beurteilung.html kann außerdem Listen leeren (Bereich 3): gewählte Notenblätter,
immer mit Rückfrage; es entsteht eine neue Datei `…_leer.xlsx` (Original bleibt).
Dafür werden in den 60 Tabellenzeilen bis zur Spalte „PDF“ direkt im XML alle
Werte ohne Formel entfernt; Formeln, Formate, Auswahllisten und Blattschutz
bleiben, Excel rechnet beim Öffnen neu (`fullCalcOnLoad`).

- `src/engine.js` – Kern: Excel lesen, PDF befüllen, fertigstellen, Listen leeren
- `src/ui.html` – Oberfläche (Platzhalter werden beim Bauen ersetzt)
- `vendor/` – pdf-lib 1.17.1 (MIT), SheetJS 0.18.5 mini (Apache-2.0), JSZip 3.10.1 (MIT)
