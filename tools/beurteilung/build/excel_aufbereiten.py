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
Ausserdem: Spalten fuer Anlassbeurteilung und Beurteilungsbeitraege, Tabelle
in Reihenfolge des Vordrucks, feste Tabelle fuer 60 Personen mit einheitlichen
Formeln ("n.N." bei fehlenden Angaben), Amtsbezeichnung aus dem Reiternamen,
ausgeblendete Rechenhilfen und Blattschutz (ohne Kennwort) – beschreibbar sind
nur die Eingabezellen.

Das Skript ist wiederholbar: laeuft es ueber eine bereits aufbereitete
Datei, werden vorhandene Einstellungen/Beurteiler uebernommen.

Aufruf: python3 excel_aufbereiten.py <notenuebersicht.xlsx> <ziel.xlsx> [--vorlage <alt.xlsx>]
  --vorlage: fehlen der Datei die Tool-Blaetter (z.B. Rohfassung), werden
             Startseite, Beurteiler, Funktionen und Einstellungen aus dieser
             frueher aufbereiteten Datei uebernommen.
"""
import re
import sys
from copy import copy

import openpyxl
from openpyxl.styles import Alignment, Border, Font, PatternFill, Protection, Side
from openpyxl.formatting.formatting import ConditionalFormattingList
from openpyxl.formatting.rule import FormulaRule
from openpyxl.formula.tokenizer import Token, Tokenizer
from openpyxl.worksheet.hyperlink import Hyperlink
from openpyxl.utils import column_index_from_string, get_column_letter
from openpyxl.worksheet.cell_range import MultiCellRange
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
    ("EPHKZ", "Erster Polizeihauptkommissar", "Erste Polizeihauptkommissarin", "A13Z"),  # vorlaeufig, Bestaetigung offen
]
# Namen, die in alten Staenden auf externe Arbeitsmappen zeigten
# ([1]Formeln / [2]Beurteilungen) und die Meldung "Aktualisierung nicht
# moeglich" ausloesen.
EXTERNE_NAMEN = ("Amtsbezeichnung", "Note", "Relevant", "Status", "Statusamt")
AMTSKUERZEL = [k for e in AMTSBEZEICHNUNGEN if not e[0].endswith("Z") for k in (e[0], e[0] + "in")]

STATUSAMT_GD = {  # Blattkopf A4 (Besoldung) und A9 (Statusamt) je Reiter – Kopierfehler werden berichtigt
    "PM": (7, "Statusamt: Polizeimeister/-in A 7"),
    "POM": (8, "Statusamt: Polizeiobermeister/-in A 8"),
    "PHM": ("9m", "Statusamt: Polizeihauptmeister/-in A 9m"),
    "PHMZ": ("9mZ", "Statusamt: Polizeihauptmeister/-in A 9mZ"),
    "PK": ("9g", "Statusamt: Polizeikommissar/-in A 9g"),
    "POK": (10, "Statusamt: Polizeioberkommissar/-in A 10"),
    "PHK": (11, "Statusamt: Polizeihauptkommissar/-in A 11"),
    "PHKZ": (12, "Statusamt: Polizeihauptkommissar/-in A 12"),
    "EPHK": (13, "Statusamt: Erster Polizeihauptkommissar/-in A 13"),
    "EPHKZ": ("13Z", "Statusamt: Erster Polizeihauptkommissar/-in A 13Z"),
}
# Befoerderungskette je Laufbahn: fehlende Reiter werden als Kopie des
# jeweils vorigen (leer) angelegt
KETTE_GD = ["PK", "POK", "PHK", "PHKZ", "EPHK", "EPHKZ"]

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
DUENN_SCHWARZ = Side(style="thin")
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
    entferne_tabellen_formatierung(ws, kopf)
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

    # Spalten des Tools: in der Reihenfolge, in der die Angaben im Vordruck
    # vorkommen, "PDF" (Markierung) immer als letzte Spalte.
    ordne_tool_spalten(ws, kopf, sp, erste, protokoll)

    # Gesamte Tabelle in die Reihenfolge des Vordrucks bringen
    sortiere_tabelle(ws, kopf, protokoll)

    # Feste Tabelle fuer PERSONEN_JE_BLATT Personen mit einheitlichen Formeln,
    # Rechenhilfen ausgeblendet dahinter, zeichnen und alles ausser den
    # Eingabezellen sperren
    pos = positionen(ws, kopf)
    breite = pos.get("befoerdert", pos["pdf"])  # letzte Spalte der Tabelle
    ende, rechen = vereinheitliche_tabelle(ws, kopf, pos, breite, protokoll)
    gestalte_tabelle(ws, kopf, pos, breite, ende)
    faerbe_tabelle(ws, kopf, pos, breite, ende)
    bedienhilfen(ws, kopf, pos, breite, ende)
    schuetze_notenblatt(ws, kopf, breite, ende, rechen)


NOTENLISTE = '"A1,A2,B1,B2,B3,C"'
TOOL_SPALTEN = [
    # Titel, Hinweis (Zeile unter der Kopfzeile), Breite, Zahlenformat, Auswahlliste
    ("Funktion", "Hauptfunktion (Dienstposten)", 26, None, f"={FUNKTIONEN}!$A$4:$A$103"),
    ("Funktion von", "TT.MM.JJJJ", 12, DATUM, None),
    ("Funktion bis", "TT.MM.JJJJ", 12, DATUM, None),
    ("Funktion 2", "weitere Funktion", 26, None, f"={FUNKTIONEN}!$A$4:$A$103"),
    ("Funktion 2 von", "TT.MM.JJJJ", 12, DATUM, None),
    ("Funktion 2 bis", "TT.MM.JJJJ", 12, DATUM, None),
    ("Funktion 3", "weitere Funktion", 26, None, f"={FUNKTIONEN}!$A$4:$A$103"),
    ("Funktion 3 von", "TT.MM.JJJJ", 12, DATUM, None),
    ("Funktion 3 bis", "TT.MM.JJJJ", 12, DATUM, None),
    ("Kooperationsgespräche", "TT.MM.JJJJ; TT.MM.JJJJ", 24, "@", None),
    ("Gespräch vor Beurteilung", "TT.MM.JJJJ", 13, DATUM, None),
    ("Führungsaufgabe", "ja / nein", 13, None, '"ja,nein"'),
    ("Schwerbehinderung", "ja / nein", 16, None, '"ja,nein"'),
    ("Einverständnis Gespräch Vertrauensperson", "ja / nein", 16, None, '"ja,nein"'),
    ("Gespräch Vertrauensperson am", "TT.MM.JJJJ", 14, DATUM, None),
    ("ALB (x)", "x = vorhanden", 9, None, '"x"'),
    ("ALB von", "TT.MM.JJJJ", 12, DATUM, None),
    ("ALB bis", "TT.MM.JJJJ", 12, DATUM, None),
    ("ALB 1.1", "Note", 7, None, NOTENLISTE),
    ("ALB 2", "Note", 7, None, NOTENLISTE),
    ("ALB 4.2", "Note", 7, None, NOTENLISTE),
    ("ALB 4.3", "Note", 7, None, NOTENLISTE),
] + [t for i in (1, 2, 3) for t in (
    (f"BB {i} (x)", "x = vorhanden", 9, None, '"x"'),
    (f"BB {i} von", "TT.MM.JJJJ", 12, DATUM, None),
    (f"BB {i} bis", "TT.MM.JJJJ", 12, DATUM, None),
)] + [
    ("PDF", "x = erstellen", 11, None, '"x"'),
    ("befördert am", "TT.MM.JJJJ", 13, DATUM, None),
]
MITTIG = {"pdf", "schwerbehinderung", "alb (x)", "bb 1 (x)", "bb 2 (x)", "bb 3 (x)",
          "alb 1.1", "alb 2", "alb 4.2", "alb 4.3", "alb gesamtnote"}


def ordne_tool_spalten(ws, kopf, sp, erste, protokoll):
    """Legt fehlende Tool-Spalten hinten an (Position regelt danach sortiere_tabelle)."""
    if "aktueller alb" in sp:  # Note der Anlassbeurteilung gehoert jetzt in den ALB-Block
        c = sp.pop("aktueller alb")
        ws.cell(kopf, c).value = "ALB Gesamtnote"
        sp["alb gesamtnote"] = c
        dv = DataValidation(type="list", formula1=NOTENLISTE, allow_blank=True)
        dv.add(f"{get_column_letter(c)}{erste}:{get_column_letter(c)}{LETZTE_ZEILE}")
        ws.add_data_validation(dv)
        protokoll.append(f"{ws.title}: „aktueller ALB“ heißt jetzt „ALB Gesamtnote“ (Block Anlassbeurteilung)")
    vorlage = ws.cell(kopf, sp["name"])
    neu = []
    for titel, hinweis, breite, fmt, liste_formel in TOOL_SPALTEN:
        tn = norm(titel)
        vorhanden = next((c for k, c in sp.items() if k == tn or (tn == "pdf" and k.startswith("pdf"))), None)
        if vorhanden:
            einblenden(ws, vorhanden)
            continue
        c = freie_spalte(ws, kopf, sp, max(sp.values()) + 1)
        buchstabe = get_column_letter(c)
        k = ws.cell(kopf, c, titel)
        k.font = Font(name=vorlage.font.name, size=vorlage.font.size, bold=True)
        k.fill = copy(vorlage.fill)
        k.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
        ws.cell(kopf + 1, c, hinweis).font = HINWEIS
        setze_spalte(ws, c, breite, False)
        for r in range(erste, LETZTE_ZEILE + 1):
            z = ws.cell(r, c)
            if fmt:
                z.number_format = fmt
            if tn in MITTIG:
                z.alignment = Alignment(horizontal="center")
        if liste_formel:
            dv = DataValidation(type="list", formula1=liste_formel, allow_blank=True)
            dv.add(f"{buchstabe}{erste}:{buchstabe}{LETZTE_ZEILE}")
            ws.add_data_validation(dv)
        sp[tn] = c
        neu.append(titel)
    if neu:
        protokoll.append(f"{ws.title}: neue Spalten " + ", ".join(neu))


# Zielreihenfolge der Notenblaetter: zuerst alles in der Reihenfolge, in der es
# im Vordruck vorkommt, danach die nur informatorischen Spalten, "PDF" zuletzt.
# (Schluessel, Erkennung ueber die Kopfzeile)
ZIELREIHENFOLGE = [
    ("lfdnr", lambda k: k == "lfd.nr."),
    ("name", lambda k: k == "name"),
    ("vorname", lambda k: k == "vorname"),
    ("geb", lambda k: k.startswith("geb")),
    ("amtsbez", lambda k: k.startswith("amtsbez")),
    ("geschlecht", lambda k: k == "geschlecht"),
    ("ernennung", lambda k: k.startswith("datum der ernennung")),
    ("zug", lambda k: k == "zug"),
    ("funktion", lambda k: k == "funktion"),
    ("f1_von", lambda k: k == "funktion von"),
    ("f1_bis", lambda k: k == "funktion bis"),
    ("f2", lambda k: k == "funktion 2"),
    ("f2_von", lambda k: k == "funktion 2 von"),
    ("f2_bis", lambda k: k == "funktion 2 bis"),
    ("f3", lambda k: k == "funktion 3"),
    ("f3_von", lambda k: k == "funktion 3 von"),
    ("f3_bis", lambda k: k == "funktion 3 bis"),
    ("fuehrung", lambda k: k.startswith("führungsaufgabe")),
    ("koop", lambda k: k.startswith("kooperation")),
    ("gespraech", lambda k: k.startswith("gespräch vor")),
    ("sbh", lambda k: k.startswith("schwerbehind")),
    ("sbh_einv", lambda k: k.startswith("einverständnis")),
    ("sbh_gespr", lambda k: k.startswith("gespräch vertrauensperson")),
    ("letzte_rbu", lambda k: k == "letzte rbu"),
    ("letzte_rbu_amt", None),     # "im Statusamt eines" rechts neben "letzte RBU"
    ("neue_rbu", lambda k: k == "neue rbu"),   # Gesamtnote vor den Teilnoten
    ("n11", lambda k: k.startswith("1.1")),
    ("n2", lambda k: k.startswith("2.")),
    ("n42", lambda k: k.startswith("4.2")),
    ("n43", lambda k: k.startswith("4.3")),
    ("alb_x", lambda k: k == "alb (x)"),
    ("alb_von", lambda k: k == "alb von"),
    ("alb_bis", lambda k: k == "alb bis"),
    ("alb", lambda k: k in ("aktueller alb", "alb gesamtnote")),
    ("alb_amt", None),            # "im Statusamt eines" rechts neben der ALB-Gesamtnote
    ("alb_n11", lambda k: k == "alb 1.1"),
    ("alb_n2", lambda k: k == "alb 2"),
    ("alb_n42", lambda k: k == "alb 4.2"),
    ("alb_n43", lambda k: k == "alb 4.3"),
] + [(f"bb{i}_{t}", (lambda n: (lambda k: k == n))(f"bb {i} " + ("(x)" if t == "x" else t)))
     for i in (1, 2, 3) for t in ("x", "von", "bis")] + [
    ("summe", lambda k: k == "summe"),
    ("monate", lambda k: k == "monate"),
    ("beginn", lambda k: k.startswith("beginn dienstzeit") or k.startswith("datum der verleihung")),
    ("monate_halb", lambda k: k.startswith("monate (zur")),
    ("ges", lambda k: k == "ges."),
    ("bemerkungen", lambda k: k == "bemerkungen"),
    ("teilzeit", lambda k: k == "teilzeit"),
    ("pdf", lambda k: k.startswith("pdf")),
    ("befoerdert", lambda k: k.startswith("befördert")),
]
GRUPPEN_UEBERSCHRIFTEN = [  # Zeile ueber der Kopfzeile: Text, erste und letzte Spalte (Schluessel)
    ("Funktionen", "funktion", "fuehrung"),
    ("RBU", "neue_rbu", "n43"),
    ("Subsidiärmerkmale ", "summe", "bemerkungen"),
    ("Anlassbeurteilung", "alb_x", "alb_n43"),
    ("Beurteilungsbeiträge", "bb1_x", "bb3_bis"),
    ("vorletzte Beurteilung", "letzte_rbu", "letzte_rbu_amt"),
]
ALTE_GRUPPEN = ("letzte Beurteilung",)
_REF = re.compile(r"^(\$?)([A-Z]{1,3})(\$?)(\d+)$")


def _bezug_umsetzen(ref, zuordnung, feste_zeilen, erste_zeile):
    """Setzt einen Zellbezug (A1 oder A1:B2) nach der Spaltenzuordnung um."""
    teile = ref.split(":")
    m = [_REF.match(t) for t in teile]
    if not all(m):
        return ref
    zeile0 = int(m[0].group(4))
    if zeile0 < erste_zeile or zeile0 in feste_zeilen:
        return ref  # Statistikbereich oben bzw. Notentabelle (A1 = 6 ...) bleiben stehen
    spalten = [column_index_from_string(x.group(2)) for x in m]
    neu = [zuordnung.get(c, c) for c in spalten]
    if len(neu) == 2 and neu[1] - neu[0] != spalten[1] - spalten[0]:
        raise ValueError(f"Bereich {ref} würde beim Umsortieren zerrissen")
    return ":".join(f"{x.group(1)}{get_column_letter(c)}{x.group(3)}{x.group(4)}" for x, c in zip(m, neu))


def _formel_umsetzen(formel, zuordnung, feste_zeilen, erste_zeile):
    tok = Tokenizer(formel)
    for t in tok.items:
        if t.type == Token.OPERAND and t.subtype == Token.RANGE and "!" not in t.value:
            t.value = _bezug_umsetzen(t.value, zuordnung, feste_zeilen, erste_zeile)
    return tok.render()


def positionen(ws, kopf):
    """Schluessel aus ZIELREIHENFOLGE -> Spalte, erkannt ueber die Kopfzeile."""
    sp_liste = [(c.column, norm(c.value)) for c in ws[kopf] if c.value is not None]
    pos = {}
    for schluessel, erkennung in ZIELREIHENFOLGE:
        if erkennung is None:
            continue
        for c, k in sp_liste:
            if erkennung(k) and c not in pos.values():
                pos[schluessel] = c
                break
    for basis in ("alb", "letzte_rbu"):
        if basis in pos and norm(ws.cell(kopf, pos[basis] + 1).value).startswith("im status"):
            pos[basis + "_amt"] = pos[basis] + 1
    return pos


def sortiere_tabelle(ws, kopf, protokoll):
    """Sortiert die Spalten der Tabelle nach ZIELREIHENFOLGE und schreibt alle Bezuege um."""
    alt = positionen(ws, kopf)
    reihenfolge = [s for s, _ in ZIELREIHENFOLGE if s in alt]
    zuordnung = {alt[s]: i + 1 for i, s in enumerate(reihenfolge)}
    tabellenbreite = len(reihenfolge)
    # Alle uebrigen Spalten (ausgeblendete Hilfsformeln, Punktetabelle, Unbekanntes)
    # behalten ihre Reihenfolge und kommen dahinter.
    breite = max(ws.max_column, max(zuordnung))
    for c in range(1, breite + 1):
        if c not in zuordnung:
            zuordnung[c] = max(zuordnung.values()) + 1
    if all(a == n for a, n in zuordnung.items()):
        return  # schon sortiert

    erste_zeile = kopf - 1  # Zeile der Gruppenueberschriften
    feste_zeilen = {r for r in range(kopf + 1, ws.max_row + 1)
                    if ws.cell(r, 1).value in ("A1", "A2", "B1", "B2", "B3", "C")
                    and isinstance(ws.cell(r, 2).value, (int, float))}

    # 0) Punktetabelle (A1 = 6 ... C = 1) aus den Spalten A/B in ausgeblendete
    #    Hilfsspalten rechts verlegen – Spalte B wird sonst zur Namensspalte.
    if feste_zeilen:
        ziel_a = max(ws.max_column, 34) + 2
        for r in sorted(feste_zeilen):
            for alt_c, neu_c in ((1, ziel_a), (2, ziel_a + 1)):
                q, z = ws.cell(r, alt_c), ws.cell(r, neu_c)
                z.value, z._style = q.value, copy(q._style)
                q.value = None
        lo, hi = min(feste_zeilen), max(feste_zeilen)
        alt_ref = re.compile(rf"\$A\${lo}:\$B\${hi}")
        neu_ref = f"${get_column_letter(ziel_a)}${lo}:${get_column_letter(ziel_a + 1)}${hi}"
        for row in ws.iter_rows():
            for z in row:
                if isinstance(z.value, str) and z.value.startswith("="):
                    z.value = alt_ref.sub(neu_ref, z.value)
        for c in (ziel_a, ziel_a + 1):
            d = ws.column_dimensions[get_column_letter(c)]
            d.min = d.max = c
            d.hidden = True
        ws.cell(lo - 1, ziel_a, "Notenpunkte").font = HINWEIS
        protokoll.append(f"{ws.title}: Punktetabelle A{lo}:B{hi} nach {neu_ref.replace('$', '')} (ausgeblendet) verlegt")
        feste_zeilen = set()

    # 1) Zusammengefasste Zellen im umzusortierenden Bereich loesen
    for m in list(ws.merged_cells.ranges):
        if m.min_row >= erste_zeile and m.min_col <= breite:
            ws.unmerge_cells(str(m))

    # 2) Zellen (Wert, Format, Kommentar) umsetzen
    zeilen = [r for r in range(erste_zeile, ws.max_row + 1) if r not in feste_zeilen]
    for r in zeilen:
        inhalt = {}
        for c in range(1, breite + 1):
            z = ws.cell(r, c)
            inhalt[c] = (z.value, z._style, z.comment, z.hyperlink)
        for c, (wert, stil, kommentar, link) in inhalt.items():
            z = ws.cell(r, zuordnung[c])
            z.value, z._style = wert, copy(stil)
            z.comment = None
            if kommentar is not None:
                z.comment = kommentar
            z.hyperlink = link

    # 3) Formeln im ganzen Blatt umschreiben
    for row in ws.iter_rows():
        for z in row:
            if isinstance(z.value, str) and z.value.startswith("="):
                z.value = _formel_umsetzen(z.value, zuordnung, feste_zeilen, erste_zeile)

    # 4) Auswahllisten und bedingte Formatierung (Bereiche)
    for dv in ws.data_validations.dataValidation:
        dv.sqref = MultiCellRange(" ".join(_bezug_umsetzen(str(r), zuordnung, feste_zeilen, erste_zeile)
                                          for r in dv.sqref.ranges))
    for cf in ws.conditional_formatting:
        for r in cf.sqref.ranges:
            if r.min_row >= erste_zeile:
                sys.exit(f"{ws.title}: bedingte Formatierung in der Tabelle ({r}) – nicht vorgesehen")

    # 5) Spaltenbreiten/-sichtbarkeit
    alt_dim = {}
    for c in range(1, breite + 1):
        buchst = get_column_letter(c)
        d = next((d for d in ws.column_dimensions.values() if (d.min or 0) <= c <= (d.max or 0)), None)
        alt_dim[c] = (d.width if d else None, bool(d and d.hidden))
    for key, d in list(ws.column_dimensions.items()):
        if (d.min or 0) <= breite:
            if (d.max or 0) > breite:  # Bereich ragt ueber die Tabelle hinaus: Rest behalten
                rest = ws.column_dimensions[get_column_letter(breite + 1)]
                rest.min, rest.max, rest.width, rest.hidden = breite + 1, d.max, d.width, d.hidden
            del ws.column_dimensions[key]
    for c, (w, versteckt) in alt_dim.items():
        d = ws.column_dimensions[get_column_letter(zuordnung[c])]
        d.min = d.max = zuordnung[c]
        if w is not None:
            d.width = w
        d.hidden = versteckt

    # 6) Gruppenueberschriften neu zusammenfassen
    neu = {s: i + 1 for i, s in enumerate(reihenfolge)}
    bekannte_texte = {norm(t) for t, _, _ in GRUPPEN_UEBERSCHRIFTEN} | {norm(t) for t in ALTE_GRUPPEN}
    for c in range(1, breite + 1):
        if norm(ws.cell(erste_zeile, c).value) in bekannte_texte:
            ws.cell(erste_zeile, c).value = None
    for text, von, bis in GRUPPEN_UEBERSCHRIFTEN:
        if von in neu and bis in neu:
            a, b = neu[von], neu[bis]
            for c in range(a, b + 1):
                if ws.cell(erste_zeile, c).value == text:
                    ws.cell(erste_zeile, c).value = None
            ws.cell(erste_zeile, a).value = text
            if b > a:
                ws.merge_cells(start_row=erste_zeile, start_column=a, end_row=erste_zeile, end_column=b)

    protokoll.append(f"{ws.title}: Spalten nach Reihenfolge der Beurteilung sortiert: "
                     + ", ".join(str(ws.cell(kopf, c).value).replace("\n", " ").strip() for c in range(1, tabellenbreite + 1)))


LINKSBUENDIG = ("name", "vorname", "funktion", "koop", "bemerkungen")
ZEILE_PT = 13.0  # Hoehe je Textzeile (Arial 10)


def gestalte_tabelle(ws, kopf, pos, breite, ende=None):
    """Linien (duenn innen, kraeftig aussen/an Gruppengrenzen), Zeilenumbruch in allen
    Zellen und an den Inhalt angepasste Zeilenhoehen – nichts ragt in Nachbarzellen."""
    grenzen = {pos[s] for s in ("name", "funktion", "koop", "letzte_rbu", "neue_rbu", "alb_x", "bb1_x", "summe",
                                "teilzeit", "pdf")
               if s in pos}
    ende = ende or letzte_tabellenzeile(ws, kopf, {"name": pos["name"]})
    links_spalten = {pos[s] for s in LINKSBUENDIG if s in pos}
    for r in range(kopf, ende + 1):
        for c in range(1, breite + 1):
            z = ws.cell(r, c)
            links = Side(style="medium") if c == 1 or c in grenzen else DUENN_SCHWARZ
            rechts = Side(style="medium") if c == breite or c + 1 in grenzen else DUENN_SCHWARZ
            oben = Side(style="medium") if r == kopf else DUENN_SCHWARZ
            unten = Side(style="medium") if r in (kopf, ende) else DUENN_SCHWARZ
            z.border = Border(left=links, right=rechts, top=oben, bottom=unten)
            if r >= kopf + 2:
                al = copy(z.alignment)
                horizontal = "left" if c in links_spalten else al.horizontal
                z.alignment = Alignment(horizontal=horizontal, vertical="center", wrap_text=True,
                                        text_rotation=al.text_rotation, indent=al.indent)
    # Zeilenhoehe aus dem laengsten Text der Zeile schaetzen (Excel passt bei
    # gespeicherten Hoehen nicht selbst an)
    breiten = {c: (ws.column_dimensions[get_column_letter(c)].width or 8.43) for c in range(1, breite + 1)}
    for r in range(kopf + 2, ende + 1):
        zeilen = 1
        for c in range(1, breite + 1):
            v = ws.cell(r, c).value
            if isinstance(v, str) and not v.startswith("="):
                pro_zeile = max(1, int(breiten[c] * 1.3))
                zeilen = max(zeilen, sum(max(1, -(-len(t) // pro_zeile)) for t in v.split("\n")))
        d = ws.row_dimensions[r]
        if zeilen > 1:
            d.height = round(zeilen * ZEILE_PT + 2, 1)
        else:
            d.height = None
    if ws.auto_filter.ref:
        ws.auto_filter.ref = f"A{kopf + 1}:{get_column_letter(breite)}{ende}"


KEIN_DATUM = "n.N."
PERSONEN_JE_BLATT = 60
PUNKTE = [("A1", 6), ("A2", 5), ("B1", 4), ("B2", 3), ("B3", 2), ("C", 1)]  # Subsidiaermerkmal "Summe"


def vereinheitliche_tabelle(ws, kopf, pos, breite, protokoll):
    """Feste Tabelle mit PERSONEN_JE_BLATT Zeilen. Jede Rechenspalte bekommt in
    jeder Zeile dieselbe Formel; ohne Namen bleibt die Zeile leer, fehlt eine
    Angabe (Datum, Stichtag, Note), steht "n.N.". Die Rechenhilfen (Notenpunkte,
    Punktetabelle) werden hinter der Tabelle einheitlich neu angelegt und
    ausgeblendet. Gibt letzte Tabellenzeile und Rechenspalten zurueck."""
    L = get_column_letter
    erste = kopf + 2
    ende = erste + PERSONEN_JE_BLATT - 1
    alt_ende = letzte_tabellenzeile(ws, kopf, {"name": pos["name"]})
    N = L(pos["name"])
    personen = [r for r in range(erste, ws.max_row + 1) if ws.cell(r, pos["name"]).value not in (None, "")]
    if personen and max(personen) > ende:
        sys.exit(f"{ws.title}: Personen bis Zeile {max(personen)} – die Tabelle fasst {PERSONEN_JE_BLATT} "
                 f"(Zeilen {erste}-{ende}).")
    stichtag = next((z.column + 1 for z in ws[2] if norm(z.value).startswith("stichtag")), None)
    if stichtag is None:
        sys.exit(f"{ws.title}: „Stichtag:“ in Zeile 2 nicht gefunden.")
    ST = f"${L(stichtag)}$2"

    # Unterhalb der Tabelle: alte Formeln weg, Daten dort waeren ein Fehler
    for r in range(ende + 1, ws.max_row + 1):
        for c in range(1, breite + 1):
            z = ws.cell(r, c)
            if isinstance(z.value, str) and z.value.startswith("="):
                z.value = None
            elif z.value is not None:
                sys.exit(f"{ws.title}: {z.coordinate} unter der Tabelle enthält „{z.value}“ – bitte prüfen.")
            z.style = "Normal"
        ws.row_dimensions[r].height = None

    # Rechenhilfen hinter der Tabelle einheitlich neu anlegen
    for m in list(ws.merged_cells.ranges):
        if m.max_col > breite:
            ws.unmerge_cells(str(m))
    for row in ws.iter_rows(min_col=breite + 1):
        for z in row:
            z.value = None
            z.style = "Normal"
    for key, d in list(ws.column_dimensions.items()):
        if (d.max or 0) > breite:
            del ws.column_dimensions[key]
    hilfe = {k: breite + 1 + i for i, k in enumerate(("n11", "n2", "n42", "n43"))}
    t_note, t_punkte = breite + 6, breite + 7
    TAB = f"${L(t_note)}${erste}:${L(t_punkte)}${erste + len(PUNKTE) - 1}"
    for k, c in hilfe.items():
        ws.cell(kopf, c, "Punkte " + str(ws.cell(kopf, pos[k]).value).split()[0].rstrip(".")).font = HINWEIS
    ws.cell(kopf, t_note, "Note").font = HINWEIS
    ws.cell(kopf, t_punkte, "Punkte").font = HINWEIS
    for i, (note, punkte) in enumerate(PUNKTE):
        ws.cell(erste + i, t_note, note)
        ws.cell(erste + i, t_punkte, punkte)
    for c in range(breite + 1, t_punkte + 1):
        setze_spalte(ws, c, 10, True)
    for c in range(1, breite + 1):  # die Tabelle selbst ist vollstaendig sichtbar
        d = next((d for d in ws.column_dimensions.values() if (d.min or 0) <= c <= (d.max or 0)), None)
        if d is not None and d.hidden:
            setze_spalte(ws, c, d.width, False)

    kuerzel = blattbasis(ws.title)
    G = L(pos["geschlecht"])
    H1, H4 = L(hilfe["n11"]), L(hilfe["n43"])
    E, B = L(pos["ernennung"]), L(pos["beginn"])
    M, MH = L(pos["monate"]), L(pos["monate_halb"])

    def monate(datum, r, faktor=""):
        return (f'IFERROR(IF(OR({datum}{r}="",{ST}="",{datum}{r}>{ST}),"{KEIN_DATUM}",'
                f'DATEDIF({datum}{r},{ST},"M"){faktor}),"{KEIN_DATUM}")')

    formeln = {
        "lfdnr": lambda r: f"COUNTA(${N}${erste}:${N}{r})",
        "amtsbez": lambda r: f'"{kuerzel}"&IF({G}{r}="w","in","")',
        "summe": lambda r: f'IF(COUNT({H1}{r}:{H4}{r})<4,"{KEIN_DATUM}",SUM({H1}{r}:{H4}{r}))',
        "monate": lambda r: monate(E, r),
        "monate_halb": lambda r: monate(B, r, "/2"),
        "ges": lambda r: f'IF(COUNT({M}{r},{MH}{r})<2,"{KEIN_DATUM}",{M}{r}+{MH}{r})',
    }
    for k, c in hilfe.items():
        formeln[("hilfe", k)] = (lambda q: lambda r: f'IFERROR(VLOOKUP({q}{r},{TAB},2,FALSE),"")')(L(pos[k]))
    spalte = {k: (hilfe[k[1]] if isinstance(k, tuple) else pos[k]) for k in formeln}

    geaendert = 0
    for r in range(erste, ende + 1):
        if r > alt_ende:  # neue Tabellenzeilen: Format der ersten Zeile
            for c in range(1, breite + 1):
                ws.cell(r, c)._style = copy(ws.cell(erste, c)._style)
        for k, f in formeln.items():
            z = ws.cell(r, spalte[k])
            neu = f'=IF(${N}{r}="","",{f(r)})'
            if k == "amtsbez" and z.value not in (None, "") and not str(z.value).startswith("="):
                soll = kuerzel + ("in" if norm(ws.cell(r, pos["geschlecht"]).value) == "w" else "")
                if str(z.value).strip() != soll:
                    protokoll.append(f"{ws.title}: {ws.cell(r, pos['name']).value}: Amtsbez. „{z.value}“ -> „{soll}“ (aus Reiter/Geschlecht)")
            if z.value != neu:
                geaendert += spalte[k] <= breite and z.value is not None
                z.value = neu

    # Statistik oben auf genau die Tabellenzeilen
    NOTE = L(pos["neue_rbu"])
    for r in (2, 3):
        for c in range(1, 11):
            z = ws.cell(r, c)
            v = str(z.value or "")
            if v.upper().startswith("=COUNTA("):
                z.value = f"=COUNTA({N}{erste}:{N}{ende})"
            elif v.upper().startswith("=COUNTIF("):
                z.value = f"=COUNTIF(${NOTE}${erste}:${NOTE}${ende},{L(c)}$1)"

    # Amtsbezeichnung ist jetzt berechnet: keine Auswahlliste mehr, Geschlecht m/w
    amt = pos["amtsbez"]
    for dv in list(ws.data_validations.dataValidation):
        rest = [x for x in dv.sqref.ranges if not (x.min_col <= amt <= x.max_col)]
        if len(rest) != len(dv.sqref.ranges):
            if rest:
                dv.sqref = MultiCellRange(" ".join(map(str, rest)))
            else:
                ws.data_validations.dataValidation.remove(dv)
    g = pos["geschlecht"]
    if not any(x.min_col <= g <= x.max_col for dv in ws.data_validations.dataValidation for x in dv.sqref.ranges):
        dv = DataValidation(type="list", formula1='"m,w"', allow_blank=True)
        dv.add(f"{G}{erste}:{G}{ende}")
        ws.add_data_validation(dv)

    if geaendert:
        protokoll.append(f"{ws.title}: Tabelle für {PERSONEN_JE_BLATT} Personen (Zeilen {erste}-{ende}), "
                         f"{geaendert} abweichende Formeln vereinheitlicht")
    return ende, {pos[k] for k in ("lfdnr", "amtsbez", "summe", "monate", "monate_halb", "ges")}


def schuetze_notenblatt(ws, kopf, breite, ende, rechen):
    """Blattschutz ohne Kennwort: beschreibbar sind nur die Eingabezellen der
    Tabelle (keine Formeln) und der Stichtag oben; Kopf, Statistik, Rechen- und
    Hilfsspalten sind gesperrt. Spaltenbreite, Zeilenhoehe, Formatieren und
    Filtern bleiben erlaubt."""
    alles_sperren(ws)
    offen = Protection(locked=False)
    for r in range(kopf + 2, ende + 1):
        for c in range(1, breite + 1):
            if c not in rechen:
                ws.cell(r, c).protection = offen
    for z in ws[2]:
        if norm(z.value).startswith("stichtag"):
            ws.cell(2, z.column + 1).protection = offen
    erlaube(ws)


def alles_sperren(ws):
    """Zellen, die im Original schon als "nicht gesperrt" formatiert waren, wieder sperren."""
    gesperrt = Protection(locked=True)
    for row in ws.iter_rows():
        for z in row:
            if not z.protection.locked:
                z.protection = gesperrt


def erlaube(ws):
    ws.protection.sheet = True
    ws.protection.formatCells = False    # False = trotz Schutz erlaubt
    ws.protection.formatColumns = False
    ws.protection.formatRows = False
    ws.protection.autoFilter = False
    ws.protection.objects = True         # Knoepfe nicht verschieb-/loeschbar (bleiben klickbar)


OHNE_BEDEUTUNG = {"FF92D050", "FFCCFFCC", "FFFFFF00", "FFFF0000"}  # Gruen, Hellgruen, Gelb, Rot aus alten Staenden
BLOCKFARBEN = {"alb": "DDEBF7", "bb": "E4DFEC"}  # Anlassbeurteilung hellblau, Beurteilungsbeitraege hellviolett


def faerbe_tabelle(ws, kopf, pos, breite, ende):
    """Entfernt zufaellige Markierungsfarben aus den Datenzeilen, unterlegt die
    Bloecke Anlassbeurteilung/Beurteilungsbeitraege in den Ueberschriften und
    setzt die Gruppenueberschriften mittig."""
    for r in range(kopf + 2, ende + 1):
        for c in range(1, breite + 1):
            z = ws.cell(r, c)
            if z.fill is not None and z.fill.fill_type == "solid" and z.fill.fgColor.type == "rgb" \
                    and z.fill.fgColor.rgb in OHNE_BEDEUTUNG:
                z.fill = PatternFill(fill_type=None)
    bloecke = {"alb": ("alb_x", "alb_n43"), "bb": ("bb1_x", "bb3_bis")}
    for name, (von, bis) in bloecke.items():
        if von in pos and bis in pos:
            fuellung = PatternFill("solid", fgColor=BLOCKFARBEN[name])
            for c in range(pos[von], pos[bis] + 1):
                for r in (kopf - 1, kopf):
                    ws.cell(r, c).fill = fuellung
    if "neue_rbu" in pos and "n43" in pos:  # RBU-Block: Farbe der Teilnoten-Ueberschriften
        fuellung = copy(ws.cell(kopf, pos["n11"]).fill)
        for c in range(pos["neue_rbu"], pos["n43"] + 1):
            ws.cell(kopf - 1, c).fill = copy(fuellung)
    # Legende "Spalten mit Formeln hinterlegt" (hellgruen in der Ueberschrift):
    # auch Lfd.Nr. und Amtsbez. rechnen inzwischen selbst
    vorlage = ws.cell(kopf, pos["summe"]).fill if "summe" in pos else None
    if vorlage is not None and vorlage.fill_type == "solid":
        for k in ("lfdnr", "amtsbez"):
            if k in pos:
                ws.cell(kopf, pos[k]).fill = copy(vorlage)
    for m in ws.merged_cells.ranges:
        if m.min_row == kopf - 1 and m.max_row == kopf - 1:
            z = ws.cell(m.min_row, m.min_col)
            z.alignment = Alignment(horizontal="center", vertical="center")


def letzte_tabellenzeile(ws, kopf, sp):
    """Letzte Zeile, bis zu der die vorhandene Tabelle (Spalte Name) umrandet ist."""
    c = sp["name"]
    letzte = kopf
    for r in range(kopf, min(ws.max_row, LETZTE_ZEILE) + 1):
        b = ws.cell(r, c).border
        if any(getattr(b, s).style for s in ("left", "right", "top", "bottom")):
            letzte = r
    return letzte


def freie_spalte(ws, kopf, sp, ab):
    """Erste Spalte ab `ab`, die in Kopfzeile, Hinweiszeile und allen Personenzeilen leer ist."""
    col = ab
    while any(ws.cell(r, col).value is not None for r in range(1, ws.max_row + 1)) or col in sp.values():
        col += 1
    return col


def einblenden(ws, col):
    """Blendet genau eine Spalte ein, auch wenn sie Teil eines ausgeblendeten Bereichs ist."""
    d = next((d for d in ws.column_dimensions.values() if (d.min or 0) <= col <= (d.max or 0)), None)
    if d is None or not d.hidden:
        return False
    setze_spalte(ws, col, d.width, False)
    return True


def setze_spalte(ws, col, breite, versteckt):
    """Breite/Sichtbarkeit genau einer Spalte setzen; ein Bereich, der sie
    enthaelt (z.B. AA:AI ausgeblendet), wird dafuer aufgeteilt."""
    for key, dim in list(ws.column_dimensions.items()):
        lo, hi = dim.min or 0, dim.max or 0
        if not (lo <= col <= hi):
            continue
        w, h = dim.width, dim.hidden
        del ws.column_dimensions[key]
        for a, b in ((lo, col - 1), (col + 1, hi)):
            if a <= b:
                neu = ws.column_dimensions[get_column_letter(a)]
                neu.min, neu.max, neu.width, neu.hidden = a, b, w, h
    d = ws.column_dimensions[get_column_letter(col)]
    d.min = d.max = col
    if breite is not None:
        d.width = breite
    d.hidden = versteckt


def berichtige_kopierfehler(wb, ist_gd, protokoll):
    for ws in wb.worksheets:
        if not ist_notenblatt(ws):
            continue
        basis = blattbasis(ws.title)
        kopf = kopfzeile(ws)
        sp = spalten(ws, kopf)
        kuerzel = {e[0] for e in AMTSBEZEICHNUNGEN}
        if ws["A2"].value in kuerzel and ws["A2"].value != basis and basis in kuerzel:
            protokoll.append(f"{ws.title}: A2 „{ws['A2'].value}“ -> „{basis}“")
            ws["A2"] = basis
        if basis in STATUSAMT_GD:
            besoldung, text = STATUSAMT_GD[basis]
            if str(ws["A9"].value or "").startswith("Statusamt:") and ws["A9"].value != text:
                protokoll.append(f"{ws.title}: A9 „{ws['A9'].value}“ -> „{text}“")
                ws["A9"] = text
            if ws["A4"].value is not None and str(ws["A4"].value) != str(besoldung):
                protokoll.append(f"{ws.title}: A4 „{ws['A4'].value}“ -> „{besoldung}“")
                ws["A4"] = besoldung
        if ist_gd:
            for k, c in sp.items():
                if k == "beginn dienstzeit laufbahn md":
                    ws.cell(kopf, c).value = "Beginn Dienstzeit Laufbahn gD"
                    protokoll.append(f"{ws.title}: Ueberschrift „Laufbahn mD“ -> „Laufbahn gD“")


def ergaenze_reiter(wb, notenblaetter, kette, protokoll):
    """Legt fehlende Reiter der Befoerderungskette als leere Kopie des vorigen an
    (gleicher Aufbau, Formeln, Auswahllisten) und ordnet die Reiter nach der Kette."""
    for i, name in enumerate(kette):
        if name in wb.sheetnames or i == 0 or kette[i - 1] not in wb.sheetnames:
            continue
        quelle = wb[kette[i - 1]]
        ws = wb.copy_worksheet(quelle)
        ws.title = name
        for dv in quelle.data_validations.dataValidation:
            ws.add_data_validation(copy(dv))
        ws.freeze_panes = quelle.freeze_panes
        ws.sheet_view.zoomScale = quelle.sheet_view.zoomScale
        kopf = kopfzeile(ws)
        pos = positionen(ws, kopf)
        breite = pos.get("befoerdert", pos["pdf"])
        for r in range(kopf + 2, kopf + 2 + PERSONEN_JE_BLATT):
            for c in range(1, breite + 1):
                z = ws.cell(r, c)
                if not (isinstance(z.value, str) and z.value.startswith("=")):
                    z.value = None
            ws.row_dimensions[r].height = None
        besoldung, text = STATUSAMT_GD[name]
        ws["A2"], ws["A4"], ws["A9"] = name, besoldung, text
        berichtige_notenblatt(ws, [])  # Amtsbezeichnung, Schutz usw. fuer den neuen Reiter
        notenblaetter.append(ws)
        protokoll.append(f"Reiter „{name}“ neu angelegt (leer, Aufbau wie „{quelle.title}“)")
    # Reihenfolge: Kette zuerst, dann alle uebrigen Notenblaetter
    rang = {n: i for i, n in enumerate(kette)}
    alt_pos = {w.title: i for i, w in enumerate(notenblaetter)}
    notenblaetter.sort(key=lambda w: (rang.get(w.title, len(kette)), alt_pos[w.title]))
    for i, w in enumerate(notenblaetter):
        wb.move_sheet(w, i - wb.worksheets.index(w))
    return notenblaetter


# --------------------------------------------------------------------------
# Bedienhilfen: Hinweiszeile, Auswahllisten/Eingabehinweise, Rotmarkierung
# --------------------------------------------------------------------------
START_ZEITRAUM = (f"'{START}'!$B$8", f"'{START}'!$B$9")   # Beurteilungszeitraum von/bis
START_ART = f"'{START}'!$B$5"
ROT = dict(fill=PatternFill(bgColor="FFC7CE"), font=Font(color="9C0006"))
NOTEN_SPALTEN = ("letzte_rbu", "neue_rbu", "n11", "n2", "n42", "n43", "alb", "alb_n11", "alb_n2", "alb_n42", "alb_n43")
DATUM_SPALTEN = ("geb", "ernennung", "f1_von", "f1_bis", "f2_von", "f2_bis", "f3_von", "f3_bis", "gespraech", "sbh_gespr",
                 "alb_von", "alb_bis", "bb1_von", "bb1_bis", "bb2_von", "bb2_bis", "bb3_von", "bb3_bis",
                 "beginn", "befoerdert")
RECHEN = ("lfdnr", "amtsbez", "summe", "monate", "monate_halb", "ges")
AMTS_LISTE = '"' + ",".join(k for e in AMTSBEZEICHNUNGEN for k in (e[0], e[0] + "in")) + '"'
# Schluessel: (Hinweiszeile, Titel Eingabehinweis, Text Eingabehinweis)
HILFE = {
    "name": ("Nachname", "Name", "Nachname der Person. Neue Personen in die erste freie Zeile eintragen."),
    "vorname": ("", "Vorname", ""),
    "geb": ("TT.MM.JJJJ", "Geburtsdatum", "Datum TT.MM.JJJJ"),
    "geschlecht": ("m / w", "Geschlecht", "m oder w – bei w wird die weibliche Amtsbezeichnung verwendet."),
    "ernennung": ("TT.MM.JJJJ", "Datum der Ernennung", "Ernennung im jetzigen Statusamt (TT.MM.JJJJ)."),
    "zug": ("z.B. 1", "Zug", "Zug der Person – daraus werden Organisationseinheit und ggf. eigene Beurteilende."),
    "funktion": ("Hauptfunktion (Dienstposten)", "Hauptfunktion", "Dienstposten aus der Liste (Blatt Funktionen). Steht auf Seite 1 des Vordrucks."),
    "f1_von": ("TT.MM.JJJJ", "Hauptfunktion von", "Nur nötig, wenn weitere Funktionen eingetragen sind."),
    "f1_bis": ("TT.MM.JJJJ", "Hauptfunktion bis", "Nur nötig, wenn weitere Funktionen eingetragen sind."),
    "f2": ("weitere Funktion", "Weitere Funktion", "Im Beurteilungszeitraum zusätzlich wahrgenommene Funktion – dann bei allen Funktionen von/bis eintragen."),
    "f2_von": ("TT.MM.JJJJ", "Funktion 2 von", "Datum TT.MM.JJJJ"),
    "f2_bis": ("TT.MM.JJJJ", "Funktion 2 bis", "Datum TT.MM.JJJJ"),
    "f3": ("weitere Funktion", "Weitere Funktion", "Im Beurteilungszeitraum zusätzlich wahrgenommene Funktion – dann bei allen Funktionen von/bis eintragen."),
    "f3_von": ("TT.MM.JJJJ", "Funktion 3 von", "Datum TT.MM.JJJJ"),
    "f3_bis": ("TT.MM.JJJJ", "Funktion 3 bis", "Datum TT.MM.JJJJ"),
    "koop": ("TT.MM.JJJJ; TT.MM.JJJJ", "Kooperationsgespräche", "Ein oder mehrere Daten, mit Semikolon getrennt (bis 6 passen in den Vordruck)."),
    "gespraech": ("TT.MM.JJJJ", "Gespräch vor Beurteilung", "Datum TT.MM.JJJJ"),
    "sbh": ("ja / nein", "Schwerbehinderung", "ja oder nein – bei ja auch Einverständnis und ggf. Gesprächsdatum eintragen."),
    "sbh_einv": ("ja / nein", "Einverständnis", "Nur bei Schwerbehinderung: Einverständnis für das Gespräch mit der Vertrauensperson (ja/nein)."),
    "sbh_gespr": ("TT.MM.JJJJ", "Gespräch Vertrauensperson", "Nur bei Einverständnis ja: Datum des Gesprächs mit der Vertrauensperson."),
    "fuehrung": ("", "Führungsaufgabe", ""),  # Text je Laufbahn, siehe bedienhilfen
    "letzte_rbu": ("A1 … C", "Letzte RBU", "Endnote der letzten Regelbeurteilung (wird bei Beförderung automatisch gesetzt)."),
    "letzte_rbu_amt": ("z.B. POM", "Statusamt der letzten RBU", "Kürzel des damaligen Statusamts."),
    "neue_rbu": ("A1 … C", "Gesamtnote (neue RBU)", "Gesamtnote der aktuellen Beurteilung – kommt ins PDF (Seite 3 und 5)."),
    "n11": ("A1 … C", "Teilnote 1.1", "Qualität und Verwertbarkeit – kommt ins PDF."),
    "n2": ("A1 … C", "Teilnote 2", "Fachwissen – kommt ins PDF."),
    "n42": ("A1 … C", "Teilnote 4.2", "Zuverlässigkeit – kommt ins PDF."),
    "n43": ("A1 … C", "Teilnote 4.3", "Zusammenarbeit – kommt ins PDF."),
    "alb_x": ("x = vorhanden", "Anlassbeurteilung", "x und Zeitraum eintragen – mind. 6 Monate im Beurteilungszeitraum, sonst wird sie nicht berücksichtigt."),
    "alb_von": ("TT.MM.JJJJ", "Anlassbeurteilung von", "Datum TT.MM.JJJJ"),
    "alb_bis": ("TT.MM.JJJJ", "Anlassbeurteilung bis", "Datum TT.MM.JJJJ"),
    "alb": ("A1 … C", "ALB Gesamtnote", "Nur zur Information."),
    "alb_amt": ("z.B. POM", "Statusamt der ALB", "Nur zur Information."),
    "alb_n11": ("A1 … C", "ALB 1.1", "Nur zur Information."),
    "alb_n2": ("A1 … C", "ALB 2", "Nur zur Information."),
    "alb_n42": ("A1 … C", "ALB 4.2", "Nur zur Information."),
    "alb_n43": ("A1 … C", "ALB 4.3", "Nur zur Information."),
    "beginn": ("TT.MM.JJJJ", "Beginn Dienstzeit", "Datum TT.MM.JJJJ – Grundlage für Monate (zur Hälfte)."),
    "bemerkungen": ("", "Bemerkungen", "Freier Hinweis, geht nicht ins PDF."),
    "teilzeit": ("", "Teilzeit", "Hinweis für die Beurteilenden, geht nicht ins PDF."),
    "pdf": ("x = erstellen", "PDF", "x = bei „nur markierte Mitarbeiter“ eine Beurteilung erzeugen."),
    "befoerdert": ("TT.MM.JJJJ", "Befördert am", "Beförderungsdatum eintragen, dann oben den Knopf „Beförderungen übernehmen“."),
}
for _i in (1, 2, 3):
    HILFE[f"bb{_i}_x"] = ("x = vorhanden", f"Beurteilungsbeitrag {_i}",
                          "x und Zeitraum eintragen – mind. 3 Monate im Beurteilungszeitraum, sonst wird er nicht berücksichtigt.")
    HILFE[f"bb{_i}_von"] = ("TT.MM.JJJJ", f"Beurteilungsbeitrag {_i} von", "Datum TT.MM.JJJJ")
    HILFE[f"bb{_i}_bis"] = ("TT.MM.JJJJ", f"Beurteilungsbeitrag {_i} bis", "Datum TT.MM.JJJJ")


def entferne_tabellen_formatierung(ws, kopf):
    """Eigene bedingte Formatierung der Tabelle entfernen (wird am Ende neu gesetzt);
    die der Statistik oben (Notenquote) bleibt."""
    neu = ConditionalFormattingList()
    for cf in ws.conditional_formatting:
        if all(r.min_row < kopf - 1 for r in cf.sqref.ranges):
            for regel in cf.rules:
                neu.add(str(cf.sqref), regel)
    ws.conditional_formatting = neu


def bedienhilfen(ws, kopf, pos, breite, ende):
    L = get_column_letter
    erste = kopf + 2
    schluessel = {c: k for k, c in pos.items()}
    ist_gd = blattbasis(ws.title) in KETTE_GD
    hilfe = dict(HILFE)
    hilfe["fuehrung"] = (("leer = ja" if ist_gd else "leer = nein"), "Führungsaufgabe",
                         "gD: Punkt 5 „Führung“ wird beurteilt – nur bei nein wird er gestrichen." if ist_gd else
                         "mD: Punkt 5 „Führung“ wird im Vordruck gestrichen – nur bei ja (Ausnahme) wird er beurteilt.")

    # Hinweiszeile unter der Kopfzeile (statt der alten Kennziffern A01, A02, ...)
    for c in range(1, breite + 1):
        k = schluessel.get(c)
        text = "rechnet selbst" if k in RECHEN else (hilfe.get(k, ("",))[0] if k else None)
        z = ws.cell(kopf + 1, c)
        if k is None and not re.fullmatch(r"A\d{2}", str(z.value or "")):
            continue
        z.value = text or None
        z.font = HINWEIS
        z.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)

    # Auswahllisten und Eingabehinweise der Tabelle neu aufbauen
    for dv in list(ws.data_validations.dataValidation):
        if any(r.min_col <= breite and r.max_row >= erste for r in dv.sqref.ranges):
            ws.data_validations.dataValidation.remove(dv)
    listen = {"x": '"x"', "note": NOTENLISTE, "funktion": f"={FUNKTIONEN}!$A$4:$A$103"}
    for k, c in pos.items():
        if c > breite or k in RECHEN or k not in hilfe:
            continue
        _, titel, text = hilfe[k]
        bereich = f"{L(c)}{erste}:{L(c)}{ende}"
        if k in NOTEN_SPALTEN:
            dv = DataValidation(type="list", formula1=NOTENLISTE, error="Bitte eine Notenstufe aus der Liste wählen: A1, A2, B1, B2, B3 oder C.")
        elif k in DATUM_SPALTEN:
            dv = DataValidation(type="date", operator="between", formula1="1", formula2="73050",
                                error="Bitte ein Datum im Format TT.MM.JJJJ eintragen.")
        elif k in ("pdf", "alb_x", "bb1_x", "bb2_x", "bb3_x"):
            dv = DataValidation(type="list", formula1=listen["x"], error="Bitte x eintragen oder leer lassen.")
        elif k in ("funktion", "f2", "f3"):
            dv = DataValidation(type="list", formula1=listen["funktion"],
                                error="Bitte eine Funktion aus der Liste wählen (neue Funktionen im Blatt „Funktionen“ ergänzen).")
        elif k in ("sbh", "sbh_einv", "fuehrung"):
            dv = DataValidation(type="list", formula1='"ja,nein"', error="Bitte ja oder nein wählen.")
        elif k == "geschlecht":
            dv = DataValidation(type="list", formula1='"m,w"', error="Bitte m oder w wählen.")
        elif k in ("letzte_rbu_amt", "alb_amt"):
            dv = DataValidation(type="list", formula1=AMTS_LISTE, error="Bitte ein Amtskürzel aus der Liste wählen (z.B. POM oder POMin).")
        else:
            dv = DataValidation()  # jeder Wert, nur Eingabehinweis
        dv.allow_blank = True
        dv.showErrorMessage = dv.type is not None
        dv.errorStyle = "stop"
        dv.errorTitle = "Ungültige Eingabe"
        dv.showInputMessage = bool(text)
        dv.promptTitle = titel[:32]
        dv.prompt = text[:255]
        dv.add(bereich)
        ws.add_data_validation(dv)

    # Rotmarkierung (Zeilen ohne Namen bleiben unauffaellig)
    N = f"${L(pos['name'])}"
    zv, zb = START_ZEITRAUM

    def regel(von, bis, formel):
        ws.conditional_formatting.add(f"{L(von)}{erste}:{L(bis)}{ende}", FormulaRule(formula=[formel], **ROT))

    if "neue_rbu" in pos:
        q = f"{L(pos['neue_rbu'])}{erste}"
        regel(pos["neue_rbu"], pos["neue_rbu"], f'AND({N}{erste}<>"",{q}="",{START_ART}<>"Beurteilungsbeitrag")')
    for k in NOTEN_SPALTEN:
        if k in pos:
            z = f"{L(pos[k])}{erste}"
            regel(pos[k], pos[k], f'AND({z}<>"",NOT(OR({z}="A1",{z}="A2",{z}="B1",{z}="B2",{z}="B3",{z}="C")))')
    for x, v, b in [("alb_x", "alb_von", "alb_bis")] + [(f"bb{i}_x", f"bb{i}_von", f"bb{i}_bis") for i in (1, 2, 3)]:
        monate = 6 if x == "alb_x" else 3  # Mindestdauer im Beurteilungszeitraum: ALB 6, BB 3 Monate
        if all(k in pos for k in (x, v, b)):
            X, V, B = (f"${L(pos[k])}{erste}" for k in (x, v, b))
            ende_im = f'IF({zb}="",{B},MIN({B},{zb}))'
            regel(pos[x], pos[b],
                  f'OR(AND({X}<>"",OR({V}="",{B}="")),AND({X}="",OR({V}<>"",{B}<>"")),'
                  f'AND({X}<>"",{V}<>"",{B}<>"",{ende_im}<EDATE(MAX({V},{zv}),{monate})-1))')
    if all(k in pos for k in ("funktion", "f1_von", "f1_bis", "f2", "f3")):
        F1, F2, F3 = (f"${L(pos[k])}{erste}" for k in ("funktion", "f2", "f3"))
        for f, v, b in (("funktion", "f1_von", "f1_bis"), ("f2", "f2_von", "f2_bis"), ("f3", "f3_von", "f3_bis")):
            F, V, B = (f"${L(pos[k])}{erste}" for k in (f, v, b))
            regel(pos[v], pos[b], f'AND({F}<>"",OR({F2}<>"",{F3}<>""),OR({V}="",{B}=""))')
    if all(k in pos for k in ("sbh", "sbh_einv", "sbh_gespr")):
        SB, EI, GE = (f"${L(pos[k])}{erste}" for k in ("sbh", "sbh_einv", "sbh_gespr"))
        regel(pos["sbh_einv"], pos["sbh_einv"], f'AND({N}{erste}<>"",{SB}="ja",{EI}="")')
        regel(pos["sbh_gespr"], pos["sbh_gespr"], f'AND({N}{erste}<>"",{EI}="ja",{GE}="")')
    for k in DATUM_SPALTEN:
        if k in pos and pos[k] <= breite:
            z = f"{L(pos[k])}{erste}"
            regel(pos[k], pos[k], f'AND({z}<>"",NOT(ISNUMBER({z})))')

    # Kopf und Namen beim Blaettern stehen lassen
    ws.freeze_panes = f"{L(pos['vorname'] + 1)}{erste}"


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
    sperre_ausser_eingabe(ws)
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
    sperre_ausser_eingabe(ws)
    return ws


def sperre_ausser_eingabe(ws):
    """Blattschutz ohne Kennwort: nur die gelben Eingabefelder bleiben frei."""
    alles_sperren(ws)
    for row in ws.iter_rows():
        for z in row:
            if z.fill is not None and z.fill.fgColor is not None and z.fill.fgColor.rgb in ("00FFF2CC", "FFFFF2CC"):
                z.protection = Protection(locked=False)
    erlaube(ws)


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


def baue_startseite(wb, notenblaetter, zuege, stichtag, alt_werte, alt_zeilen, infos=None):
    ws = wb.create_sheet(START, len(notenblaetter))  # hinter die Vergleichsgruppen
    ws.sheet_view.showGridLines = False
    for w in wb.worksheets:
        w.sheet_view.tabSelected = False
    wb.worksheets[0].sheet_view.tabSelected = True   # Datei oeffnet mit der ersten Vergleichsgruppe
    wb.active = 0

    def wert(label, standard):
        v = alt_werte.get(norm(label))
        return standard if v in (None, "") else v

    zelle(ws, "A1", "Beurteilungen erstellen", TITEL)
    zelle(ws, "A2", "Gelbe Felder ausfüllen, Excel-Datei speichern (Strg+S) und Excel schließen, dann Beurteilung.html "
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
    zelle(ws, "C8", "Regelbeurteilung: mindestens 6 Monate, höchstens 2 Jahre (z.B. 01.10.2025 – 30.09.2027)", HINWEIS)
    ws.conditional_formatting.add("B8:B9", FormulaRule(
        formula=['AND($B$5="Regelbeurteilung",$B$8<>"",$B$9<>"",'
                 'OR($B$9<EDATE($B$8,6)-1,$B$9>EDATE($B$8,24)-1))'], **ROT))

    zelle(ws, "A11", "2  Mitarbeiter", ABSCHNITT)
    optionen = [
        ("Auswahl", wert("Auswahl", "alle Mitarbeiter"), ["alle Mitarbeiter", "nur markierte Mitarbeiter"],
         "„nur markierte“ = auf den Notenblättern in Spalte „PDF“ ein x setzen"),
        ("Mitarbeiter ohne neue Note überspringen", wert("Mitarbeiter ohne neue Note überspringen", "ja"), ["ja", "nein"],
         "Spalte „neue RBU“ leer -> keine Beurteilung erzeugen"),
    ]
    for i, (label, v, werte, hinweis) in enumerate(optionen):
        r = 12 + i
        zelle(ws, f"A{r}", label)
        zelle(ws, f"B{r}", v, fill=EINGABE, rahmen=True)
        zelle(ws, f"C{r}", hinweis, HINWEIS)
        liste(ws, f"B{r}", werte)

    r = 15
    zelle(ws, f"A{r}", "Blatt", FETT, KOPF, rahmen=True)
    zelle(ws, f"B{r}", "einbeziehen", FETT, KOPF, rahmen=True)
    for col, t in zip("CDEF", ("Personen", "mit neuer RBU", "ohne neue RBU", "PDF markiert")):
        zelle(ws, f"{col}{r}", t, FETT, KOPF, rahmen=True, align=Alignment(horizontal="center"))
    alt_bl = {norm(z[0]): z[1] for z in tabelle(alt_zeilen, "blatt", "einbeziehen")}
    for name in notenblaetter:
        r += 1
        z = zelle(ws, f"A{r}", name, rahmen=True)
        i = (infos or {}).get(name)
        if i:
            # Klick auf den Blattnamen springt in den Reiter
            z.hyperlink = Hyperlink(ref=z.coordinate, location=f"'{name}'!{i['name']}{i['erste']}")
            z.font = Font(color="0563C1", underline="single")
            b = f"'{name}'!${i['name']}${i['erste']}:${i['name']}${i['ende']}"
            q = f"'{name}'!${i['rbu']}${i['erste']}:${i['rbu']}${i['ende']}"
            p = f"'{name}'!${i['pdf']}${i['erste']}:${i['pdf']}${i['ende']}"
            mitte = Alignment(horizontal="center")
            zelle(ws, f"C{r}", f"=COUNTA({b})", rahmen=True, align=mitte)
            zelle(ws, f"D{r}", f'=COUNTIFS({b},"<>",{q},"<>")', rahmen=True, align=mitte)
            zelle(ws, f"E{r}", f"=C{r}-D{r}", rahmen=True, align=mitte)
            zelle(ws, f"F{r}", f'=COUNTIFS({b},"<>",{p},"<>")', rahmen=True, align=mitte)
        zelle(ws, f"B{r}", alt_bl.get(norm(name), "ja"), fill=EINGABE, rahmen=True)
        liste(ws, f"B{r}", ["ja", "nein"])
    zelle(ws, f"A{r + 1}", "Klick auf den Blattnamen springt in den Reiter. „ohne neue RBU“ > 0: dort fehlt noch eine Gesamtnote.",
          HINWEIS)
    r += 1

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
    zelle(ws, f"A{r}", "Diese Excel-Datei speichern (Strg+S) und Excel schließen.", FETT)
    zelle(ws, f"A{r + 1}", "Dann Beurteilung.html (im selben Ordner) per Doppelklick öffnen und diese Excel-Datei "
                           "hineinziehen. Solange die Datei in Excel offen ist, ist sie im Browser grau und nicht auswählbar.",
          HINWEIS)

    ws.column_dimensions["A"].width = 44
    ws.column_dimensions["B"].width = 34
    ws.column_dimensions["C"].width = 34
    for col in "DEF":
        ws.column_dimensions[col].width = 15
    # "ohne neue RBU" rot, solange noch Gesamtnoten fehlen
    ws.conditional_formatting.add(f"E16:E{15 + len(notenblaetter)}", FormulaRule(formula=["E16>0"], **ROT))
    sperre_ausser_eingabe(ws)
    return ws


# --------------------------------------------------------------------------
# Knoepfe fuer die Makros (Beurteilungs-Makros.txt) auf jedem Notenblatt.
# openpyxl kann keine Formen schreiben, daher direkt ins gespeicherte XML.
# --------------------------------------------------------------------------
KNOEPFE = [  # Text, Makro, Spalte (0-basiert), Zeile von/bis (0-basiert), Farbe
    ("Beförderungen übernehmen", "BefoerderungenUebernehmen", 20, 0, 2, "1F3864"),  # eine Spalte Abstand zum Stichtag
    ("Liste leeren", "ListeLeeren", 20, 3, 5, "A61C1C"),
]
_NS_R = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"


def _knopf_xml(nr, text, makro, spalte, von, bis, farbe):
    return (f'<xdr:twoCellAnchor editAs="absolute">'
            f'<xdr:from><xdr:col>{spalte}</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>{von}</xdr:row><xdr:rowOff>30000</xdr:rowOff></xdr:from>'
            f'<xdr:to><xdr:col>{spalte + 6}</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>{bis}</xdr:row><xdr:rowOff>120000</xdr:rowOff></xdr:to>'
            f'<xdr:sp macro="[0]!{makro}" textlink="">'
            f'<xdr:nvSpPr><xdr:cNvPr id="{nr + 1}" name="Knopf {makro}"/><xdr:cNvSpPr/></xdr:nvSpPr>'
            f'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></a:xfrm>'
            f'<a:prstGeom prst="roundRect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="{farbe}"/></a:solidFill>'
            f'<a:ln><a:noFill/></a:ln></xdr:spPr>'
            f'<xdr:txBody><a:bodyPr vertOverflow="clip" wrap="square" rtlCol="0" anchor="ctr"/><a:lstStyle/>'
            f'<a:p><a:pPr algn="ctr"/><a:r><a:rPr lang="de-DE" sz="1100" b="1"><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill>'
            f'</a:rPr><a:t>{text}</a:t></a:r></a:p></xdr:txBody></xdr:sp><xdr:clientData/></xdr:twoCellAnchor>')


def knoepfe_einfuegen(datei, blaetter):
    import zipfile
    with zipfile.ZipFile(datei) as z:
        teile = {n: z.read(n) for n in z.namelist()}
    wb_xml = teile["xl/workbook.xml"].decode()
    rels = teile["xl/_rels/workbook.xml.rels"].decode()
    ziel = {m.group(1): m.group(2) for m in re.finditer(r'<Relationship[^>]*Id="([^"]+)"[^>]*Target="([^"]+)"', rels)}
    ziel.update({m.group(2): m.group(1) for m in re.finditer(r'<Relationship[^>]*Target="([^"]+)"[^>]*Id="([^"]+)"', rels)})
    pfade = {}
    for m in re.finditer(r'<sheet\b[^>]*>', wb_xml):
        name = re.search(r'name="([^"]*)"', m.group(0)).group(1)
        rid = re.search(r'r:id="([^"]+)"', m.group(0)).group(1)
        t = ziel[rid]
        pfade[name.replace("&amp;", "&")] = t.lstrip("/") if t.startswith("/") else "xl/" + t
    typen = teile["[Content_Types].xml"].decode()
    nr = max([int(x) for n in teile for x in re.findall(r"^xl/drawings/drawing(\d+)\.xml$", n)] or [0])
    for blatt in blaetter:
        pfad = pfade[blatt]
        xml = teile[pfad].decode()
        if "<drawing " in xml:
            continue
        nr += 1
        zeichnung = (f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                     f'<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
                     f'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
                     + "".join(_knopf_xml(i + 1, *k) for i, k in enumerate(KNOEPFE)) + "</xdr:wsDr>")
        teile[f"xl/drawings/drawing{nr}.xml"] = zeichnung.encode()
        typen = typen.replace("</Types>", f'<Override PartName="/xl/drawings/drawing{nr}.xml" '
                              f'ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/></Types>')
        ordner, name = pfad.rsplit("/", 1)
        rels_pfad = f"{ordner}/_rels/{name}.rels"
        srels = teile.get(rels_pfad, b'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                          b'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>').decode()
        rid = "rIdKnopf"
        srels = srels.replace("</Relationships>", f'<Relationship Id="{rid}" Type="{_NS_R}/drawing" '
                              f'Target="../drawings/drawing{nr}.xml"/></Relationships>')
        teile[rels_pfad] = srels.encode()
        if "xmlns:r=" not in xml.split(">", 2)[1]:
            xml = re.sub(r"<worksheet\b", f'<worksheet xmlns:r="{_NS_R}"', xml, count=1)
        tag = f'<drawing r:id="{rid}"/>'
        m = re.search(r"<(legacyDrawing|legacyDrawingHF|picture|oleObjects|controls|webPublishItems|tableParts|extLst)\b", xml)
        xml = xml[:m.start()] + tag + xml[m.start():] if m else xml.replace("</worksheet>", tag + "</worksheet>")
        teile[pfad] = xml.encode()
    teile["[Content_Types].xml"] = typen.encode()
    with zipfile.ZipFile(datei, "w", zipfile.ZIP_DEFLATED) as z:
        for n, d in teile.items():
            z.writestr(n, d)


def main(quelle, ziel, vorlage=None):
    wb = openpyxl.load_workbook(quelle, keep_vba=quelle.lower().endswith(".xlsm"))
    protokoll = []
    # Eintraege der Tool-Blaetter aus der Datei selbst, sonst aus einer
    # frueher aufbereiteten Fassung (--vorlage), damit nichts verloren geht
    alt = openpyxl.load_workbook(vorlage) if vorlage else None
    von = lambda name: wb if name in wb.sheetnames or alt is None else alt
    if alt is not None:
        uebernommen = [n for n in (START, BEURTEILER, EINSTELLUNGEN, FUNKTIONEN)
                       if n not in wb.sheetnames and n in alt.sheetnames]
        if uebernommen:
            protokoll.append(f"aus {vorlage} übernommen: " + ", ".join(uebernommen))
    alt_start, alt_start_z = alte_werte(von(START), START)
    _, alt_beurt_z = alte_werte(von(BEURTEILER), BEURTEILER)
    alt_einst, alt_einst_z = alte_werte(von(EINSTELLUNGEN), EINSTELLUNGEN)
    alt_funkt_z = alte_zeilen(von(FUNKTIONEN), FUNKTIONEN, 2 + TAETIGKEITEN)
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
    if ist_gd:
        notenblaetter = ergaenze_reiter(wb, notenblaetter, KETTE_GD, protokoll)
    zuege.sort(key=lambda z: (z in (None, ""), not isinstance(z, (int, float)), str(z)))

    infos = {}
    for ws in notenblaetter:
        kopf = kopfzeile(ws)
        pos = positionen(ws, kopf)
        infos[ws.title] = dict(erste=kopf + 2, ende=kopf + 1 + PERSONEN_JE_BLATT, name=get_column_letter(pos["name"]),
                               rbu=get_column_letter(pos["neue_rbu"]), pdf=get_column_letter(pos["pdf"]))
    baue_startseite(wb, [ws.title for ws in notenblaetter], zuege, stichtag, alt_start, alt_start_z, infos)
    baue_beurteiler(wb, alt_beurt_z)
    baue_funktionen(wb, alt_funkt_z)
    baue_einstellungen(wb, zuege, alt_einst, alt_einst_z)
    wb.save(ziel)
    knoepfe_einfuegen(ziel, [ws.title for ws in notenblaetter])
    print("\n".join(protokoll))
    print(f"-> {ziel}")


if __name__ == "__main__":
    args = sys.argv[1:]
    vorlage = None
    if "--vorlage" in args:
        i = args.index("--vorlage")
        vorlage = args[i + 1]
        del args[i:i + 2]
    if len(args) != 2:
        sys.exit(__doc__)
    main(args[0], args[1], vorlage)
