"""Bereitet den BPOL-Vordruck 4 00 069 einmalig fuer das Tool vor.

Veraendert werden bewusst NUR:
  * die Schriftgroesse: alle Text- und Auswahlfelder mit automatischer
    Schriftgroesse ("0 Tf" = Text schrumpft bis zur Unleserlichkeit) bekommen
    eine feste Groesse,
  * das Feld "Begruendung der Gesamtnote" (f.begruend.1): dort darf weiter-
    geschrieben werden als das Feld hoch ist ("nicht scrollen" wird aufgehoben),
    der Ueberhang wird beim Fertigstellen auf Fortsetzungsseiten verteilt.
Dafuer muss der Berechtigungsschutz entfernt werden (Oeffnen ohne Kennwort).

Aufruf: python3 vordruck_vorbereiten.py <vordruck_blanko.pdf> <ziel.pdf>
Benoetigt: pip install pymupdf
"""
import re
import sys

import pymupdf

SCHRIFT_PT = 10           # Text- und Auswahlfelder
SCHRIFT_FUSSZEILE_PT = 8  # schreibgeschuetzte Fusszeile auf Seite 2-6
FELD_BEGRUENDUNG = "f.begruend.1"
DO_NOT_SCROLL = 1 << 23


def schluessel(doc, xref, key):
    typ, wert = doc.xref_get_key(xref, key)
    return None if typ == "null" else (typ, wert)


def kette(doc, xref):
    """Widget und alle Elternfelder (xref-Liste)."""
    aus = []
    while xref:
        aus.append(xref)
        p = schluessel(doc, xref, "Parent")
        xref = int(p[1].split()[0]) if p else 0
    return aus


def feste_groesse(da, pt):
    return re.sub(r"(/\S+)\s+0(?:\.0+)?\s+Tf", lambda m: f"{m.group(1)} {pt} Tf", da)


def main(quelle, ziel):
    doc = pymupdf.open(quelle)
    if doc.needs_pass and not doc.authenticate(""):
        sys.exit("Vordruck laesst sich nicht ohne Kennwort oeffnen.")

    acro = schluessel(doc, doc.pdf_catalog(), "AcroForm")
    if acro and acro[0] == "xref":
        axref = int(acro[1].split()[0])
        da = schluessel(doc, axref, "DA")
        if da:
            doc.xref_set_key(axref, "DA", pymupdf.get_pdf_str(feste_groesse(da[1], SCHRIFT_PT)))

    geaendert, erledigt, begruendung = 0, set(), False
    for seite in doc:
        for w in seite.widgets():
            if w.field_type not in (pymupdf.PDF_WIDGET_TYPE_TEXT, pymupdf.PDF_WIDGET_TYPE_COMBOBOX,
                                    pymupdf.PDF_WIDGET_TYPE_LISTBOX):
                continue  # Ankreuzfelder behalten ihre Darstellung
            pt = SCHRIFT_FUSSZEILE_PT if w.field_name.endswith("fusszeile") else SCHRIFT_PT
            for xref in kette(doc, w.xref):
                if xref in erledigt:
                    continue
                erledigt.add(xref)
                da = schluessel(doc, xref, "DA")
                if da and da[0] == "string":
                    neu = feste_groesse(da[1], pt)
                    if neu != da[1]:
                        doc.xref_set_key(xref, "DA", pymupdf.get_pdf_str(neu))
                        geaendert += 1
            if w.field_name == FELD_BEGRUENDUNG:
                begruendung = True
                for xref in kette(doc, w.xref):
                    ff = schluessel(doc, xref, "Ff")
                    if ff:
                        doc.xref_set_key(xref, "Ff", str(int(ff[1]) & ~DO_NOT_SCROLL))

    if not begruendung:
        sys.exit(f"Feld {FELD_BEGRUENDUNG} nicht gefunden – falscher Vordruck?")
    doc.save(ziel, encryption=pymupdf.PDF_ENCRYPT_NONE, garbage=1, deflate=True)
    print(f"{geaendert} Schriftgroessen fest eingestellt, Schutz entfernt -> {ziel}")


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
