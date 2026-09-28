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
Name, Geburtsdatum, Amtsbezeichnung mit Besoldungsgruppe (z.B. „Polizeiobermeister (A8)“), letzte Ernennung,
Dienststelle/Organisationseinheit, Erst-/Zweitbeurteilende, die vier Teilnoten
(1.1, 2, 4.2, 4.3) und die Gesamtnote (Spalte „neue RBU“).

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
python3 build/excel_aufbereiten.py <Notenübersicht.xlsx> <Notenübersicht_neu.xlsx>
```

`excel_aufbereiten.py` ist wiederholbar: über eine schon aufbereitete Datei
laufen gelassen, bleiben Startseite, Beurteiler und Einstellungen erhalten.
Neue Personen, Zeilen oder Notenblätter erkennt das Tool ohnehin selbst
(Spalten werden über die Überschriften gefunden).

- `src/engine.js` – Kern: Excel lesen, PDF befüllen, fertigstellen
- `src/ui.html` – Oberfläche (Platzhalter werden beim Bauen ersetzt)
- `vendor/` – pdf-lib 1.17.1 (MIT), SheetJS 0.18.5 mini (Apache-2.0), JSZip 3.10.1 (MIT)
