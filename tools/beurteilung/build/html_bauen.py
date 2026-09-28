"""Baut die eigenstaendige Datei Beurteilung.html (offline, eine Datei).

Fuegt die Bibliotheken aus vendor/, den Kern src/engine.js und den
vorbereiteten Vordruck (Base64) in src/ui.html ein.

Aufruf: python3 html_bauen.py <vordruck_vorbereitet.pdf> <ziel.html>
        python3 html_bauen.py --test <vordruck_test.pdf> <Beurteilung_Test.html>
"""
import base64
import datetime
import pathlib
import sys

BASIS = pathlib.Path(__file__).resolve().parent.parent


def skript(pfad):
    text = (BASIS / pfad).read_text(encoding="utf-8")
    return text.replace("</script", "<\\/script")


TESTHINWEIS = (
    '<div class="sperre" style="background:#fff5de;color:#9a6200;border-color:#9a6200">'
    '<strong>TESTVERSION – automatische Fortsetzungsseite im PDF</strong>'
    'Die erzeugten PDFs (Dateinamen beginnen mit TEST_) legen beim Verlassen der „Begründung der Gesamtnote“ '
    'selbst eine Fortsetzungsseite an, wenn der Text länger als das Feld ist – sofern das PDF-Programm das erlaubt '
    '(Adobe Acrobat Reader bzw. PDF-XChange testen). Klappt es nicht, erscheint im PDF ein Hinweis; dann wie '
    'gewohnt mit der normalen Beurteilung.html fertigstellen.</div>')


def main(vordruck, ziel, test=False):
    html = (BASIS / "src/ui.html").read_text(encoding="utf-8")
    teile = {
        "/*PDFLIB*/": skript("vendor/pdf-lib.min.js"),
        "/*XLSX*/": skript("vendor/xlsx.mini.min.js"),
        "/*JSZIP*/": skript("vendor/jszip.min.js"),
        "/*ENGINE*/": skript("src/engine.js"),
        "/*VORDRUCK*/": base64.b64encode(pathlib.Path(vordruck).read_bytes()).decode("ascii"),
        "/*BUILD*/": datetime.date.today().strftime("%d.%m.%Y") + (" · TESTVERSION" if test else ""),
        "/*TESTHINWEIS*/": TESTHINWEIS if test else "",
        "/*TESTFLAG*/false": "true" if test else "false",
    }
    for marke, inhalt in teile.items():
        if html.count(marke) != 1:
            sys.exit(f"Platzhalter {marke} fehlt in ui.html")
        html = html.replace(marke, inhalt)
    if test:
        html = html.replace("<title>Beurteilungen erstellen</title>", "<title>TEST – Beurteilungen erstellen</title>")
    pathlib.Path(ziel).write_text(html, encoding="utf-8")
    print(f"{ziel}: {len(html) / 1e6:.1f} MB")



if __name__ == "__main__":
    argumente = [a for a in sys.argv[1:] if a != "--test"]
    main(argumente[0], argumente[1], test="--test" in sys.argv)
