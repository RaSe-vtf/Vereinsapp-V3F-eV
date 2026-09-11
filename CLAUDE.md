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
- [ ] **Geschäftsstelle – weitere Bausteine**: Auf Nachfrage identifizierte
  Lücken, die Schritt für Schritt angegangen werden:
  - [x] Mitglieder-Austritt als geregelter Prozess – erledigt: Vorstand
    erfasst nur das Kündigungseingangsdatum, Austrittsdatum wird
    automatisch nach § 6 Abs. 2 der Satzung berechnet (6 Wochen zum
    Quartalsende), Deaktivierung läuft automatisch (opportunistisch +
    Cronjob `htdocs/cron/austritte.php`), Kassenwart bekommt Hinweise in
    Bankverbindungen/SEPA-Export. Offen beim Nutzer: den Cronjob im
    all-inkl-KAS-Panel einrichten und `CRON_SECRET` in
    `private/config.php` setzen.
  - [x] Vorstandsämter – erledigt: eigenes Feld "Vorstandsamt" (Vorsitz,
    stellv. Vorsitz, Kassenwart, Beisitzer/erweiterter Vorstand) je
    Vorstandsmitglied, unabhängig von der Mitgliedschaftsart. Die drei
    Ämter des geschäftsführenden Vorstands sind laut § 11 Abs. 2 Satzung
    nur einmal vergebbar, das setzt die App durch. Das Impressum zieht die
    Vertretungsberechtigung (§ 26 BGB) jetzt automatisch aus der/dem
    hinterlegten Vorsitzenden.
  - [x] Mitgliederversammlung/Jahreshauptversammlung – bewusst kein
    eigenes Feature in der App (Aufwand/Nutzen), stattdessen vier
    Word-Vorlagen (Einladung, Tagesordnung, Anwesenheitsliste,
    Niederschrift/Beschlussprotokoll) erstellt, mit den einschlägigen
    Fristen/Mehrheiten/Stimmrecht-Regeln aus §§ 8, 14–17 der Satzung als
    Hinweistexte direkt in den Vorlagen. Die "Protokolle"-Seite bleibt ein
    Platzhalter. Offen beim Nutzer: die vier Vorlagen selbst unter
    Geschäftsstelle -> Vereinsdokumente hochladen.
  - [ ] Vereinskalender/Terminverwaltung für Training/Wettkämpfe.

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

Hinweis: Für ein geplantes Redesign nach dem Triathlon-Konzept (siehe
Master-Prompt weiter unten) wird diese feste, symmetrische Verlaufsregel
bewusst zugunsten einer dynamischen, asymmetrischen Farbnutzung
aufgehoben. Bis dieses Redesign tatsächlich umgesetzt ist, gilt die
obige feste Regel unverändert weiter.

## Master-Prompt: Visuelle Identität (Triathlon-Konzept)

Gemeinsam erarbeitetes Kreativ-Briefing für ein künftiges Redesign der
App nach der Leitidee "THE CLUB IS THE RACE" (Ablauf eines
Triathlon-Wettkampftages als visuelle Metapher für die App-Struktur:
Meldebüro/Check-in -> Wechselzone -> Wettkampf -> Ziel). Noch nicht
umgesetzt, dient als feste Referenz für die Design-Richtung, sobald das
Redesign angegangen wird. Deutsche und englische Fassung inhaltlich
gleichwertig, Englisch an technisch/gestalterisch vagen Stellen
zusätzlich präzisiert.

### Master-Prompt (Deutsch)

Entwickle eine eigenständige, hochwertige visuelle Designwelt für eine
moderne Triathlon-Vereinsapp.

Die App soll nicht wie klassische Vereinsverwaltungssoftware und auch
nicht wie eine generische Fitness-App aussehen. Ihre visuelle Identität
soll unmittelbar aus der Welt eines Triathlon-Wettkampftages entstehen.

*Rahmenbedingungen: Die App wird von einem kleinen, ehrenamtlich
geführten Verein betrieben und ohne Agentur, Illustrator oder
Build-Pipeline direkt in PHP/CSS gepflegt. Die Designwelt darf sich an
der Atmosphäre eines hochwertigen internationalen Triathlon-Events
orientieren – sie muss aber vollständig aus handgebautem, abstraktem
SVG/CSS umsetzbar bleiben (keine Fotos, keine generierten
Illustrationen, kein aufwendiges Asset-System). Lieber wenige, klare,
wiederverwendbare grafische Elemente als eine große Bildwelt, die
niemand im Verein später pflegen kann.*

Die zentrale kreative Leitidee lautet:

„THE CLUB IS THE RACE."

Ein Triathlon besteht nicht nur aus Schwimmen, Radfahren und Laufen.
Schon lange vor dem Start beginnt eine klar strukturierte Journey:
Anmeldung und Meldebüro, Startunterlagen, persönlicher Platz in der
Wechselzone, Organisation durch Race Control und Kampfrichter, der
eigentliche Wettkampf, das Finish sowie Event-Merchandise und
Bekleidung.

Genau diese Welt wird zur visuellen Metapher für die Vereinsapp.

⸻

**01 — REGISTRATION / CHECK-IN**

„Hier beginnt dein Rennen."

Jeder Triathlon beginnt mit der Anmeldung. Der Sportler kommt ins
Meldebüro und erhält sein Race Pack mit Startnummer, Zeitmesschip,
Chipband, Badekappe und weiteren Startunterlagen.

Diese Welt repräsentiert innerhalb der App: Login, Registrierung und
Aufnahmeantrag.

Grafische Inspiration liefern Race Pack, Startnummer, Check-in-Counter,
Akkreditierung, Timing Chip und nummerierte Unterlagen.

Diese Elemente werden nicht fotorealistisch dargestellt, sondern
abstrahiert wie im Branding eines hochwertigen internationalen
Sportevents – umgesetzt als einfache Linien, Formen und Typografie,
nicht als Bildmaterial.

⸻

**02 — MY TRANSITION**

„Dein Platz. Deine Daten. Dein Sport."

Nach dem Check-in erhält jeder Athlet seinen persönlichen Platz in der
Wechselzone. Dort richtet er sich individuell ein: Fahrrad, Helm,
Schuhe, Startnummer, Verpflegung und persönliche Wettkampfausrüstung
liegen an einem klar definierten Platz bereit.

Dieser persönliche Wechselplatz wird zur Metapher für den individuellen
Bereich eines Vereinsmitglieds.

Er repräsentiert: Meine Daten, Mitgliedschaft und Sportlerprofil.

Der Nutzer soll das Gefühl bekommen: „Das ist mein Platz im Verein."

Visuell können Startnummer, Name, nummerierter Wechselplatz,
Fahrradständer, persönliche Equipment-Zone und dezente
Transition-Markierungen aufgegriffen werden.

⸻

**03 — RACE CONTROL**

„Hier läuft alles zusammen – ehrenamtlich, aber verlässlich."

Hinter jedem Triathlon steht eine Organisation, die den gesamten
Wettkampf ermöglicht: Wettkampfbüro, Veranstalter, Helfer und
Kampfrichter koordinieren Teilnehmer, Strecke, Zeitnahme, Kommunikation
und Regeln.

Diese organisatorische Ebene repräsentiert die Geschäftsstelle und
Administration des Vereins.

Dazu gehören beispielsweise Mitgliederverwaltung, Aufnahmeanträge,
E-Mail-Verteiler, Kassenwart und Finanzen, Vereinsdokumente,
Protokolle, Kommunikation und administrative Funktionen.

Race Control ist nicht Teil des Rennens selbst, sondern die
organisatorische Ebene, die dafür sorgt, dass alles funktioniert.
Anders als bei einem großen Event steckt dahinter aber kein bezahltes
Orga-Team mit Kampfrichtern, sondern der eigene, ehrenamtliche
Vorstand. Deshalb darf diese Welt strukturiert, klar und funktional
wirken – nummerierte Bereiche, Checklisten, Statusanzeigen,
Kontrollpunkte, Race-Office-Elemente und klare
Informationshierarchien – aber nicht kühl oder bürokratisch. Der Ton
bleibt der von Leuten, die den Verein mit Herzblut am Laufen halten,
nicht von Offiziellen, die Regeln durchsetzen.

⸻

**04 — THE RACE / CLUB LIFE**

„Jetzt beginnt das Vereinsleben."

Nach Vorbereitung und Organisation beginnt der eigentliche Triathlon.

SWIM → BIKE → RUN → FINISH

Diese Welt steht innerhalb der App für das aktive Vereinsleben:
Trainingskalender, gemeinsame Trainingseinheiten, Wettkämpfe,
Veranstaltungen, Ergebnislisten, Bildergalerien, Vereinsnews und
gemeinsame Erlebnisse.

*Hinweis zum aktuellen Stand: Von diesen Inhalten existiert heute nur
das Sportlerprofil; Trainingskalender, Ergebnislisten, Bildergalerien
und Vereinsnews gibt es als App-Funktion noch nicht. Dieser Abschnitt
beschreibt deshalb bewusst zwei Dinge zugleich: die visuelle Sprache
für das, was schon da ist, UND eine inhaltliche Zielvorstellung für
Features, die erst noch gebaut werden. Beim Umsetzen wird das getrennt
gehalten – die neuen Features entstehen erst, wenn sie eigens
beauftragt werden, nicht automatisch als Nebeneffekt des Redesigns.*

Dieser Bereich darf emotionaler, dynamischer und lebendiger wirken als
die administrativen Bereiche.

Schwimmen wird durch fließende Bewegungen und Wasserlinien
interpretiert.

Radfahren steht für Geschwindigkeit, lange diagonale Linien und
Dynamik.

Laufen steht für Rhythmus, Vorwärtsbewegung und den Weg zum Ziel.

Der Finish-Bereich mit Zielbogen und Medaille symbolisiert gemeinsame
Erfolge, persönliche Entwicklung, Gemeinschaft und erreichte Ziele.

Das Finish ist kein eigener Verwaltungsbereich. Es ist das emotionale
Ziel der gesamten Journey.

⸻

**CLUB GEAR / MERCH**

„Wear the Team."

Zu einem professionellen Triathlon-Event gehört eine eigene
Merchandise- und Bekleidungswelt. Athleten tragen Eventshirts,
Trisuits, Radtrikots, Laufbekleidung, Hoodies und andere Artikel als
sichtbares Zeichen ihrer Zugehörigkeit.

Diese Welt wird innerhalb der App zur Vereinsbekleidung / Club Gear.

Sie steht nicht nur für einen Shop, sondern für: Identität,
Zugehörigkeit, Teamgeist und sichtbare Gemeinschaft.

Die vorhandene Vereinsbekleidung und das digitale Appdesign sollen
erkennbar aus derselben visuellen DNA stammen.

Die charakteristischen dynamischen Linien und Markenfarben dürfen im
Club-Gear-Bereich besonders kraftvoll auftreten.

Club Gear liegt konzeptionell neben der Race Journey. Es begleitet
Mitglieder beim Training, beim Wettkampf und im Vereinsleben und ist
der physische Ausdruck der Marke.

*Hinweis: Auch Club Gear ist heute keine App-Funktion, sondern reale
Vereinsbekleidung außerhalb der App. Als visuelle Referenz (Farben,
Linienführung) fließt sie ein; ein eigener "Shop"-Bereich in der App
ist damit nicht automatisch beauftragt.*

⸻

**DIE RACE LINE – das zentrale grafische Element**

Entwickle eine charakteristische grafische Race Line, die zur
visuellen Signatur der gesamten Vereinsapp wird.

Diese Linie erzählt die Journey des Athleten und verbindet die
verschiedenen Welten miteinander:

CHECK-IN → TRANSITION → SWIM → BIKE → RUN → FINISH

Die Race Line verändert dabei subtil ihre Form und Dynamik.

Beim Check-in beginnt sie präzise, technisch und geordnet.

In der Transition Zone wird sie strukturiert und kann sich an
nummerierten Bereichen orientieren.

Beim Swim wird sie fließend, organisch und wellenartig.

Beim Bike wird sie schnell, langgezogen und diagonal.

Beim Run wird sie rhythmisch, klar und zielgerichtet.

Beim Finish laufen die Elemente wieder zusammen und können einen
abstrakten Zielbogen, eine Ziellinie oder eine Medaille formen.

Diese Race Line muss nicht überall vollständig sichtbar sein. Sie darf
über verschiedene Screens hinweg nur in Ausschnitten erscheinen und
dadurch wie ein wiederkehrender visueller Faden durch die gesamte App
führen.

Technisch wird die Race Line als reines SVG-Linienelement (Pfad, kein
Bild) umgesetzt, damit sie sich pro Screen leicht anpassen, einfärben
und wiederverwenden lässt.

⸻

**MODULARES GRAFIKSYSTEM**

Die visuelle Identität soll aus wenigen wiederverwendbaren
Grundbausteinen bestehen, die ausschließlich mit HTML, CSS und
handgebautem SVG umgesetzt werden können.

Das System besteht im Kern aus: Race Line, Speedlines,
Start-/Streckennummern, Course Markern, reduzierten Line-Icons und
Typografie.

Diese Elemente werden nicht für jeden Screen neu illustriert.
Stattdessen werden dieselben Bausteine je nach App-Bereich anders
kombiniert, skaliert, beschnitten und eingefärbt.

Ziel ist ein kleines visuelles Baukastensystem, mit dem auch
zukünftige Funktionen gestaltet werden können, ohne neue
Illustrationen oder Assets produzieren zu müssen.

Die Wiedererkennbarkeit entsteht nicht durch viele unterschiedliche
Grafiken, sondern durch die konsequente Wiederholung weniger
charakteristischer Elemente.

Diese sechs Bausteine bilden für die erste Umsetzung ein bewusst
geschlossenes Starter-Kit. Erweiterungen erfolgen nur gezielt und
bewusst, nicht beiläufig für einen einzelnen neuen Screen.

Technisch wird der Baukasten als eine zentrale SVG-Sprite-Datei
umgesetzt (`<symbol>`-Definitionen, einmal eingebunden, z.B. über
`includes/kopf.php`), deren Formen per `<use>` referenziert und über
`currentColor` bzw. CSS-Variablen eingefärbt werden – dasselbe
Wiederverwendungsprinzip, das im Code bereits für `includes/kopf.php`
und `vereinNameNowrap()` gilt.

⸻

**VISUELLE STILRICHTUNG**

Orientiere die Gestaltung an der Atmosphäre eines hochwertigen
internationalen Triathlon-Events und nicht an klassischer
Vereinssoftware – umgesetzt jedoch mit den einfachen Mitteln, die ein
ehrenamtlich gepflegtes Projekt tatsächlich tragen kann.

Die Designwelt soll: sportlich, schnell, technisch, hochwertig,
emotional, modern, selbstbewusst und gleichzeitig sehr klar sein.

Nutze viel Weißraum und helle neutrale Flächen.

Darüber liegen dynamische grafische Elemente: diagonale Speedlines,
Race Lines, Streckenmarkierungen, Startnummern und subtile technische
Details.

Die dynamische Sportgrafik soll häufig nur schwach und atmosphärisch im
Hintergrund erscheinen. An emotional wichtigen Stellen darf sie
kräftiger hervortreten.

Die eigentliche Benutzeroberfläche bleibt ruhig, klar und funktional.

Die Triathlon-Welt bildet die emotionale Ebene hinter dem Interface –
sie ist nicht das Interface selbst.

⸻

**FUNKTIONALE KLARHEIT VOR METAPHER**

Die funktionale UI folgt bewusst konventionellen und leicht
verständlichen Bedienmustern. Formulare bleiben Formulare, Listen
bleiben Listen, Navigation bleibt Navigation. Die Triathlon-Metapher
darf Orientierung und Identität schaffen, aber niemals die
Verständlichkeit einer Funktion verschlechtern.

Funktionale Bezeichnungen wie „Meine Daten", „Mitglieder",
„Dokumente" oder „Aufnahmeanträge" werden nicht zwanghaft durch
Triathlon-Begriffe ersetzt. Begriffe wie CHECK-IN, MY TRANSITION oder
RACE CONTROL bilden eine zusätzliche visuelle und emotionale Ebene.

Branding darf überraschen. Bedienung nicht.

Damit diese Absicht beim Umsetzen eindeutig bleibt, gilt konkret:

Die Renn-Sprache darf erscheinen: als Kicker/Overline über der
eigentlichen Überschrift (z.B. klein „CHECK-IN" über groß
„Anmelden"), als Bereichstitel auf Übersichtsseiten, in
Bildunterschriften sowie in atmosphärischen Elementen wie der Race
Line oder Hintergrundgrafik.

Die Renn-Sprache darf niemals ersetzen: Button-Beschriftungen,
Formularfeld-Labels, Fehlermeldungen, Tabellen-Spaltenköpfe, Menü- und
Navigationseinträge sowie `aria-label`- bzw. Screenreader-Texte. Dort
gilt immer der funktionale, deutsche Begriff.

⸻

**MARKENFARBEN**

Verwende die festgelegten Markenfarben:

Pink — #ff3399
Gelb — #fadd06
Blau — #5b9bd5

Ergänzend wird das vorhandene Türkis des V-Logos als ruhige
Primärfarbe für Branding, Typografie und funktionale UI-Elemente
verwendet.

Die Farben sollen nicht einfach drei gleichberechtigte bunte Flächen
bilden. Sie sollen Bewegung erzeugen und wie die grafischen Linien
eines professionellen Race Kits eingesetzt werden.

*Das ersetzt die bisherige Regel eines festen, symmetrischen Verlaufs
mit drei exakt gleich großen 20%-Abschnitten (Pink → Gelb → Blau). Die
drei Farben bleiben, ihr Einsatz wird aber bewusst ungleich, gerichtet
und dynamisch statt gleichverteilt.*

Die bestehende Vereinsbekleidung dient als wichtige visuelle Referenz:
App, Trisuit, Radtrikot und Club Gear sollen sichtbar zur selben
Markenfamilie gehören.

⸻

**TYPOGRAFIE & EVENT-GRAFIK**

Verwende eine moderne, kraftvolle Sporttypografie mit klarer
Hierarchie.

Große Zahlen, Startnummern und kurze technische Begriffe dürfen als
gestalterische Elemente eingesetzt werden:

01 CHECK-IN
02 TRANSITION
RACE CONTROL
SWIM / BIKE / RUN
FINISH
CLUB GEAR

Ergänze diese durch dezente Micro-Typografie, Streckenmarkierungen,
Nummerierungen und technische Informationen, wie sie aus
professionellem Race- und Eventbranding bekannt sind.

Diese Elemente dürfen teilweise angeschnitten, großformatig oder sehr
subtil im Hintergrund erscheinen.

⸻

**BILDSPRACHE**

Menschen und Triathleten dürfen vorkommen, sollen aber nicht die
gesamte Gestaltung dominieren.

Vermeide den Eindruck klassischer Sportwerbung mit großen
fotorealistischen Athleten vor dramatischem Hintergrund.

Bevorzuge stattdessen abstrahierte Silhouetten, Ausschnitte,
Bewegungsfragmente, Equipment und grafische Hinweise auf Triathlon –
ausschließlich als gezeichnete Linien-/Flächengrafik, nicht als Foto
oder KI-generiertes Bild.

Fahrrad, Schwimmbrille, Badekappe, Laufschuh, Startnummer, Timing Chip,
Radständer, Boje, Wechselbox, Zielbogen und Medaille dürfen als
subtile visuelle Referenzen verwendet werden.

⸻

**WAS VERMIEDEN WERDEN SOLL**

Keine klassische Verwaltungssoftware.
Keine Ansammlung großer weißer Dashboard-Kacheln mit Emojis.
Keine generischen Fitness-App-Symbole.
Keine Comic-Illustrationen.
Keine verspielte Kinderoptik.
Keine überladene Gaming-Ästhetik.
Keine permanenten Regenbogenverläufe.
Keine großflächigen grellen Farbflächen.
Keine dominante fotorealistische Sportwerbung.
Keine aufwendige Bild- oder Asset-Pipeline, die über handgebautes
SVG/CSS hinausgeht.
Keine Ersetzung funktionaler Beschriftungen (Buttons, Formularfelder,
Navigation, Fehlermeldungen) durch Renn-Jargon.
Keine neuen Illustrationen/Assets außerhalb des sechsteiligen
Grafik-Baukastens (Race Line, Speedlines, Start-/Streckennummern,
Course Marker, Line-Icons, Typografie).

Die Markenfarben und die Race-Grafik sollen gezielt, hochwertig und
kontrolliert eingesetzt werden.

⸻

**ÜBERGEORDNETES ZIEL**

Entwickle keine bloße Benutzeroberfläche, sondern eine visuelle
Markenwelt für einen Triathlonverein.

Die einzelnen App-Bereiche sollen sich wie unterschiedliche Orte
desselben Triathlon-Events anfühlen:

Registration ist der Eintritt in den Verein.
My Transition ist mein persönlicher Platz.
Race Control organisiert den Verein.
The Race ist das aktive Vereinsleben.
Club Gear macht unsere Identität sichtbar.
Finish steht für das, was wir gemeinsam erreichen.

Alle Welten werden durch dieselbe Race Line, dieselbe Typografie,
dieselben Markenfarben und dieselbe dynamische grafische Sprache
miteinander verbunden – umgesetzt so schlank, dass ein kleiner
ehrenamtlicher Verein sie dauerhaft selbst pflegen kann.

Das Ergebnis soll so eigenständig sein, dass die visuelle Sprache auch
ohne Logo erkennen lässt: Das ist keine beliebige Vereinsapp. Das ist
eine Triathlon-Vereinsapp.

Die emotionale Kernbotschaft lautet: „Gemeinsam ins Ziel."

### Master-Prompt (English)

Develop a distinctive, premium visual design language for a modern
triathlon club app.

The app must not look like conventional club-management software, nor
like a generic fitness app. Its visual identity should be derived
directly from the world of a triathlon race day – translated into
interface design, not illustrated literally.

*Constraints: The app is run by a small, volunteer-led sports club and
maintained directly in PHP/CSS, with no design agency, no illustrator,
and no build pipeline. The design world may draw its atmosphere from a
premium international triathlon event – but every element must be
achievable as hand-authored, abstract SVG/CSS: no photography, no
AI-generated illustration, no asset pipeline requiring external tools.
Favor a small set of clear, reusable graphic primitives (paths,
strokes, gradients, shapes) over a large asset library nobody in the
club can maintain afterward.*

The central creative idea is:

"THE CLUB IS THE RACE."

A triathlon is not only swimming, cycling and running. Long before the
start, a clearly structured journey begins: registration and the race
office, collecting your race documents, your personal spot in the
transition zone, the organization behind race control and officials,
the race itself, and finally the finish line together with event
merchandise and apparel.

This world becomes the visual metaphor for the club app.

⸻

**01 — REGISTRATION / CHECK-IN**

"This is where your race begins."

Every triathlon starts with registration. The athlete goes to the race
office and receives their race pack: bib number, timing chip, chip
strap, swim cap, and further race documents.

Within the app, this world represents: login, sign-up, and the
membership application.

Graphic inspiration comes from the race pack, the bib number, the
check-in counter, accreditation, the timing chip, and numbered
documents.

These elements are never rendered photorealistically. They are
abstracted the way a premium international sports event would brand
them – reduced to line, shape and typography, never to imagery.
Concretely: bib-style numeral badges, a chip-strap tag shape used for
status pills, a check-in-counter motif for the login card.

⸻

**02 — MY TRANSITION**

"Your spot. Your data. Your sport."

After check-in, every athlete is assigned their personal spot in the
transition zone. There they set up individually: bike, helmet, shoes,
bib number, nutrition, and personal race gear are laid out at one
clearly defined spot.

This personal transition spot becomes the metaphor for a club member's
individual area.

It represents: My Data, membership, and the athlete profile.

The user should feel: "This is my spot in the club."

Visually, this can draw on: the bib number, the member's name, a
numbered transition spot, a bike rack, a personal equipment zone, and
subtle transition-zone floor markings (numbered squares, tape lines).

⸻

**03 — RACE CONTROL**

"This is where everything comes together – volunteer-run, but
dependable."

Behind every triathlon stands an organization that makes the whole
event possible: the race office, organizers, volunteers and officials
coordinate participants, the course, timing, communication and rules.

This organizational layer represents the club's back office and
administration.

This includes, for example: membership management, membership
applications, the email distribution list, treasury and finances, club
documents, meeting minutes, communication, and administrative
functions.

Race Control is not part of the race itself – it is the organizational
layer that makes the race possible. Unlike a large commercial event,
though, there is no paid operations team or officials behind it, only
the club's own volunteer board. So this world may look structured,
clear and functional – numbered zones, checklists, status indicators,
checkpoints, race-office elements, and clear information hierarchy –
but never cold or bureaucratic. The tone stays that of people keeping
the club running out of dedication, not officials enforcing rules.

⸻

**04 — THE RACE / CLUB LIFE**

"Now club life begins."

After preparation and organization, the actual triathlon begins.

SWIM → BIKE → RUN → FINISH

Within the app, this world stands for active club life: the training
calendar, shared training sessions, competitions, events, results
lists, photo galleries, club news, and shared experiences.

*Note on current status: of these, only the athlete profile exists
today; a training calendar, results lists, photo galleries and club
news do not yet exist as app features. This section deliberately
describes two things at once: the visual language for what already
exists, AND a content vision for features not yet built. When
implementing, these stay separate – new features are only built once
explicitly commissioned, never as a side effect of the redesign.*

This area may feel more emotional, dynamic and alive than the
administrative areas.

Swimming is interpreted through flowing motion and water lines.

Cycling stands for speed, long diagonal lines and momentum.

Running stands for rhythm, forward motion, and the path to the finish.

The finish area, with its finish arch and medal, symbolizes shared
achievement, personal growth, community, and goals reached.

The finish is not its own administrative section. It is the emotional
destination of the entire journey.

⸻

**CLUB GEAR / MERCH**

"Wear the team."

Every professional triathlon event has its own merchandise and apparel
world. Athletes wear event shirts, tri suits, cycling jerseys, running
gear, hoodies and other items as a visible sign of belonging.

Within the app, this world becomes club apparel / Club Gear.

It stands not just for a shop, but for: identity, belonging, team
spirit, and visible community.

The club's existing apparel and the app's digital design should
visibly come from the same visual DNA.

The characteristic dynamic lines and brand colors may appear
especially powerfully in the Club Gear area.

Club Gear sits conceptually alongside the race journey. It accompanies
members through training, competition and club life, and is the
physical expression of the brand.

*Note: Club Gear is also not an app feature today – it is real club
apparel that exists outside the app. It feeds in as a visual reference
only (colors, line language); this does not automatically commission a
"shop" section within the app.*

⸻

**THE RACE LINE — the central graphic device**

Develop a distinctive graphic Race Line that becomes the visual
signature of the entire club app.

This line tells the athlete's journey and connects the different
worlds:

CHECK-IN → TRANSITION → SWIM → BIKE → RUN → FINISH

The Race Line subtly changes its form and energy along the way.

At check-in, it starts precise, technical and ordered.

In the transition zone, it becomes structured and can align itself to
numbered zones.

During the swim, it turns fluid, organic and wave-like.

During the bike leg, it becomes fast, elongated and diagonal.

During the run, it becomes rhythmic, clear and goal-directed.

At the finish, its elements converge again and may form an abstract
finish arch, a finish line, or a medal shape.

The Race Line does not need to be fully visible everywhere. It may
appear only in fragments across different screens, acting as a
recurring visual thread running through the whole app.

Technically, implement the Race Line as a pure SVG path element (never
a raster image), as a single reusable component whose stroke,
curvature and color can be adapted per screen via CSS/SVG attributes –
one shared definition, many contextual variations.

⸻

**MODULAR GRAPHIC SYSTEM**

The visual identity should be built from a small set of reusable base
components, implementable exclusively with HTML, CSS, and
hand-authored SVG.

The system consists at its core of: the Race Line, speed lines,
bib/course numerals, course markers, reduced line icons, and
typography.

These elements are not re-illustrated for every screen. Instead, the
same components are recombined, scaled, cropped and recolored
differently depending on the app area.

The goal is a small visual toolkit that can also be used to design
future features without producing new illustrations or assets.

Recognizability comes not from many different graphics, but from the
consistent repetition of a few characteristic elements.

For the first implementation, these six components form a
deliberately closed starter kit. Extensions happen only deliberately
and with intent, never casually for a single new screen.

Technically, the toolkit is implemented as one central SVG sprite file
(`<symbol>` definitions, included once, e.g. via `includes/kopf.php`),
whose shapes are referenced via `<use>` and recolored via
`currentColor` or CSS custom properties – the same reuse principle
already applied in the codebase for `includes/kopf.php` and
`vereinNameNowrap()`.

⸻

**VISUAL DIRECTION**

Base the design on the atmosphere of a premium international triathlon
event, not on conventional club-management software – but realized
with the simple means an all-volunteer project can actually sustain.

The design world should be: athletic, fast, technical, premium,
emotional, modern, confident, and at the same time very clear.

Use generous white space and light, neutral surfaces.

Layered on top: dynamic graphic elements – diagonal speed lines, Race
Lines, course markings, bib numbers, and subtle technical detail.

The dynamic sport graphics should mostly appear faint and atmospheric
in the background. At emotionally significant moments, they may step
forward more boldly.

The interface itself stays calm, clear and functional.

The triathlon world forms the emotional layer behind the interface –
it is not the interface itself.

⸻

**FUNCTIONAL CLARITY BEFORE METAPHOR**

The functional UI deliberately follows conventional, easily
understood interaction patterns. Forms stay forms, lists stay lists,
navigation stays navigation. The triathlon metaphor may create
orientation and identity, but must never degrade the clarity of a
function.

Functional labels such as "My Data," "Members," "Documents," or
"Membership Applications" are not forcibly replaced with triathlon
terminology. Terms like CHECK-IN, MY TRANSITION, or RACE CONTROL form
an additional visual and emotional layer.

Branding may surprise. Operation may not.

To keep this intent unambiguous during implementation, specifically:

Race-day language may appear: as a kicker/overline above the actual
heading (e.g., small "CHECK-IN" above a large "Log In"), as a section
title on overview pages, in image captions, and in atmospheric
elements such as the Race Line or background graphics.

Race-day language must never replace: button labels, form field
labels, error messages, table column headers, menu and navigation
entries, or `aria-label`/screen-reader text. These always use the
plain, functional German term.

⸻

**BRAND COLORS**

Use the defined brand colors:

Pink — #ff3399
Yellow — #fadd06
Blue — #5b9bd5

In addition, the V-logo's existing teal is used as a calm primary color
for branding, typography, and functional UI elements.

The colors should not simply form three equal, flat color blocks. They
should generate motion and be deployed the way the graphic lines of a
professional race kit are – directional, asymmetric, layered.

This supersedes the previous rule of a fixed, symmetric gradient with
three exactly equal 20% segments (pink → yellow → blue). The three
colors remain, but their use becomes deliberately uneven, directional
and dynamic rather than evenly distributed.

The club's existing apparel serves as an important visual reference:
the app, tri suit, cycling jersey and Club Gear should visibly belong
to the same brand family.

⸻

**TYPOGRAPHY & EVENT GRAPHICS**

Use a modern, powerful sport typeface with a clear hierarchy.

Large numerals, bib numbers, and short technical terms may be used as
design elements in their own right:

01 CHECK-IN
02 TRANSITION
RACE CONTROL
SWIM / BIKE / RUN
FINISH
CLUB GEAR

Complement these with subtle micro-typography, course markings,
numbering and technical information, in the style known from
professional race and event branding.

These elements may appear partially cropped, oversized, or very
subtle in the background.

⸻

**VISUAL LANGUAGE / IMAGERY**

People and triathletes may appear, but should not dominate the overall
design.

Avoid the impression of conventional sports advertising with large
photorealistic athletes against a dramatic backdrop.

Instead, favor abstracted silhouettes, cropped fragments, motion
fragments, equipment, and graphic references to triathlon – rendered
exclusively as drawn line/shape graphics, never as photography or
AI-generated imagery.

Bike, swim goggles, swim cap, running shoe, bib number, timing chip,
bike rack, buoy, transition box, finish arch and medal may all be used
as subtle visual references.

⸻

**WHAT TO AVOID**

No conventional club-management software look.
No collection of large white dashboard tiles with emoji.
No generic fitness-app icons.
No comic-style illustration.
No playful, childish aesthetic.
No overloaded gaming aesthetic.
No permanent full-bleed rainbow gradients.
No large, garish flat color fields.
No dominant photorealistic sports advertising.
No elaborate image or asset pipeline beyond hand-authored SVG/CSS.
No replacing functional labels (buttons, form fields, navigation,
error messages) with race-day jargon.
No new illustrations/assets outside the six-part graphic toolkit
(Race Line, speed lines, bib/course numerals, course markers, line
icons, typography).

Brand colors and race graphics should be deployed deliberately, with
precision and restraint.

⸻

**OVERARCHING GOAL**

Develop not merely a user interface, but a visual brand world for a
triathlon club.

Each app area should feel like a different location within the same
triathlon event:

Registration is the entry into the club.
My Transition is my personal spot.
Race Control organizes the club.
The Race is active club life.
Club Gear makes our identity visible.
Finish stands for what we achieve together.

All these worlds are connected by the same Race Line, the same
typography, the same brand colors, and the same dynamic graphic
language – implemented lean enough that a small volunteer-run club can
maintain it on its own, indefinitely.

The result should be distinctive enough that the visual language alone
– even without the logo – makes it recognizable: This is not just any
club app. This is a triathlon club app.

The emotional core message is: "Together to the finish."
