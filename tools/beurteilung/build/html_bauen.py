"""Baut die eigenstaendige Datei Beurteilung.html (offline, eine Datei).

Fuegt die Bibliotheken aus vendor/, den Kern src/engine.js und den
vorbereiteten Vordruck (Base64) in src/ui.html ein.

Aufruf: python3 html_bauen.py <vordruck_vorbereitet.pdf> <ziel.html>
"""
import base64
import pathlib
import sys

BASIS = pathlib.Path(__file__).resolve().parent.parent


def skript(pfad):
    text = (BASIS / pfad).read_text(encoding="utf-8")
    return text.replace("</script", "<\\/script")


def main(vordruck, ziel):
    html = (BASIS / "src/ui.html").read_text(encoding="utf-8")
    teile = {
        "/*PDFLIB*/": skript("vendor/pdf-lib.min.js"),
        "/*XLSX*/": skript("vendor/xlsx.mini.min.js"),
        "/*JSZIP*/": skript("vendor/jszip.min.js"),
        "/*ENGINE*/": skript("src/engine.js"),
        "/*VORDRUCK*/": base64.b64encode(pathlib.Path(vordruck).read_bytes()).decode("ascii"),
    }
    for marke, inhalt in teile.items():
        if html.count(marke) != 1:
            sys.exit(f"Platzhalter {marke} fehlt in ui.html")
        html = html.replace(marke, inhalt)
    pathlib.Path(ziel).write_text(html, encoding="utf-8")
    print(f"{ziel}: {len(html) / 1e6:.1f} MB")



if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
