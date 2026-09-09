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

## Merkliste / Offene Punkte

Diese Liste ist der feste Ablageort für Punkte, die später noch final
geklärt/eingetragen werden müssen. Sie ist Teil von CLAUDE.md und damit in
jeder künftigen Sitzung automatisch abrufbar. Neue offene Punkte hier
ergänzen, erledigte Punkte hier entfernen bzw. als erledigt vermerken.

- [ ] **SEPA-Gläubiger-Identifikationsnummer, Vereins-IBAN, Vereins-BIC**:
  Der Verein hat noch kein Vereinskonto und daher weder eine Gläubiger-ID
  beim Bundesamt für Wirtschaft und Ausfuhrkontrolle beantragt noch eine
  Vereins-IBAN/BIC. `SEPA_GLAEUBIGER_ID`, `VEREIN_IBAN` und `VEREIN_BIC` in
  `private/config.php` stehen deshalb bewusst noch auf Platzhaltern (siehe
  `htdocs/bereich/sepa_mandat.php` bzw. der Kassenwart-SEPA-Export unter
  `htdocs/bereich/vorstand/kassenwart/export.php`). Sobald ein Vereinskonto
  vorliegt, dort eintragen – nicht von selbst nachfragen/andrängen, der
  Nutzer meldet sich dazu.
- [ ] **Vereinsname**: Aktuell in der App als "Vonsys Tri Family e.V."
  hinterlegt (`VEREIN_NAME`/`MAIL_ABSENDER_NAME` in `config.example.php`
  bzw. `private/config.php`). Falls sich der offizielle Name noch ändert
  (z.B. nach endgültiger Eintragung ins Vereinsregister), hier und in der
  Config nachziehen.
- [ ] **Kontoauszug-Import je Bank**: Der Import unter Kassenwart ->
  Kassenbücher -> Vereinskonto (`parseKontoauszug()` in
  `includes/functions.php`) deckt aktuell die zwei bankunabhängigen
  Standardformate CAMT.053 (ISO-20022-XML) und MT940 (SWIFT) ab. Zeigt sich
  anhand echter Auszüge der tatsächlich genutzten Bank, dass das Format
  abweicht (z.B. eigenes CSV) oder Feldbesonderheiten (Verwendungszweck,
  Beteiligter, oder die automatisch aus `<LglSeqNb>` bzw. `:28C:`
  ausgelesene Auszugsnummer für die Lückenprüfung) nicht sauber erkannt
  werden, anhand eines echten Beispielauszugs nachbessern – nicht von
  selbst nachfragen/andrängen, der Nutzer bringt bei Bedarf einen echten
  Auszug mit.
- [ ] **Vereinsdokumente-Upload nach dem Rollout auf PDF-only umstellen**:
  Der Upload unter Geschäftsstelle -> Vereinsdokumente
  (`handleVereinsdokumentUpload()` in `includes/functions.php`) akzeptiert
  aktuell bewusst jedes Dateiformat (PDF bleibt PDF, Bilder werden
  automatisch in eine PDF-Seite gewandelt, alles andere z.B. Word/ODT wird
  unverändert im Originalformat gespeichert) – das gilt ausdrücklich nur
  bis zum Rollout, zur einfacheren Erstbefüllung. Nach dem Rollout soll das
  wieder auf reine PDF-Pflicht zurückgestellt werden (Formate ohne
  automatische Wandlung dann ablehnen statt im Originalformat zu
  akzeptieren) – nicht von selbst nachfragen/andrängen, der Nutzer meldet
  sich, wenn der Rollout so weit ist.

## Vereinsname im Fließtext

"Vonsys Tri Family e.V." (bzw. der jeweils aktuelle Wert von `VEREIN_NAME`)
soll im sichtbaren Text nie über eine Zeile umbrechen, sondern immer wie ein
zusammenhängendes Wort erscheinen. Dafür gibt es `vereinNameNowrap()` in
`includes/functions.php` (ersetzt Leerzeichen durch geschützte Leerzeichen
`\u{00A0}`) – bei sichtbarem Fließtext `e(vereinNameNowrap())` statt
`e(VEREIN_NAME)` verwenden. Ausgenommen sind `alt`-Attribute sowie reiner
Klartext ohne Umbruchsproblem (E-Mail-Texte, SEPA-XML) – dort weiterhin
`VEREIN_NAME` direkt nutzen.

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
