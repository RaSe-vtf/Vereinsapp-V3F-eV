# Arbeitsregeln für dieses Projekt

Diese Regeln gelten für alle künftigen Sitzungen an diesem Repository und
haben Vorrang vor allgemeinen Standardverhalten.

1. **Keine Code-Änderungen ohne vorheriges Okay.** Vor jeder Implementierung
   (neues Feature, Bugfix, Refactoring) erst den geplanten Ansatz kurz
   beschreiben und auf ausdrückliche Bestätigung des Nutzers warten. Nicht
   einfach losprogrammieren, auch nicht bei scheinbar kleinen Änderungen.
2. **Auslieferungen immer als ZIP**, nie als einzelne Dateien zum Download.
3. **Einheitliche Namensgebung**: Wenn zu einer Auslieferung sowohl eine
   `.sql`- als auch eine `.zip`-Datei gehören, müssen beide denselben
   Basisnamen tragen (z.B. `vereinsapp-2024-06-01.sql` und
   `vereinsapp-2024-06-01.zip`), damit Code-Stand und Datenbank-Stand klar
   zusammengehören.

## Marken-Farbverlauf

Der Akzent-Farbverlauf orientiert sich am App-Icon und wird für Banner-Rahmen
(`.top-header`) und Buttons (`.btn`) verwendet, aktuell als CSS-Variable
`--verlauf-akzent` in `htdocs/assets/css/style.css`:

```css
linear-gradient(120deg, #ff3399 0%, #fadd06 50%, #5b9bd5 100%);
```

- Pink: `#ff3399`
- Gelb: `#fadd06`
- Blau: `#5b9bd5`

Diese Farbfolge (Pink → Gelb → Blau) bei künftigen Design-Änderungen
beibehalten, sofern nicht ausdrücklich anders gewünscht.
