"""TEST-Variante: Fortsetzungsseite fuer die Begruendung direkt im PDF.

Ergaenzt den vorbereiteten Vordruck um
  * eine unsichtbare Seitenvorlage "bgfort" (Fortsetzung der Begruendung der
    Gesamtnote) mit einem grossen Textfeld "bgfortsetzung",
  * ein Dokument-Skript bgUeberlauf(): Verlaesst man die Begruendung (oder ein
    Fortsetzungsfeld) und der Text ist laenger als das Feld, wird der Ueberhang
    abgeschnitten, bei Bedarf eine Fortsetzungsseite aus der Vorlage direkt
    dahinter angelegt und der Rest dort eingetragen (verkettet ueber mehrere
    Seiten). Geht das im PDF-Programm nicht, erscheint ein Hinweis auf das
    Fertigstellen in Beurteilung.html.
Ob ein PDF-Programm Seitenvorlagen per Skript anlegen darf, ist je nach
Programm/Version verschieden – deshalb nur als Test.

Aufruf: python3 vordruck_test_vorlage.py <vordruck_vorbereitet.pdf> <ziel.pdf>
"""
import sys

import pymupdf

VORLAGE = "bgfort"
FELD = "bgfortsetzung"  # ohne Punkt: Punkte trennen im PDF Feldebenen
BEGRUENDUNG = "f.begruend.1"
B, H = 595.276, 841.89
BOX = (58, 70, 566, 740)  # PDF-Koordinaten (unten links): x0, y0, x1, y1

JS = r"""
function bgNaechstes(d, feld) {
  var best = null, bestSeite = 100000;
  for (var i = 0; i < d.numFields; i++) {
    var n = d.getNthFieldName(i);
    if (/bgfortsetzung$/.test(n) && n != "bgfortsetzung") {
      var f = d.getField(n);
      var s = f.page;
      if (typeof s != "number") s = s[0];
      if (s > feld.page && s < bestSeite) { best = f; bestSeite = s; }
    }
  }
  return best;
}
function bgUeberlauf(feld) {
  var d = feld.doc || this;
  try {
    var PT = 10, ZEICHEN = 0.6 * PT, ZEILE = 1.2 * PT, RAND = 4;
    var r = feld.rect;
    var proZeile = Math.floor((Math.abs(r[2] - r[0]) - 2 * RAND) / ZEICHEN);
    var maxZeilen = Math.floor((Math.abs(r[1] - r[3]) - 2 * RAND) / ZEILE);
    var txt = String(feld.value == null ? "" : feld.value).replace(/\r\n?/g, "\n");
    var abs = txt.split("\n"), zeilen = 0, pos = 0, schnitt = -1;
    for (var a = 0; a < abs.length && schnitt < 0; a++) {
      var p = abs[a], start = pos;
      if (p.length == 0) {
        zeilen++;
        if (zeilen > maxZeilen) { schnitt = start; break; }
      }
      var i = 0;
      while (i < p.length) {
        zeilen++;
        if (zeilen > maxZeilen) { schnitt = start + i; break; }
        if (p.length - i <= proZeile) { i = p.length; break; }
        var ende = p.lastIndexOf(" ", i + proZeile);
        if (ende <= i) ende = i + proZeile;
        i = ende;
        while (p.charAt(i) == " ") i++;
      }
      pos = start + p.length + 1;
    }
    if (schnitt < 0) return;
    var vorne = txt.substring(0, schnitt).replace(/[ \n]+$/, "");
    var rest = txt.substring(schnitt).replace(/^[ \n]+/, "");
    var naechstes = bgNaechstes(d, feld);
    if (!naechstes) {
      d.getTemplate("bgfort").spawn({nPage: feld.page + 1, bRename: true, bOverlay: false});
      naechstes = bgNaechstes(d, feld);
      if (!naechstes) throw "Fortsetzungsfeld nicht gefunden";
    }
    var alt = String(naechstes.value == null ? "" : naechstes.value);
    feld.value = vorne;
    naechstes.value = alt ? rest + " " + alt : rest;
    bgUeberlauf(naechstes);
  } catch (e) {
    app.alert("TEST: Die Fortsetzungsseite konnte in diesem PDF-Programm nicht automatisch angelegt werden.\n(" +
      e + ")\n\nDer Text ist vollständig gespeichert. Bitte die Beurteilung wie gewohnt in Beurteilung.html " +
      "unter „2 Beurteilung fertigstellen“ auf Folgeseiten verteilen.", 1);
  }
}
"""


def pdf_string(s):
    return "(" + s.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)") + ")"


def neues_objekt(doc, inhalt, stream=None):
    x = doc.get_new_xref()
    doc.update_object(x, inhalt)
    if stream is not None:
        doc.update_stream(x, stream.encode("latin-1"))
    return x


def main(quelle, ziel):
    doc = pymupdf.open(quelle)
    kat = doc.pdf_catalog()

    # 1) Vorlagenseite zeichnen (wie die Fortsetzungsseiten beim Fertigstellen)
    seite = doc.new_page(-1, width=B, height=H)
    y = lambda v: H - v  # PDF-Koordinate -> pymupdf (oben links)
    rechts = 566
    for text, groesse, font, hoehe in (("Dienstliche Beurteilung in der Bundespolizei", 10, "hebo", 800),
                                       ("(Anlage 4 BeurtRL BPOL)", 10, "helv", 787),
                                       ("Zutreffendes bitte ankreuzen oder ausfüllen", 7, "helv", 776)):
        breite = pymupdf.get_text_length(text, fontname=font, fontsize=groesse)
        seite.insert_text((rechts - breite, y(hoehe)), text, fontname=font, fontsize=groesse)
    seite.insert_text((BOX[0], y(BOX[3] + 8)), "Begründung der Gesamtnote", fontname="helv", fontsize=10)
    seite.insert_text((BOX[0] + 128, y(BOX[3] + 8)), "(vgl. Nr. 4.3 und 4.5) – Fortsetzung", fontname="helv", fontsize=7)
    seite.draw_rect(pymupdf.Rect(BOX[0], y(BOX[3]), BOX[2], y(BOX[1])), color=(0.45, 0.45, 0.45), width=0.75)
    seite.insert_text((57, y(22)), "BPOL 4 00 069 08 16  (Fortsetzungsblatt zu Seite 5)", fontname="helv", fontsize=6)

    w = pymupdf.Widget()
    w.field_type = pymupdf.PDF_WIDGET_TYPE_TEXT
    w.field_name = FELD
    w.rect = pymupdf.Rect(BOX[0] + 1, y(BOX[3]) + 1, BOX[2] - 1, y(BOX[1]) - 1)
    w.text_font = "Cour"
    w.text_fontsize = 10
    w.field_flags = 4096  # mehrzeilig, darf scrollen (fuer die Verkettung)
    w.border_width = 0
    seite.add_widget(w)
    widget = next(seite.widgets())
    js_bl = neues_objekt(doc, "<</S/JavaScript/JS " + pdf_string("bgUeberlauf(event.target);") + ">>")
    doc.xref_set_key(widget.xref, "AA", f"<</Bl {js_bl} 0 R>>")
    doc.xref_set_key(widget.xref, "DA", pymupdf.get_pdf_str("/Cour 10 Tf 0 g"))
    doc.xref_set_key(widget.xref, "P", f"{seite.xref} 0 R")

    # 2) Seite aus dem Seitenbaum nehmen und als (unsichtbare) Vorlage fuehren
    seiten_xref = seite.xref
    wurzel = int(doc.xref_get_key(kat, "Pages")[1].split()[0])
    kids = doc.xref_get_key(wurzel, "Kids")[1]
    kids = kids.replace(f"{seiten_xref} 0 R", "").replace("  ", " ")
    doc.xref_set_key(wurzel, "Kids", kids)
    anzahl = int(doc.xref_get_key(wurzel, "Count")[1])
    doc.xref_set_key(wurzel, "Count", str(anzahl - 1))
    doc.xref_set_key(seiten_xref, "Parent", "null")
    doc.xref_set_key(seiten_xref, "Type", "/Template")

    # 3) Namensbaum: Templates + Dokument-Skript
    js_obj = neues_objekt(doc, "<</S/JavaScript/JS " + pdf_string(JS) + ">>")
    namen = doc.xref_get_key(kat, "Names")
    if namen[0] == "xref":
        nx = int(namen[1].split()[0])
    else:
        nx = neues_objekt(doc, "<<>>")
        doc.xref_set_key(kat, "Names", f"{nx} 0 R")
    doc.xref_set_key(nx, "Templates", f"<</Names[({VORLAGE}) {seiten_xref} 0 R]>>")
    js_baum = doc.xref_get_key(nx, "JavaScript")
    jx = int(js_baum[1].split()[0]) if js_baum[0] == "xref" else None
    if jx is None:
        doc.xref_set_key(nx, "JavaScript", f"<</Names[(bgUeberlauf) {js_obj} 0 R]>>")
    else:
        alt = doc.xref_get_key(jx, "Names")[1].strip()[1:-1]
        doc.xref_set_key(jx, "Names", f"[(bgUeberlauf) {js_obj} 0 R {alt}]")

    # 4) Begruendung: beim Verlassen pruefen
    for s in doc:
        for f in s.widgets():
            if f.field_name == BEGRUENDUNG:
                ziel_xref = f.xref
    js_b = neues_objekt(doc, "<</S/JavaScript/JS " + pdf_string("bgUeberlauf(event.target);") + ">>")
    doc.xref_set_key(ziel_xref, "AA", f"<</Bl {js_b} 0 R>>")

    doc.save(ziel, garbage=3, deflate=True)
    print(f"-> {ziel} (Vorlage „{VORLAGE}“, Skript bgUeberlauf)")


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
