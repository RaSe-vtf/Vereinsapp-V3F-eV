"""Uebernimmt Anforderungsprofile in das Blatt "Funktionen" der Notenuebersicht.

Ein Anforderungsprofil ist ein teilweise ausgefuellter Vordruck BPOL 4 00 069:
Funktionsbezeichnung (f.funktion.1), Funktionswertigkeit (f.funktion.2) und die
die Funktion praegenden Taetigkeiten (f.taetigkeit.1, je Taetigkeit ein mit
"- " beginnender Absatz, von Hand umbrochen). Bestehende Eintraege mit gleicher
Funktionsbezeichnung werden ersetzt, neue angehaengt, andere bleiben stehen.

Aufruf: python3 funktionen_importieren.py <ordner-oder-zip> <notenuebersicht.xlsx> [...]
Benoetigt: pip install pymupdf openpyxl; die Excel-Datei muss aufbereitet sein.
"""
import pathlib
import re
import sys
import tempfile
import zipfile

import openpyxl
import pymupdf

BLATT = "Funktionen"
ERSTE_ZEILE = 4
LETZTE_ZEILE = 103
TAETIGKEITEN = 5


def zeilen_verbinden(absatz):
    """Fuegt handumbrochene Zeilen wieder zusammen (Trennstrich/Schraegstrich am Zeilenende)."""
    text = ""
    for zeile in absatz.split("\n"):
        zeile = zeile.strip()
        if not zeile:
            continue
        if not text:
            text = zeile
        elif text.endswith("-") and zeile[:1].islower():
            text = text[:-1] + zeile           # polizei- / licher -> polizeilicher
        elif text.endswith("/"):
            text += zeile                      # Dokumentation/ / Ermittlung
        else:
            text += " " + zeile
    return re.sub(r"\s+", " ", text).strip()


def lies_profil(pfad):
    doc = pymupdf.open(pfad)
    werte = {w.field_name: (w.field_value or "") for seite in doc for w in seite.widgets()}
    bezeichnung = re.sub(r"\s+", " ", werte.get("f.funktion.1", "")).strip()
    if not bezeichnung:
        return None
    wertigkeit = werte.get("f.funktion.2", "").strip().strip("()").strip()
    roh = werte.get("f.taetigkeit.1", "").replace("\r\n", "\n").replace("\r", "\n")
    taetigkeiten = []
    for absatz in re.split(r"\n\s*\n", roh):
        absatz = re.sub(r"^\s*[-–•]\s*", "", absatz)
        if absatz.strip():
            taetigkeiten.append(zeilen_verbinden(absatz))
    return bezeichnung, wertigkeit, taetigkeiten


def profile(quelle):
    quelle = pathlib.Path(quelle)
    if quelle.suffix.lower() == ".zip":
        ziel = pathlib.Path(tempfile.mkdtemp())
        with zipfile.ZipFile(quelle) as z:
            z.extractall(ziel)
        quelle = ziel
    for pfad in sorted(quelle.rglob("*.pdf")):
        if "__MACOSX" in pfad.parts or pfad.name.startswith("._"):
            continue
        p = lies_profil(pfad)
        if p:
            yield pfad.name, p
        else:
            print(f"  übersprungen (keine Funktionsbezeichnung): {pfad.name}")


def sortierschluessel(eintrag):
    bezeichnung, wertigkeit = eintrag[0], eintrag[1] or ""
    stufe = re.search(r"\d+", wertigkeit)
    return (int(stufe.group()) if stufe else 99, wertigkeit, bezeichnung.lower())


def main(quelle, dateien):
    gelesen = list(profile(quelle))
    if not gelesen:
        sys.exit("Keine Anforderungsprofile gefunden.")
    for name, (bez, wert, taet) in gelesen:
        hinweis = f"  – {len(taet)} Tätigkeiten, nur die ersten {TAETIGKEITEN} übernommen" if len(taet) > TAETIGKEITEN else ""
        print(f"  {bez} ({wert}): {len(taet)} Tätigkeiten{hinweis}")
    for datei in dateien:
        wb = openpyxl.load_workbook(datei)
        if BLATT not in wb.sheetnames:
            sys.exit(f"{datei}: Blatt „{BLATT}“ fehlt – erst excel_aufbereiten.py laufen lassen.")
        ws = wb[BLATT]
        eintraege = {}
        for r in range(ERSTE_ZEILE, LETZTE_ZEILE + 1):
            z = [ws.cell(r, c).value for c in range(1, 3 + TAETIGKEITEN)]
            if z[0]:
                eintraege[str(z[0]).strip().lower()] = [str(z[0]).strip(), z[1]] + [v for v in z[2:] if v]
        for _, (bez, wert, taet) in gelesen:
            eintraege[bez.lower()] = [bez, wert] + taet[:TAETIGKEITEN]
        liste = sorted(eintraege.values(), key=sortierschluessel)
        if len(liste) > LETZTE_ZEILE - ERSTE_ZEILE + 1:
            sys.exit(f"{datei}: mehr als {LETZTE_ZEILE - ERSTE_ZEILE + 1} Funktionen")
        for i, r in enumerate(range(ERSTE_ZEILE, LETZTE_ZEILE + 1)):
            werte = liste[i] if i < len(liste) else []
            for c in range(1, 3 + TAETIGKEITEN):
                ws.cell(r, c).value = werte[c - 1] if c - 1 < len(werte) else None
        wb.save(datei)
        print(f"-> {datei}: {len(liste)} Funktionen")


if __name__ == "__main__":
    if len(sys.argv) < 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2:])
