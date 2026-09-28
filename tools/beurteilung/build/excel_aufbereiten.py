"""Bereitet eine Notenuebersicht fuer das Beurteilungs-Tool auf.

Ergaenzt drei Blaetter ("Beurteilungen erstellen", "Beurteiler",
"Einstellungen"), eine Markier-Spalte "PDF" auf jedem Notenblatt und
berichtigt bekannte Fehler der Notenuebersicht:
  * Zaehlformeln oben (Anzahl PVB, Notenverteilung) erfassen alle Zeilen,
  * #DIV/0! in der Prozentzeile, wenn noch keine Noten vergeben sind,
  * Hilfsformeln AD:AG zeigen wieder auf die eigene Zeile,
  * Bemerkungen, die als Text-Zahl gespeichert sind, werden echte Zahlen,
  * Blattnamen ohne (veraltete) Personenzahl,
  * Kopierfehler in Ueberschriften (Laufbahn gD, Statusamt gD).

Das Skript ist wiederholbar: laeuft es ueber eine bereits aufbereitete
Datei, werden vorhandene Einstellungen/Beurteiler uebernommen.

Aufruf: python3 excel_aufbereiten.py <notenuebersicht.xlsx> <ziel.xlsx>
"""
import re
import sys
from copy import copy

import openpyxl
from openpyxl.styles import Alignment, Border, Font, PatternFill, Protection, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

START = "Beurteilungen erstellen"
BEURTEILER = "Beurteiler"
EINSTELLUNGEN = "Einstellungen"
LETZTE_ZEILE = 200  # Reichweite der korrigierten Zaehlformeln

# Kuerzel, Amtsbezeichnung maennlich/weiblich, Besoldungsgruppe.
# Ins PDF kommt z.B. "Polizeiobermeister (A8)"; das "Z" der Besoldungsgruppe
# kennzeichnet die Amtszulage. Vom Nutzer als endgueltig bestaetigt – wird bei
# jedem Aufbereiten fest geschrieben und im Blatt "Einstellungen" gesperrt.
AMTSBEZEICHNUNGEN = [
    ("PM", "Polizeimeister", "Polizeimeisterin", "A7"),
    ("POM", "Polizeiobermeister", "Polizeiobermeisterin", "A8"),
    ("PHM", "Polizeihauptmeister", "Polizeihauptmeisterin", "A9m"),
    ("PHMZ", "Polizeihauptmeister", "Polizeihauptmeisterin", "A9mZ"),
    ("PK", "Polizeikommissar", "Polizeikommissarin", "A9g"),
    ("POK", "Polizeioberkommissar", "Polizeioberkommissarin", "A10"),
    ("PHK", "Polizeihauptkommissar", "Polizeihauptkommissarin", "A11"),
    ("PHKZ", "Polizeihauptkommissar", "Polizeihauptkommissarin", "A12"),
    ("EPHK", "Erster Polizeihauptkommissar", "Erste Polizeihauptkommissarin", "A13"),
]
# Namen, die in alten Staenden auf externe Arbeitsmappen zeigten
# ([1]Formeln / [2]Beurteilungen) und die Meldung "Aktualisierung nicht
# moeglich" ausloesen.
EXTERNE_NAMEN = ("Amtsbezeichnung", "Note", "Relevant", "Status", "Statusamt")
AMTSKUERZEL = [k for e in AMTSBEZEICHNUNGEN if not e[0].endswith("Z") for k in (e[0], e[0] + "in")]

STATUSAMT_GD = {  # Kopierfehler aus den mD-Blaettern in A4/A9 der gD-Blaetter
    "PK": ("9g", "Statusamt: Polizeikommissar/-in A 9g"),
    "POK": (10, "Statusamt: Polizeioberkommissar/-in A 10"),
    "PHK": (11, "Statusamt: Polizeihauptkommissar/-in A 11"),
    "PHKZ": (12, "Statusamt: Polizeihauptkommissar/-in A 12"),
}

FUNKTIONEN = "Funktionen"
TAETIGKEITEN = 5  # Vordruck Seite 2: "in der Regel nicht mehr als fuenf je Funktion"

FETT = Font(bold=True)
TITEL = Font(bold=True, size=16, color="1F3864")
ABSCHNITT = Font(bold=True, size=12, color="1F3864")
HINWEIS = Font(italic=True, size=9, color="666666")
EINGABE = PatternFill("solid", fgColor="FFF2CC")
KOPF = PatternFill("solid", fgColor="D9E1F2")
FEST = PatternFill("solid", fgColor="EDEDED")
DUENN = Side(style="thin", color="A6A6A6")
RAHMEN = Border(left=DUENN, right=DUENN, top=DUENN, bottom=DUENN)
DATUM = "DD.MM.YYYY"


def norm(v):
    return re.sub(r"\s+", " ", str(v or "")).strip().lower()


def ist_notenblatt(ws):
    return kopfzeile(ws) is not None


def kopfzeile(ws):
    for r in range(1, min(ws.max_row, 40) + 1):
        werte = [norm(c.value) for c in ws[r][:6]]
        if "lfd.nr." in werte and "name" in werte:
            return r
    return None


def spalten(ws, kopf):
    return {norm(c.value): c.column for c in ws[kopf] if c.value is not None}


def personenzeilen(ws, kopf, sp):
    c_name = sp["name"]
    for r in range(kopf + 1, ws.max_row + 1):
        nr = ws.cell(r, 1).value
        if ws.cell(r, c_name).value and (isinstance(nr, (int, float)) or (isinstance(nr, str) and nr.startswith("="))):
            yield r


# --------------------------------------------------------------------------
# Berichtigungen an den Notenblaettern
# --------------------------------------------------------------------------

def berichtige_notenblatt(ws, protokoll):
    kopf = kopfzeile(ws)
    sp = spalten(ws, kopf)
    erste = kopf + 2  # Zeile nach der Kennziffern-Zeile (A01, A02, ...)
    col_note = get_column_letter(sp["neue rbu"])
    col_name = get_column_letter(sp["name"])

    # Zaehlformeln oben
    if isinstance(ws["C2"].value, str) and ws["C2"].value.startswith("=COUNTA"):
        ws["C2"] = f"=COUNTA({col_name}{erste}:{col_name}{LETZTE_ZEILE})"
    for col in "EFGHIJ":
        z = ws[f"{col}2"]
        if isinstance(z.value, str) and z.value.upper().startswith("=COUNTIF"):
            z.value = f"=COUNTIF(${col_note}${erste}:${col_note}${LETZTE_ZEILE},{col}$1)"
        z3 = ws[f"{col}3"]
        if isinstance(z3.value, str) and re.fullmatch(rf"={col}2/\$D\$2", z3.value):
            z3.value = f"=IFERROR({col}2/$D$2,0)"
    if ws["D3"].value == "=SUM(E3:M3)":
        ws["D3"] = "=SUM(E3:J3)"
    protokoll.append(f"{ws.title}: Zaehlformeln auf Zeilen {erste}-{LETZTE_ZEILE} erweitert, #DIV/0! abgefangen")

    # Hilfsformeln AD:AG (Notenpunkte der Subsidiaermerkmale) und Summe Q:
    # auf einigen Blaettern um eine Zeile verschoben oder auf fremde Zeilen
    # zeigend. Jede Zeile rechnet jetzt nur mit ihren eigenen Noten; ohne
    # Noten bleibt die Summe leer statt #VALUE!.
    muster = re.compile(r'^=IF\(([A-Z]+)\d+="","",VLOOKUP\(\1\d+,(\$A\$\d+:\$B\$\d+),2,FALSE\)\)$')
    ziel_spalten = {"AD": "M", "AE": "N", "AF": "O", "AG": "P"}
    tabelle_ref, zeilen_mit_formel = None, set()
    for r in range(erste, ws.max_row + 1):
        for hilf in ziel_spalten:
            m = muster.match(str(ws[f"{hilf}{r}"].value or ""))
            if m:
                tabelle_ref = tabelle_ref or m.group(2)
                zeilen_mit_formel.add(r)
    repariert = 0
    if tabelle_ref:
        zeilen = zeilen_mit_formel | set(personenzeilen(ws, kopf, sp))
        for r in sorted(zeilen):
            for hilf, quelle in ziel_spalten.items():
                soll = f'=IF({quelle}{r}="","",VLOOKUP({quelle}{r},{tabelle_ref},2,FALSE))'
                if ws[f"{hilf}{r}"].value != soll:
                    ws[f"{hilf}{r}"].value = soll
                    repariert += 1
    for r in range(erste, ws.max_row + 1):
        q = ws[f"Q{r}"]
        if re.fullmatch(r"=AD\d+\+AE\d+\+AF\d+\+AG\d+", str(q.value or "")):
            q.value = f'=IF(COUNT(AD{r}:AG{r})=0,"",SUM(AD{r}:AG{r}))'
        v = ws[f"V{r}"]
        if v.value == f"=SUM(#REF!,U{r})":  # "ges." = Monate + Monate (zur Haelfte)
            v.value = f"=SUM(R{r},U{r})"
            repariert += 1
    if repariert:
        protokoll.append(f"{ws.title}: {repariert} Hilfsformeln (AD:AG) zeigten auf falsche Zeilen – berichtigt, Summe Q ohne #VALUE!")

    # Bemerkungen als Zahl
    if "bemerkungen" in sp:
        n = 0
        for r in personenzeilen(ws, kopf, sp):
            z = ws.cell(r, sp["bemerkungen"])
            if isinstance(z.value, str) and re.fullmatch(r"\d+", z.value.strip()):
                z.value = int(z.value.strip())
                n += 1
        if n:
            protokoll.append(f"{ws.title}: {n} Bemerkungen von Text in Zahl umgewandelt")

    # Auswahllisten, die auf externe Arbeitsmappen zeigten (siehe
    # entferne_externe_verknuepfungen): Amtsbezeichnung -> lokale Liste ueber
    # die ganze Spalte, Statusamt (I6/K6, leer) und Relevant (Teilzeit ist nur
    # ein freier Hinweis fuer die Beurteilenden) entfallen.
    alte = [dv for dv in ws.data_validations.dataValidation if dv.formula1 in EXTERNE_NAMEN]
    for dv in alte:
        ws.data_validations.dataValidation.remove(dv)
    if alte and "amtsbez." in sp:
        col = get_column_letter(sp["amtsbez."])
        dv = DataValidation(type="list", formula1='"' + ",".join(AMTSKUERZEL) + '"', allow_blank=True,
                            showErrorMessage=False)
        dv.add(f"{col}{erste}:{col}{LETZTE_ZEILE}")
        ws.add_data_validation(dv)
    if alte:
        protokoll.append(f"{ws.title}: {len(alte)} Auswahllisten mit externer Quelle ersetzt bzw. entfernt")

    # Markier-Spalte "PDF"
    if not any(k.startswith("pdf") for k in sp):
        col = max(sp.values()) + 1
        while any(ws.cell(r, col).value is not None for r in range(1, ws.max_row + 1)):
            col += 1
        kopfzelle = ws.cell(kopf, col, "PDF")
        vorlage = ws.cell(kopf, sp["name"])
        kopfzelle.font = Font(name=vorlage.font.name, size=vorlage.font.size, bold=True)
        kopfzelle.fill = copy(vorlage.fill)
        kopfzelle.border = copy(vorlage.border)
        kopfzelle.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
        ws.cell(kopf + 1, col, "x = erstellen").font = HINWEIS
        ws.column_dimensions[get_column_letter(col)].width = 7
        sp["pdf"] = col
        dv = DataValidation(type="list", formula1='"x"', allow_blank=True)
        dv.add(f"{get_column_letter(col)}{erste}:{get_column_letter(col)}{LETZTE_ZEILE}")
        ws.add_data_validation(dv)
        for r in range(erste, max(ws.max_row, erste + 40) + 1):
            if ws.cell(r, sp["name"]).value:
                ws.cell(r, col).alignment = Alignment(horizontal="center")
        protokoll.append(f"{ws.title}: Spalte „PDF“ ({get_column_letter(col)}) zum Markieren einzelner Mitarbeiter ergaenzt")
    # Angaben je Person, die ins PDF uebernommen werden
    zusatz = [
        ("Funktion", "Auswahl aus Blatt „Funktionen“", 26, None, f"={FUNKTIONEN}!$A$4:$A$103"),
        ("Kooperationsgespräche", "TT.MM.JJJJ; TT.MM.JJJJ", 24, "@", None),
        ("Gespräch vor Beurteilung", "TT.MM.JJJJ", 13, DATUM, None),
        ("Schwerbehinderung", "ja / nein", 11, None, '"ja,nein"'),
    ]
    for titel, hinweis, breite, fmt, liste_formel in zusatz:
        if norm(titel) in sp:
            continue
        col = freie_spalte(ws, kopf, sp, max(sp.values()) + 1)
        kopfzelle = ws.cell(kopf, col, titel)
        vorlage = ws.cell(kopf, sp["name"])
        kopfzelle.font = Font(name=vorlage.font.name, size=vorlage.font.size, bold=True)
        kopfzelle.fill = copy(vorlage.fill)
        kopfzelle.border = copy(vorlage.border)
        kopfzelle.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
        ws.cell(kopf + 1, col, hinweis).font = HINWEIS
        ws.column_dimensions[get_column_letter(col)].width = breite
        buchstabe = get_column_letter(col)
        if fmt:
            for r in range(erste, LETZTE_ZEILE + 1):
                ws.cell(r, col).number_format = fmt
        if liste_formel:
            dv = DataValidation(type="list", formula1=liste_formel, allow_blank=True)
            dv.add(f"{buchstabe}{erste}:{buchstabe}{LETZTE_ZEILE}")
            ws.add_data_validation(dv)
        sp[norm(titel)] = col
        protokoll.append(f"{ws.title}: Spalte „{titel}“ ({buchstabe}) ergaenzt")

    # Auf vielen Blaettern sind die Spalten rechts von "Teilzeit" ausgeblendet –
    # die Spalten des Tools muessen trotzdem sichtbar sein.
    for k, c in sp.items():
        if k.startswith("pdf") or k in {norm(z[0]) for z in zusatz}:
            if einblenden(ws, c):
                protokoll.append(f"{ws.title}: Spalte „{ws.cell(kopf, c).value}“ war ausgeblendet – eingeblendet")


def freie_spalte(ws, kopf, sp, ab):
    """Erste Spalte ab `ab`, die in Kopfzeile, Hinweiszeile und allen Personenzeilen leer ist."""
    zeilen = [kopf, kopf + 1] + list(personenzeilen(ws, kopf, sp))
    col = ab
    while any(ws.cell(r, col).value is not None for r in zeilen) or col in sp.values():
        col += 1
    return col


def einblenden(ws, col):
    """Blendet genau eine Spalte ein, auch wenn sie Teil eines ausgeblendeten Bereichs ist."""
    for key, dim in list(ws.column_dimensions.items()):
        lo, hi = dim.min or 0, dim.max or 0
        if not (lo <= col <= hi) or not dim.hidden:
            continue
        breite = dim.width
        del ws.column_dimensions[key]
        for a, b, versteckt in ((lo, col - 1, True), (col, col, False), (col + 1, hi, True)):
            if a <= b:
                neu = ws.column_dimensions[get_column_letter(a)]
                neu.min, neu.max, neu.width, neu.hidden = a, b, breite, versteckt
        return True
    return False


def berichtige_kopierfehler(wb, ist_gd, protokoll):
    for ws in wb.worksheets:
        if not ist_notenblatt(ws):
            continue
        basis = blattbasis(ws.title)
        kopf = kopfzeile(ws)
        sp = spalten(ws, kopf)
        if ist_gd:
            for k, c in sp.items():
                if k == "beginn dienstzeit laufbahn md":
                    ws.cell(kopf, c).value = "Beginn Dienstzeit Laufbahn gD"
                    protokoll.append(f"{ws.title}: Ueberschrift „Laufbahn mD“ -> „Laufbahn gD“")
            if basis in STATUSAMT_GD:
                besoldung, text = STATUSAMT_GD[basis]
                if re.match(r"Statusamt: Polizei(ober|haupt)?meister", str(ws["A9"].value or "")):
                    protokoll.append(f"{ws.title}: A9 „{ws['A9'].value}“ -> „{text}“")
                    ws["A9"] = text
                if str(ws["A4"].value) == "9mZ" and besoldung != "9mZ":
                    protokoll.append(f"{ws.title}: A4 „9mZ“ -> „{besoldung}“")
                    ws["A4"] = besoldung


def entferne_externe_verknuepfungen(wb, protokoll):
    """Entfernt Verknuepfungen auf andere Arbeitsmappen samt der Namen dorthin."""
    n = len(wb._external_links)
    wb._external_links = []
    for name in list(wb.defined_names.keys()):
        if name in EXTERNE_NAMEN or "[" in str(wb.defined_names[name].attr_text):
            del wb.defined_names[name]
    if n:
        protokoll.append(f"{n} Verknuepfungen auf externe Arbeitsmappen entfernt (Meldung „Aktualisierung nicht moeglich“)")


def blattbasis(titel):
    return re.sub(r"\s*\(\d+\)\s*$", "", titel).strip()


# --------------------------------------------------------------------------
# Neue Blaetter
# --------------------------------------------------------------------------

def zelle(ws, ref, wert=None, font=None, fill=None, fmt=None, rahmen=False, align=None):
    z = ws[ref]
    if wert is not None:
        z.value = wert
    if font:
        z.font = font
    if fill:
        z.fill = fill
    if fmt:
        z.number_format = fmt
    if rahmen:
        z.border = RAHMEN
    if align:
        z.alignment = align
    return z


def liste(ws, bereich, eintraege):
    dv = DataValidation(type="list", formula1='"' + ",".join(eintraege) + '"', allow_blank=True)
    dv.add(bereich)
    ws.add_data_validation(dv)


def alte_zeilen(wb, name, spalten):
    if name not in wb.sheetnames:
        return []
    ws = wb[name]
    return [[c.value for c in row] for row in ws.iter_rows(min_row=1, max_row=ws.max_row, max_col=spalten)]


def alte_werte(wb, name):
    """Liest Spalte A/B (Beschriftung -> Wert) und Tabellen eines alten Blatts."""
    if name not in wb.sheetnames:
        return {}, []
    ws = wb[name]
    zeilen = [[c.value for c in row] for row in ws.iter_rows(min_row=1, max_row=ws.max_row, max_col=6)]
    return {norm(z[0]): z[1] for z in zeilen if z and z[0]}, zeilen


def tabelle(zeilen, kopf_a, kopf_b):
    for i, z in enumerate(zeilen):
        if norm(z[0]) == kopf_a and norm(z[1]).startswith(kopf_b):
            daten = []
            for w in zeilen[i + 1:]:
                if all(v in (None, "") for v in w[:4]):
                    break
                daten.append(w)
            return daten
    return []


def baue_beurteiler(wb, alt_zeilen):
    ws = wb.create_sheet(BEURTEILER)
    zelle(ws, "A1", "Beurteilende", TITEL)
    zelle(ws, "A2", "Hier alle Erst- und Zweitbeurteilenden hinterlegen. Auf der Startseite werden sie per Auswahlliste gewählt. "
                    "Ins PDF kommt: Amtsbezeichnung, Name, Vorname, Funktion.", HINWEIS)
    kopf = ["Auswahlname (automatisch)", "Amtsbezeichnung", "Name", "Vorname", "Funktion"]
    for i, t in enumerate(kopf):
        zelle(ws, f"{get_column_letter(i + 1)}3", t, FETT, KOPF, rahmen=True)
    alt = [z for z in alt_zeilen[3:] if any(z[1:5])] if alt_zeilen else []
    for r in range(4, 44):
        zelle(ws, f"A{r}", f'=IF(C{r}="","",C{r}&", "&D{r})', rahmen=True)
        werte = alt[r - 4][1:5] if r - 4 < len(alt) else [None] * 4
        for i, col in enumerate("BCDE"):
            zelle(ws, f"{col}{r}", werte[i], fill=EINGABE, rahmen=True)
    for col, b in zip("ABCDE", (28, 18, 20, 18, 34)):
        ws.column_dimensions[col].width = b
    ws.freeze_panes = "A4"
    return ws


def baue_funktionen(wb, alt_zeilen):
    ws = wb.create_sheet(FUNKTIONEN)
    zelle(ws, "A1", "Funktionen und Anforderungsprofile", TITEL)
    zelle(ws, "A2", "Je Funktion eine Zeile. Auf den Notenblättern in Spalte „Funktion“ auswählbar. Ins PDF kommen "
                    "Funktionsbezeichnung und -wertigkeit (Seite 1), die Wertigkeit (Seite 2, Nr. 4.1.2) und die "
                    "prägenden Tätigkeiten (Seite 2) – die Beurteilenden können sie im PDF noch anpassen.", HINWEIS)
    kopf = ["Funktionsbezeichnung", "Wertigkeit"] + [f"Tätigkeit {i}" for i in range(1, TAETIGKEITEN + 1)]
    for i, t in enumerate(kopf):
        zelle(ws, f"{get_column_letter(i + 1)}3", t, FETT, KOPF, rahmen=True)
    alt = [z for z in (alt_zeilen[3:] if alt_zeilen else []) if z and z[0]]
    for r in range(4, 104):
        werte = list(alt[r - 4]) if r - 4 < len(alt) else []
        for i in range(len(kopf)):
            v = werte[i] if i < len(werte) else None
            zelle(ws, f"{get_column_letter(i + 1)}{r}", v, fill=EINGABE, rahmen=True,
                  align=Alignment(wrap_text=True, vertical="top"))
    for i, b in enumerate([32, 12] + [40] * TAETIGKEITEN):
        ws.column_dimensions[get_column_letter(i + 1)].width = b
    ws.freeze_panes = "B4"
    return ws


def sperre_ausser_eingabe(ws):
    """Blattschutz ohne Kennwort: nur die gelben Eingabefelder bleiben frei."""
    for row in ws.iter_rows():
        for z in row:
            if z.fill is not None and z.fill.fgColor is not None and z.fill.fgColor.rgb in ("00FFF2CC", "FFFFF2CC"):
                z.protection = Protection(locked=False)
    ws.protection.sheet = True
    ws.protection.formatColumns = False  # Spaltenbreite bleibt anpassbar


def baue_einstellungen(wb, zuege, alt_werte, alt_zeilen):
    ws = wb.create_sheet(EINSTELLUNGEN)
    zelle(ws, "A1", "Einstellungen", TITEL)
    zelle(ws, "A2", "Feste Angaben, die für alle Beurteilungen gelten. Gelb = Eingabefeld, grau = fest (Blatt ist ohne Kennwort geschützt).", HINWEIS)

    zelle(ws, "A4", "Dienststelle", FETT)
    zelle(ws, "B4", alt_werte.get("dienststelle"), fill=EINGABE, rahmen=True)
    zelle(ws, "C4", "z.B. Bundespolizeiabteilung …, 3. Einsatzhundertschaft – wird mit der Organisationseinheit "
                    "des Zuges (Tabelle darunter) zu „Dienststelle, Organisationseinheit“ zusammengesetzt.", HINWEIS)

    zelle(ws, "A6", "Organisationseinheit je Zug", ABSCHNITT)
    zelle(ws, "A7", "Zug", FETT, KOPF, rahmen=True)
    zelle(ws, "B7", "Organisationseinheit", FETT, KOPF, rahmen=True)
    alt_oe = {norm(z[0]): z[1] for z in tabelle(alt_zeilen, "zug", "organisationseinheit")}
    r = 8
    for zug in zuege:
        anzeige = "(ohne Zug)" if zug in (None, "") else zug
        vorschlag = alt_oe.get(norm(anzeige))
        if vorschlag is None:
            vorschlag = "" if zug in (None, "") else (f"{zug}. Zug" if isinstance(zug, (int, float)) else str(zug))
        zelle(ws, f"A{r}", anzeige, rahmen=True)
        zelle(ws, f"B{r}", vorschlag, fill=EINGABE, rahmen=True)
        r += 1
    for _ in range(4):  # Reserve fuer neue Zuege
        zelle(ws, f"A{r}", None, fill=EINGABE, rahmen=True)
        zelle(ws, f"B{r}", None, fill=EINGABE, rahmen=True)
        r += 1

    r += 1
    zelle(ws, f"A{r}", "Amtsbezeichnungen (Statusamt)", ABSCHNITT)
    r += 1
    for i, t in enumerate(["Kürzel", "Amtsbezeichnung (männlich)", "Amtsbezeichnung (weiblich)", "Besoldungsgruppe"]):
        zelle(ws, f"{get_column_letter(i + 1)}{r}", t, FETT, KOPF, rahmen=True)
    r += 1
    for eintrag in AMTSBEZEICHNUNGEN:
        for col, v in zip("ABCD", eintrag):
            zelle(ws, f"{col}{r}", v, fill=FEST, rahmen=True)
        r += 1
    zelle(ws, f"A{r}", "Ins PDF kommt „Amtsbezeichnung (Besoldungsgruppe)“, z.B. „Polizeiobermeister (A8)“. "
                       "Die weibliche Form wird genommen, wenn Geschlecht = w ist oder das Kürzel auf „in“ endet (z.B. POMin). "
                       "Ist die Amtsbezeichnung leer, gilt der Blattname (z.B. PM). Die Tabelle ist fest und gesperrt.", HINWEIS)
    for col, b in zip("ABCD", (26, 40, 40, 18)):
        ws.column_dimensions[col].width = b
    sperre_ausser_eingabe(ws)
    return ws


def baue_startseite(wb, notenblaetter, zuege, stichtag, alt_werte, alt_zeilen):
    ws = wb.create_sheet(START, 0)
    ws.sheet_view.showGridLines = False
    for w in wb.worksheets:
        w.sheet_view.tabSelected = False
    ws.sheet_view.tabSelected = True
    wb.active = 0

    def wert(label, standard):
        v = alt_werte.get(norm(label))
        return standard if v in (None, "") else v

    zelle(ws, "A1", "Beurteilungen erstellen", TITEL)
    zelle(ws, "A2", "Gelbe Felder ausfüllen, Excel-Datei speichern und schließen (⌘ S, dann ⌘ W), dann Beurteilung.html "
                    "öffnen und diese Datei hineinziehen.", HINWEIS)

    zelle(ws, "A4", "1  Beurteilung", ABSCHNITT)
    felder = [
        ("Beurteilungsart", wert("Beurteilungsart", "Regelbeurteilung"), None),
        ("Anlass (nur bei Anlassbeurteilung)", wert("Anlass (nur bei Anlassbeurteilung)", None), None),
        ("Stichtag", wert("Stichtag", stichtag), DATUM),
        ("Beurteilungszeitraum von", wert("Beurteilungszeitraum von", None), DATUM),
        ("Beurteilungszeitraum bis", wert("Beurteilungszeitraum bis", None), DATUM),
    ]
    for i, (label, v, fmt) in enumerate(felder):
        r = 5 + i
        zelle(ws, f"A{r}", label)
        zelle(ws, f"B{r}", v, fill=EINGABE, fmt=fmt, rahmen=True, align=Alignment(horizontal="left"))
    liste(ws, "B5", ["Regelbeurteilung", "Anlassbeurteilung", "Beurteilungsbeitrag"])

    zelle(ws, "A11", "2  Mitarbeiter", ABSCHNITT)
    optionen = [
        ("Auswahl", wert("Auswahl", "alle Mitarbeiter"), ["alle Mitarbeiter", "nur markierte Mitarbeiter"],
         "„nur markierte“ = auf den Notenblättern in Spalte „PDF“ ein x setzen"),
        ("Mitarbeiter ohne neue Note überspringen", wert("Mitarbeiter ohne neue Note überspringen", "ja"), ["ja", "nein"],
         "Spalte „neue RBU“ leer -> keine Beurteilung erzeugen"),
        ("Noten auch für Zweitbeurteilende/n eintragen", wert("Noten auch für Zweitbeurteilende/n eintragen", "ja"), ["ja", "nein"],
         "nein = nur die Spalte der/des Erstbeurteilenden wird befüllt"),
    ]
    for i, (label, v, werte, hinweis) in enumerate(optionen):
        r = 12 + i
        zelle(ws, f"A{r}", label)
        zelle(ws, f"B{r}", v, fill=EINGABE, rahmen=True)
        zelle(ws, f"C{r}", hinweis, HINWEIS)
        liste(ws, f"B{r}", werte)

    r = 16
    zelle(ws, f"A{r}", "Blatt", FETT, KOPF, rahmen=True)
    zelle(ws, f"B{r}", "einbeziehen", FETT, KOPF, rahmen=True)
    alt_bl = {norm(z[0]): z[1] for z in tabelle(alt_zeilen, "blatt", "einbeziehen")}
    for name in notenblaetter:
        r += 1
        zelle(ws, f"A{r}", name, rahmen=True)
        zelle(ws, f"B{r}", alt_bl.get(norm(name), "ja"), fill=EINGABE, rahmen=True)
        liste(ws, f"B{r}", ["ja", "nein"])

    r += 2
    zelle(ws, f"A{r}", "3  Beurteilende", ABSCHNITT)
    auswahl = f"={BEURTEILER}!$A$4:$A$43"
    for label in ("Erstbeurteilende/r", "Zweitbeurteilende/r"):
        r += 1
        zelle(ws, f"A{r}", label)
        zelle(ws, f"B{r}", alt_werte.get(norm(label)), fill=EINGABE, rahmen=True)
        dv = DataValidation(type="list", formula1=auswahl, allow_blank=True)
        dv.add(f"B{r}")
        ws.add_data_validation(dv)
    zelle(ws, f"C{r - 1}", "Auswahl aus Blatt „Beurteiler“ – gilt für alle, soweit unten je Zug nichts anderes steht", HINWEIS)

    r += 2
    zelle(ws, f"A{r}", "Zug", FETT, KOPF, rahmen=True)
    zelle(ws, f"B{r}", "Erstbeurteilende/r (abweichend)", FETT, KOPF, rahmen=True)
    zelle(ws, f"C{r}", "Zweitbeurteilende/r (abweichend)", FETT, KOPF, rahmen=True)
    alt_zug = {norm(z[0]): z for z in tabelle(alt_zeilen, "zug", "erstbeurteilende")}
    for zug in [z for z in zuege if z not in (None, "")]:
        r += 1
        alt = alt_zug.get(norm(zug), [None, None, None])
        zelle(ws, f"A{r}", zug, rahmen=True, align=Alignment(horizontal="left"))
        for col, v in (("B", alt[1]), ("C", alt[2])):
            zelle(ws, f"{col}{r}", v, fill=EINGABE, rahmen=True)
            dv = DataValidation(type="list", formula1=auswahl, allow_blank=True)
            dv.add(f"{col}{r}")
            ws.add_data_validation(dv)
    if not any(z not in (None, "") for z in zuege):
        r += 1
        zelle(ws, f"A{r}", "(keine Züge in den Notenblättern eingetragen)", HINWEIS)

    r += 2
    zelle(ws, f"A{r}", "4  PDFs erzeugen", ABSCHNITT)
    r += 1
    zelle(ws, f"A{r}", "Diese Excel-Datei speichern und schließen (⌘ S, dann ⌘ W).", FETT)
    zelle(ws, f"A{r + 1}", "Dann Beurteilung.html (im selben Ordner) per Doppelklick öffnen und diese Excel-Datei "
                           "hineinziehen. Solange die Datei in Excel offen ist, ist sie im Browser grau und nicht auswählbar.",
          HINWEIS)

    ws.column_dimensions["A"].width = 44
    ws.column_dimensions["B"].width = 34
    ws.column_dimensions["C"].width = 34
    return ws


def main(quelle, ziel):
    wb = openpyxl.load_workbook(quelle)
    protokoll = []
    alt_start, alt_start_z = alte_werte(wb, START)
    _, alt_beurt_z = alte_werte(wb, BEURTEILER)
    alt_einst, alt_einst_z = alte_werte(wb, EINSTELLUNGEN)
    alt_funkt_z = alte_zeilen(wb, FUNKTIONEN, 2 + TAETIGKEITEN)
    for name in (START, BEURTEILER, EINSTELLUNGEN, FUNKTIONEN):
        if name in wb.sheetnames:
            del wb[name]

    entferne_externe_verknuepfungen(wb, protokoll)
    notenblaetter = [ws for ws in wb.worksheets if ist_notenblatt(ws)]
    if not notenblaetter:
        sys.exit("Keine Notenblaetter (Kopfzeile mit „Lfd.Nr.“ und „Name“) gefunden.")
    ist_gd = any(blattbasis(ws.title) in ("PK", "POK", "PHK", "PHKZ") for ws in notenblaetter)
    berichtige_kopierfehler(wb, ist_gd, protokoll)

    zuege, stichtag = [], None
    for ws in notenblaetter:
        neu = blattbasis(ws.title)
        if neu != ws.title and neu not in wb.sheetnames:
            protokoll.append(f"Blatt „{ws.title}“ -> „{neu}“ (Personenzahl steht in C2)")
            ws.title = neu
        berichtige_notenblatt(ws, protokoll)
        kopf = kopfzeile(ws)
        sp = spalten(ws, kopf)
        for r in personenzeilen(ws, kopf, sp):
            z = ws.cell(r, sp["zug"]).value if "zug" in sp else None
            if z not in zuege:
                zuege.append(z)
        if stichtag is None and norm(ws["R2"].value).startswith("stichtag"):
            stichtag = ws["S2"].value
    zuege.sort(key=lambda z: (z in (None, ""), not isinstance(z, (int, float)), str(z)))

    baue_startseite(wb, [ws.title for ws in notenblaetter], zuege, stichtag, alt_start, alt_start_z)
    baue_beurteiler(wb, alt_beurt_z)
    baue_funktionen(wb, alt_funkt_z)
    baue_einstellungen(wb, zuege, alt_einst, alt_einst_z)
    wb.save(ziel)
    print("\n".join(protokoll))
    print(f"-> {ziel}")


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
