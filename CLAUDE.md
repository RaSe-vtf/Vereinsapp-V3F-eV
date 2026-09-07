# Arbeitsregeln für dieses Projekt

Diese Regeln gelten für alle künftigen Sitzungen an diesem Repository und
haben Vorrang vor allgemeinen Standardverhalten.

1. **Keine Code-Änderungen ohne vorheriges Okay.** Vor jeder Implementierung
   (neues Feature, Bugfix, Refactoring) erst den geplanten Ansatz kurz
   beschreiben und auf ausdrückliche Bestätigung des Nutzers warten. Nicht
   einfach losprogrammieren, auch nicht bei scheinbar kleinen Änderungen.
2. **Auslieferungen immer als ZIP**, nie als einzelne Dateien zum Download.
3. **`.zip` bei jeder Auslieferung, `.sql` nur bei Schema-Änderung.** Die
   `.zip` (Code-Stand) gehört zu jeder Auslieferung dazu. Die `.sql`
   (Datenbankschema) nur zusätzlich anzeigen/liefern, wenn sich das Schema
   seit der letzten Auslieferung tatsächlich geändert hat – hat sich nichts
   geändert, wird die `.sql` nicht angezeigt.
4. **Immer derselbe, feste Dateiname** – kein Datum, keine hochgezählte
   Nummer, kein beschreibendes Wort: jede Auslieferung heißt schlicht
   `vereinsapp.zip` und `vereinsapp.sql`. Bei jeder neuen Auslieferung wird
   dieser gleiche Name erneut verwendet (überschreibt die vorherige
   gleichnamige Datei beim Nutzer).

## Marken-Farbverlauf

Der Akzent-Farbverlauf orientiert sich am App-Icon und wird für Banner-Rahmen
(`.top-header`) und Buttons (`.btn`) verwendet, aktuell als CSS-Variable
`--verlauf-akzent` in `htdocs/assets/css/style.css`:

```css
linear-gradient(120deg, #ff3399 0%, #ff3399 20%, #fadd06 40%, #fadd06 60%, #5b9bd5 80%, #5b9bd5 100%);
```

Symmetrischer Aufbau, fünf gleich große Abschnitte à 20%: Pink (0–20%),
Übergang (20–40%), Gelb (40–60%), Übergang (60–80%), Blau (80–100%). Alle
drei Farben bekommen gleich viel Fläche, und beide Mischzonen links und
rechts von Gelb sind gleich breit.

- Pink: `#ff3399`
- Gelb: `#fadd06`
- Blau: `#5b9bd5`

Diese Farbfolge und dieses symmetrische Stop-Muster (Pink → Gelb → Blau,
je 20%) bei künftigen Design-Änderungen beibehalten, sofern nicht
ausdrücklich anders gewünscht.
