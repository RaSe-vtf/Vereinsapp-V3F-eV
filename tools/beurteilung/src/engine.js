/*
 * Beurteilungs-Tool – Kern (ohne Oberflaeche).
 *
 * Liest die aufbereitete Notenuebersicht (Excel) und befuellt den
 * vorbereiteten Vordruck BPOL 4 00 069. Laeuft im Browser (Beurteilung.html)
 * und unter Node (Tests): module.exports = fabrik(PDFLib, XLSX).
 */
(function (root, fabrik) {
  if (typeof module === 'object' && module.exports) module.exports = fabrik;
  else root.BeurteilungEngine = fabrik(root.PDFLib, root.XLSX);
})(this, function (PDFLib, XLSX) {
  'use strict';
  const { PDFDocument, PDFName, PDFNumber, PDFString, StandardFonts, rgb } = PDFLib;

  const START = 'beurteilungen erstellen';
  const BEURTEILER = 'beurteiler';
  const EINSTELLUNGEN = 'einstellungen';
  const FUNKTIONEN = 'funktionen';

  const SCHRIFT_PT = 10;
  const ZEILENABSTAND = 1.15;
  const INNENABSTAND = 2;
  const BEGRUENDUNG = 'f.begruend.1';
  const FERTIG_MARKE = 'BeurteilungFertiggestellt';

  // Notenfelder im Vordruck: [Erstbeurteilende/r, Zweitbeurteilende/r]
  const NOTENFELDER = {
    n11: ['f.dd.1', 'f.dd.2'],     // 1.1 Qualitaet und Verwertbarkeit
    n2: ['f.dd.7', 'f.dd.8'],      // 2   Fachkenntnisse
    n42: ['f.dd.23', 'f.dd.24'],   // 4.2 Zuverlaessigkeit
    n43: ['f.dd.25', 'f.dd.26'],   // 4.3 Zusammenarbeit
    endnote: ['f.dd.49', 'f.dd.50'], // IV Gesamtnote
  };
  // Endnote zusaetzlich unter "G Gesamtbewertung" (Seite 3) – nur RBU/ALB
  const GESAMTBEWERTUNG = ['f.dd.47', 'f.dd.48'];
  const KOOP_FELDER = 6; // f.koorperation.1 .. .6 (Schreibweise des Vordrucks)
  // Felder, die der Vordruck bei "Beurteilungsbeitrag" per Skript ausblendet
  const ZWEIT_FELDER = [];
  for (let i = 2; i <= 48; i += 2) ZWEIT_FELDER.push('f.dd.' + i);
  ZWEIT_FELDER.push('f.dd.47', 'f.dd.49', 'f.dd.50');
  for (let i = 26; i <= 52; i += 2) ZWEIT_FELDER.push('f.kk.' + i);

  const ARTEN = {
    regelbeurteilung: { wert: 'Regelbeurteilung', kurz: 'RBU' },
    anlassbeurteilung: { wert: 'Anlassbeurteilung', kurz: 'ALB' },
    beurteilungsbeitrag: { wert: 'Beurteilungsbeitrag', kurz: 'BB' },
  };
  const NOTEN = ['A1', 'A2', 'B1', 'B2', 'B3', 'C'];

  // ------------------------------------------------------------------
  // Hilfen
  // ------------------------------------------------------------------
  function norm(v) {
    return String(v == null ? '' : v).replace(/\s+/g, ' ').trim().toLowerCase();
  }
  function text(v) {
    return v == null ? '' : String(v).trim();
  }
  function leer(v) {
    return text(v) === '';
  }
  function zweistellig(n) {
    return (n < 10 ? '0' : '') + n;
  }
  /** Excel-Seriennummer, Date oder Text -> "TT.MM.JJJJ" ('' wenn leer). */
  function datum(v) {
    if (v == null || v === '') return '';
    if (typeof v === 'number') {
      const d = new Date(Math.round((v - 25569) * 86400000));
      return zweistellig(d.getUTCDate()) + '.' + zweistellig(d.getUTCMonth() + 1) + '.' + d.getUTCFullYear();
    }
    if (v instanceof Date) {
      return zweistellig(v.getDate()) + '.' + zweistellig(v.getMonth() + 1) + '.' + v.getFullYear();
    }
    const s = String(v).trim();
    const iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) return iso[3] + '.' + iso[2] + '.' + iso[1];
    return s;
  }
  /** "12.03.2026; 04.11.2026" (oder eine Excel-Datumszahl) -> Liste von Daten. */
  function datumsliste(v) {
    if (v == null || v === '') return [];
    if (typeof v === 'number' || v instanceof Date) return [datum(v)];
    return String(v).split(/[;\n]+/).map((t) => datum(t.trim())).filter((t) => t !== '');
  }
  function zeilenVon(ws) {
    return XLSX.utils.sheet_to_json(ws, { header: 1, raw: true, defval: null, blankrows: true });
  }
  function blattbasis(titel) {
    return String(titel).replace(/\s*\(\d+\)\s*$/, '').trim();
  }

  /** Spalte A = Beschriftung, Spalte B = Wert. Beschriftungen normalisiert. */
  function beschriftungen(zeilen) {
    const m = {};
    for (const z of zeilen) {
      if (z && !leer(z[0]) && !(norm(z[0]) in m)) m[norm(z[0])] = z[1];
    }
    return m;
  }
  function wertMitPraefix(m, praefix) {
    for (const k of Object.keys(m)) if (k.startsWith(praefix)) return m[k];
    return undefined;
  }
  /** Tabelle unterhalb einer Kopfzeile (Spalte A == kopfA, Spalte B beginnt mit kopfB). */
  function tabelle(zeilen, kopfA, kopfB) {
    for (let i = 0; i < zeilen.length; i++) {
      const z = zeilen[i] || [];
      if (norm(z[0]) === kopfA && norm(z[1]).startsWith(kopfB)) {
        const daten = [];
        for (let j = i + 1; j < zeilen.length; j++) {
          const w = zeilen[j] || [];
          if ([0, 1, 2].every((k) => leer(w[k]))) break;
          daten.push(w);
        }
        return daten;
      }
    }
    return [];
  }

  // ------------------------------------------------------------------
  // Excel lesen
  // ------------------------------------------------------------------
  function leseArbeitsmappe(daten, dateiname) {
    const wb = XLSX.read(daten, { type: 'array', cellDates: false });
    const blatt = {};
    for (const n of wb.SheetNames) blatt[norm(n)] = n;
    if (!blatt[START]) {
      throw new Error('„' + dateiname + '“: Blatt „Beurteilungen erstellen“ fehlt – ist das die aufbereitete Notenübersicht?');
    }
    const warnungen = [];

    // Startseite
    const sz = zeilenVon(wb.Sheets[blatt[START]]);
    const s = beschriftungen(sz);
    const artText = norm(s['beurteilungsart']);
    const art = ARTEN[artText];
    if (!art) throw new Error('„' + dateiname + '“: Beurteilungsart „' + text(s['beurteilungsart']) + '“ unbekannt.');
    const start = {
      art: art,
      anlass: text(wertMitPraefix(s, 'anlass')),
      stichtag: datum(s['stichtag']),
      von: datum(s['beurteilungszeitraum von']),
      bis: datum(s['beurteilungszeitraum bis']),
      nurMarkierte: norm(s['auswahl']).startsWith('nur'),
      ohneNoteUeberspringen: norm(wertMitPraefix(s, 'mitarbeiter ohne neue note')) !== 'nein',
      zweitNoten: norm(wertMitPraefix(s, 'noten auch für zweit')) !== 'nein',
      erst: text(s['erstbeurteilende/r']),
      zweit: text(s['zweitbeurteilende/r']),
      blaetter: {},
      zuege: {},
    };
    for (const z of tabelle(sz, 'blatt', 'einbeziehen')) start.blaetter[norm(z[0])] = norm(z[1]) !== 'nein';
    for (const z of tabelle(sz, 'zug', 'erstbeurteilende')) {
      start.zuege[norm(z[0])] = { erst: text(z[1]), zweit: text(z[2]) };
    }
    if (!start.von || !start.bis) warnungen.push('Beurteilungszeitraum (von/bis) ist auf der Startseite nicht ausgefüllt.');
    if (art.kurz === 'RBU' && !start.stichtag) warnungen.push('Stichtag ist nicht ausgefüllt.');

    // Beurteiler
    const beurteiler = {};
    if (blatt[BEURTEILER]) {
      const bz = zeilenVon(wb.Sheets[blatt[BEURTEILER]]);
      const kopf = bz.findIndex((z) => z && z.some((v) => norm(v) === 'name') && z.some((v) => norm(v) === 'vorname'));
      if (kopf >= 0) {
        const sp = {};
        bz[kopf].forEach((v, i) => { sp[norm(v)] = i; });
        for (const z of bz.slice(kopf + 1)) {
          if (!z || leer(z[sp['name']])) continue;
          const b = {
            amtsbez: text(z[sp['amtsbezeichnung']]),
            name: text(z[sp['name']]),
            vorname: text(z[sp['vorname']]),
            funktion: text(z[sp['funktion']]),
          };
          beurteiler[norm(b.name + ', ' + b.vorname)] = b;
        }
      }
    } else {
      warnungen.push('Blatt „Beurteiler“ fehlt.');
    }

    // Einstellungen
    const einst = { dienststelle: '', oe: {}, amt: {} };
    if (blatt[EINSTELLUNGEN]) {
      const ez = zeilenVon(wb.Sheets[blatt[EINSTELLUNGEN]]);
      einst.dienststelle = text(beschriftungen(ez)['dienststelle']);
      for (const z of tabelle(ez, 'zug', 'organisationseinheit')) einst.oe[norm(z[0])] = text(z[1]);
      for (const z of tabelle(ez, 'kürzel', 'amtsbezeichnung')) {
        if (!leer(z[0])) einst.amt[norm(z[0])] = { m: text(z[1]), w: text(z[2]), bg: text(z[3]) };
      }
    }

    // Funktionen / Anforderungsprofile
    const funktionen = {};
    if (blatt[FUNKTIONEN]) {
      const fz = zeilenVon(wb.Sheets[blatt[FUNKTIONEN]]);
      const kopf = fz.findIndex((z) => z && norm(z[0]) === 'funktionsbezeichnung');
      if (kopf >= 0) {
        for (const z of fz.slice(kopf + 1)) {
          if (!z || leer(z[0])) continue;
          funktionen[norm(z[0])] = {
            bezeichnung: text(z[0]),
            wertigkeit: text(z[1]),
            taetigkeiten: z.slice(2).map(text).filter((t) => t !== ''),
          };
        }
      }
    }

    // Notenblaetter: jedes Blatt mit Kopfzeile "Lfd.Nr." + "Name"
    const notenblaetter = [];
    for (const name of wb.SheetNames) {
      if ([START, BEURTEILER, EINSTELLUNGEN].includes(norm(name))) continue;
      const z = zeilenVon(wb.Sheets[name]);
      const kopf = z.findIndex((r) => r && r.slice(0, 6).some((v) => norm(v) === 'lfd.nr.') && r.some((v) => norm(v) === 'name'));
      if (kopf < 0) continue;
      const sp = {};
      z[kopf].forEach((v, i) => {
        const k = norm(v);
        if (k && !(k in sp)) sp[k] = i;
      });
      const col = (praefix) => {
        for (const k of Object.keys(sp)) if (k.startsWith(praefix)) return sp[k];
        return -1;
      };
      const c = {
        amtsbez: col('amtsbez'), name: sp['name'], vorname: col('vorname'), geschlecht: col('geschlecht'),
        geb: col('geb'), zug: col('zug'), endnote: col('neue rbu'), n11: col('1.1'), n2: col('2.'),
        n42: col('4.2'), n43: col('4.3'), ernennung: col('datum der ernennung'), pdf: col('pdf'),
        funktion: col('funktion'), koop: col('kooperation'), gespraech: col('gespräch vor'),
        sbh: col('schwerbehind'),
      };
      // Beruecksichtigte Beurteilungen: Anlassbeurteilung + bis zu 3 Beitraege
      const genau = (k) => (k in sp ? sp[k] : -1);
      const frueher = [['Anlassbeurteilung', 'alb']].concat([1, 2, 3].map((i) => ['Beurteilungsbeitrag', 'bb ' + i]))
        .map(([art, k]) => ({ art: art, spalte: k.toUpperCase(), x: genau(k + ' (x)'), von: genau(k + ' von'), bis: genau(k + ' bis') }))
        .filter((f) => f.x >= 0);
      for (const [k, v] of Object.entries(c)) {
        if (v < 0 && !['pdf', 'zug', 'geschlecht', 'amtsbez', 'funktion', 'koop', 'gespraech', 'sbh'].includes(k)) {
          warnungen.push('Blatt „' + name + '“: Spalte für „' + k + '“ nicht gefunden.');
        }
      }
      const personen = [];
      for (let i = kopf + 1; i < z.length; i++) {
        const r = z[i] || [];
        // Personenzeile = Name gefuellt; die Kennziffern-Zeile (A01, A02, ...) direkt
        // unter der Kopfzeile zaehlt nicht. Lfd.Nr. ist oft eine Formel und hat in
        // nie in Excel gespeicherten Dateien keinen Wert – daher nicht darauf pruefen.
        if (leer(r[c.name]) || /^A\d{2}$/.test(text(r[c.name]))) continue;
        const wert = (k) => (c[k] >= 0 ? r[c[k]] : null);
        personen.push({
          blatt: name,
          zeile: i + 1,
          amtsbez: text(wert('amtsbez')),
          name: text(wert('name')),
          vorname: text(wert('vorname')),
          geschlecht: norm(wert('geschlecht')),
          geb: datum(wert('geb')),
          zug: wert('zug'),
          ernennung: datum(wert('ernennung')),
          markiert: !leer(wert('pdf')),
          funktion: text(wert('funktion')),
          koop: datumsliste(wert('koop')),
          gespraech: datum(wert('gespraech')),
          sbh: norm(wert('sbh')),
          frueher: frueher.map((f) => ({
            art: f.art, spalte: f.spalte, x: !leer(r[f.x]), von: f.von >= 0 ? datum(r[f.von]) : '', bis: f.bis >= 0 ? datum(r[f.bis]) : '',
          })).filter((f) => f.x || f.von || f.bis),
          noten: {
            n11: text(wert('n11')).toUpperCase(), n2: text(wert('n2')).toUpperCase(),
            n42: text(wert('n42')).toUpperCase(), n43: text(wert('n43')).toUpperCase(),
            endnote: text(wert('endnote')).toUpperCase(),
          },
        });
      }
      notenblaetter.push({ name: name, personen: personen });
    }
    if (!notenblaetter.length) throw new Error('„' + dateiname + '“: keine Notenblätter gefunden.');
    return { dateiname, start, beurteiler, einst, funktionen, notenblaetter, warnungen };
  }

  // ------------------------------------------------------------------
  // Auswahl + Aufloesen (Amtsbezeichnung, Dienststelle, Beurteiler)
  // ------------------------------------------------------------------
  function beurteilerText(mappe, auswahl, rolle, hinweise) {
    if (leer(auswahl)) return '';
    const b = mappe.beurteiler[norm(auswahl)];
    if (!b) {
      hinweise.push(rolle + ' „' + auswahl + '“ steht nicht im Blatt „Beurteiler“ – Text wird unverändert übernommen.');
      return auswahl;
    }
    return [b.amtsbez, b.name, b.vorname, b.funktion].filter((t) => !leer(t)).join(', ');
  }

  function amtsbezeichnung(mappe, p, hinweise) {
    const blattK = blattbasis(p.blatt);
    let kuerzel = p.amtsbez || blattK;
    const weiblich = p.geschlecht === 'w' || /in$/.test(kuerzel);
    let basis = kuerzel.replace(/in$/, '');
    if (!(norm(basis) in mappe.einst.amt) && norm(kuerzel) in mappe.einst.amt) basis = kuerzel;
    // Blatt mit Amtszulage (z.B. PHMZ): Personen dort tragen oft nur "PHM"
    if (/Z$/.test(blattK) && blattK.slice(0, -1) === basis && norm(blattK) in mappe.einst.amt) basis = blattK;
    const eintrag = mappe.einst.amt[norm(basis)];
    if (!eintrag) {
      hinweise.push('Amtsbezeichnung „' + kuerzel + '“ fehlt in „Einstellungen“ – Kürzel wird eingetragen.');
      return kuerzel;
    }
    const bez = (weiblich ? eintrag.w : eintrag.m) || eintrag.m || kuerzel;
    return eintrag.bg ? bez + ' (' + eintrag.bg + ')' : bez;
  }

  function dienststelle(mappe, p) {
    const zug = leer(p.zug) ? '(ohne zug)' : norm(p.zug);
    let oe = mappe.einst.oe[zug];
    if (oe === undefined) oe = leer(p.zug) ? '' : (typeof p.zug === 'number' ? p.zug + '. Zug' : text(p.zug));
    return [mappe.einst.dienststelle, oe].filter((t) => !leer(t)).join(', ');
  }

  /** "TT.MM.JJJJ" -> sortierbare Zahl JJJJMMTT (unbekannt: 0). */
  function tagZahl(t) {
    const m = String(t).match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
    return m ? +m[3] * 10000 + +m[2] * 100 + +m[1] : 0;
  }

  /**
   * Textbaustein fuer "Allgemeine Bemerkungen" (Seite 5): welche Anlass-
   * beurteilung/Beurteilungsbeitraege beruecksichtigt wurden. Leer, wenn keine.
   */
  function bemerkungText(liste) {
    if (!liste.length) return '';
    if (liste.length === 1) {
      const f = liste[0];
      return (f.art === 'Anlassbeurteilung' ? 'Die ' : 'Der ') + f.art + ' vom ' + f.von + ' bis ' + f.bis +
        ' wurde in dieser Beurteilung berücksichtigt.';
    }
    return ['Folgende Beurteilungen wurden in dieser Beurteilung berücksichtigt:']
      .concat(liste.map((f) => '- ' + f.art + ' vom ' + f.von + ' bis ' + f.bis)).join('\n');
  }

  /** Liefert die zu erzeugenden Beurteilungen einer Arbeitsmappe. */
  function auftraege(mappe) {
    const st = mappe.start;
    const liste = [];
    const uebersprungen = [];
    for (const bl of mappe.notenblaetter) {
      if (st.blaetter[norm(bl.name)] === false) continue;
      for (const p of bl.personen) {
        if (st.nurMarkierte && !p.markiert) continue;
        if (st.ohneNoteUeberspringen && leer(p.noten.endnote)) {
          if (!st.nurMarkierte) { uebersprungen.push(p); continue; }
        }
        const hinweise = [];
        const zugWahl = st.zuege[norm(p.zug)] || {};
        const erstWahl = zugWahl.erst || st.erst;
        const zweitWahl = zugWahl.zweit || st.zweit;
        if (leer(erstWahl)) hinweise.push('keine/n Erstbeurteilende/n ausgewählt');
        for (const [k, v] of Object.entries(p.noten)) {
          if (!leer(v) && !NOTEN.includes(v)) hinweise.push('Note „' + v + '“ (' + k + ') ist keine gültige Notenstufe');
        }
        if (leer(p.noten.endnote) && st.art.kurz !== 'BB') hinweise.push('keine neue Note (Gesamtnote) eingetragen');
        let funktion = null;
        if (!leer(p.funktion)) {
          funktion = mappe.funktionen[norm(p.funktion)] || null;
          if (!funktion) hinweise.push('Funktion „' + p.funktion + '“ steht nicht im Blatt „Funktionen“');
        }
        if (p.koop.length > KOOP_FELDER) {
          hinweise.push(p.koop.length + ' Kooperationsgespräche – im Vordruck ist Platz für ' + KOOP_FELDER +
            ', die übrigen bitte im PDF ergänzen');
        }
        if (!leer(p.sbh) && !['ja', 'nein'].includes(p.sbh)) hinweise.push('Schwerbehinderung „' + p.sbh + '“ ist weder ja noch nein');
        const beruecksichtigt = [];
        for (const f of p.frueher) {
          if (!f.x) {
            hinweise.push(f.spalte + ': Zeitraum eingetragen, aber kein x gesetzt – nicht berücksichtigt');
          } else if (leer(f.von) || leer(f.bis)) {
            hinweise.push(f.spalte + ': x gesetzt, aber „' + f.spalte + ' ' + (leer(f.von) ? 'von' : 'bis') +
              '“ fehlt – nicht eingetragen, bitte im PDF unter „Allgemeine Bemerkungen“ ergänzen');
          } else {
            if (tagZahl(f.von) > tagZahl(f.bis)) hinweise.push(f.spalte + ': „von“ (' + f.von + ') liegt nach „bis“ (' + f.bis + ')');
            beruecksichtigt.push(f);
          }
        }
        beruecksichtigt.sort((x, y) => tagZahl(x.von) - tagZahl(y.von));
        liste.push({
          person: p,
          art: st.art,
          anlass: st.anlass,
          stichtag: st.stichtag,
          von: st.von,
          bis: st.bis,
          zweitNoten: st.zweitNoten,
          amtsbez: amtsbezeichnung(mappe, p, hinweise),
          dienststelle: dienststelle(mappe, p),
          funktion: funktion,
          beruecksichtigt: beruecksichtigt,
          bemerkung: bemerkungText(beruecksichtigt),
          erst: beurteilerText(mappe, erstWahl, 'Erstbeurteilende/r', hinweise),
          zweit: beurteilerText(mappe, zweitWahl, 'Zweitbeurteilende/r', hinweise),
          hinweise: hinweise,
        });
      }
    }
    return { liste, uebersprungen };
  }

  // ------------------------------------------------------------------
  // Text setzen: Zeilenumbruch, Zeichenvorrat
  // ------------------------------------------------------------------
  /** Ersetzt Zeichen, die die Standardschrift nicht kennt. */
  function bereinige(font, s) {
    s = String(s).replace(/\r\n?/g, '\n').replace(/\t/g, '    ').replace(/[\u2028\u2029]/g, '\n');
    let aus = '';
    for (const ch of s) {
      if (ch === '\n') { aus += ch; continue; }
      try { font.encodeText(ch); aus += ch; } catch (e) { aus += '?'; }
    }
    return aus;
  }

  /** Bricht Text wortweise auf eine Breite um (Absaetze bleiben erhalten). */
  function umbrechen(font, groesse, s, breite) {
    const zeilen = [];
    for (const absatz of s.split('\n')) {
      if (absatz.trim() === '') { zeilen.push(''); continue; }
      const woerter = absatz.split(/ +/);
      let zeile = '';
      for (let wort of woerter) {
        const kandidat = zeile ? zeile + ' ' + wort : wort;
        if (font.widthOfTextAtSize(kandidat, groesse) <= breite) { zeile = kandidat; continue; }
        if (zeile) zeilen.push(zeile);
        // Ueberlange Woerter hart trennen
        while (font.widthOfTextAtSize(wort, groesse) > breite) {
          let n = wort.length - 1;
          while (n > 1 && font.widthOfTextAtSize(wort.slice(0, n), groesse) > breite) n--;
          zeilen.push(wort.slice(0, n));
          wort = wort.slice(n);
        }
        zeile = wort;
      }
      zeilen.push(zeile);
    }
    return zeilen;
  }

  // ------------------------------------------------------------------
  // PDF: Felder setzen
  // ------------------------------------------------------------------
  function widgetsVon(form, name) {
    return form.getField(name).acroField.getWidgets();
  }
  function setzeAnkreuz(form, name, exportwert) {
    const feld = form.getField(name);
    const an = PDFName.of(exportwert);
    let gefunden = false;
    for (const w of feld.acroField.getWidgets()) {
      const onWert = w.getOnValue();
      const treffer = onWert && onWert.toString() === an.toString();
      w.setAppearanceState(treffer ? onWert : PDFName.of('Off'));
      gefunden = gefunden || treffer;
    }
    if (!gefunden) throw new Error('Ankreuzfeld ' + name + ' kennt den Wert „' + exportwert + '“ nicht');
    feld.acroField.dict.set(PDFName.of('V'), an);
  }
  function setzeSichtbar(form, name, sichtbar) {
    let feld;
    try { feld = form.getField(name); } catch (e) { return; }
    for (const w of feld.acroField.getWidgets()) {
      const f = w.dict.get(PDFName.of('F'));
      let flags = f ? f.asNumber() : 0;
      flags = sichtbar ? (flags & ~2) | 4 : flags | 2;
      w.dict.set(PDFName.of('F'), PDFNumber.of(flags));
    }
  }
  // Nur die Felder, die das Tool tatsaechlich befuellt, bekommen eine neue
  // Darstellung. Alle anderen (v.a. die Ankreuzfelder mit den Kreuzen des
  // Vordrucks) behalten ihre Original-Darstellung – pdf-lib wuerde sie sonst
  // durch eigene Haken ersetzen.
  const GEAENDERT = new WeakMap();
  function merke(form, feld) {
    if (!GEAENDERT.has(form)) GEAENDERT.set(form, new Set());
    GEAENDERT.get(form).add(feld);
  }
  function setzeText(form, name, wert) {
    if (leer(wert)) return;
    const f = form.getTextField(name);
    f.setText(String(wert));
    merke(form, f);
  }
  function setzeNote(form, name, note) {
    if (leer(note)) return;
    const dd = form.getDropdown(name);
    if (dd.getOptions().includes(note)) {
      dd.select(note);
      merke(form, dd);
    }
  }

  function sichereDA(form) {
    const DA = PDFName.of('DA');
    const liste = [];
    for (const f of form.getFields()) {
      const dicts = [f.acroField.dict].concat(f.acroField.getWidgets().map((w) => w.dict));
      for (const d of dicts) liste.push([d, d.get(DA)]);
    }
    return liste;
  }
  function stelleDAwieder(liste) {
    const DA = PDFName.of('DA');
    for (const [d, wert] of liste) {
      if (wert) d.set(DA, wert);
      else d.delete(DA);
    }
  }

  /**
   * Taetigkeiten im Stil der Anforderungsprofile: "- " davor, Folgezeilen
   * eingerueckt, Leerzeile dazwischen – fertig umbrochen auf die Feldbreite.
   */
  function taetigkeitenText(font, feld, taetigkeiten) {
    const breite = feldGeometrie(feld).breite;
    const g = schriftgroesse(feld);
    const bloecke = taetigkeiten.map((x) => {
      const zeilen = umbrechen(font, g, bereinige(font, x).replace(/\n/g, ' '), breite - font.widthOfTextAtSize('- ', g));
      return zeilen.map((z, i) => (i === 0 ? '- ' : '  ') + z).join('\n');
    });
    return bloecke.join('\n\n');
  }

  function fusszeile(a) {
    const p = a.person;
    let v = a.art.wert + ' für ' + p.name + ', ' + p.vorname + '; geb. ' + p.geb;
    if (a.stichtag && a.art.kurz === 'RBU') v += '; Stichtag: ' + a.stichtag; // nur dort ausgefuellt
    return v;
  }

  function dateinamensteil(s) {
    return String(s).normalize('NFC')
      .replace(/Ä/g, 'Ae').replace(/Ö/g, 'Oe').replace(/Ü/g, 'Ue').replace(/ä/g, 'ae').replace(/ö/g, 'oe')
      .replace(/ü/g, 'ue').replace(/ß/g, 'ss')
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^A-Za-z0-9._-]+/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '').slice(0, 40);
  }

  function dateiname(a) {
    const p = a.person;
    // Windows-/ZIP-sicher: keine Umlaute, keine Sonderzeichen, begrenzte Laenge
    const teil = (s) => dateinamensteil(s);
    const tag = (a.stichtag || a.bis || '').split('.').reverse().join('-');
    return [teil(p.name), teil(p.vorname), a.art.kurz, tag].filter(Boolean).join('_') + '.pdf';
  }

  /** Erzeugt die vorbefuellte Beurteilung (weiter ausfuellbar). */
  async function erzeugePdf(vordruck, a) {
    const pdf = await PDFDocument.load(vordruck);
    const form = pdf.getForm();
    const courier = await pdf.embedFont(StandardFonts.Courier);
    const p = a.person;
    const t = (s) => bereinige(courier, s);

    setzeAnkreuz(form, 'f.kk.0', a.art.wert);
    setzeAnkreuz(form, 'f.kk.222', 'On'); // Beamtin/Beamter
    // Sichtbarkeit wie die Skripte des Vordrucks beim Anklicken der Art
    setzeSichtbar(form, 'f.zusatz', a.art.kurz === 'ALB');
    for (const n of ZWEIT_FELDER) setzeSichtbar(form, n, a.art.kurz !== 'BB');

    if (a.art.kurz === 'ALB') setzeText(form, 'f.zusatz', t(a.anlass));
    if (a.art.kurz === 'RBU') setzeText(form, 'f.stichtag', a.stichtag);
    setzeText(form, 'f.von.1', a.von);
    setzeText(form, 'f.bis.1', a.bis);
    setzeText(form, 'f.name', t(p.name + ', ' + p.vorname));
    setzeText(form, 'f.gebdat', p.geb);
    setzeText(form, 'f.amtsbez.1', t(a.amtsbez));
    setzeText(form, 'f.ernenn.1', p.ernennung);
    setzeText(form, 'f.dienststelle.1', t(a.dienststelle));
    setzeText(form, 'f.erstbeurt.1', t(a.erst));
    setzeText(form, 'f.zweitbeurt.1', t(a.zweit));
    setzeText(form, 'h.fusszeile', t(fusszeile(a)));

    if (a.funktion) {
      setzeText(form, 'f.funktion.1', t(a.funktion.bezeichnung));
      if (a.funktion.wertigkeit) {
        setzeText(form, 'f.funktion.2', t('(' + a.funktion.wertigkeit + ')')); // Schreibweise der Profile
        setzeText(form, 'f.wert.1', t(a.funktion.wertigkeit)); // Seite 2, Nr. 4.1.2 (schmales Feld)
      }
      setzeText(form, 'f.taetigkeit.1', taetigkeitenText(courier, form.getTextField('f.taetigkeit.1'),
        a.funktion.taetigkeiten));
    }
    p.koop.slice(0, KOOP_FELDER).forEach((d, i) => setzeText(form, 'f.koorperation.' + (i + 1), d));
    setzeText(form, 'f.gespraech.1', p.gespraech);
    // Allgemeine Bemerkungen: beruecksichtigte Anlassbeurteilung/Beitraege
    // (ohne feste Umbrueche – das mehrzeilige Feld bricht selbst um)
    setzeText(form, 'f.allg.1', t(a.bemerkung));
    if (p.sbh === 'ja' || p.sbh === 'nein') {
      // wie das Skript des Vordrucks (kk_schwerbehindert): bei "nein" bleiben
      // Einverstaendnis und Gespraech mit der Vertrauensperson gesperrt
      setzeAnkreuz(form, 'f.kk.schwerbehindert', p.sbh === 'ja' ? 'Ja' : 'Nein');
      const einv = form.getField('f.kk.einverstaendnis');
      if (p.sbh === 'ja') einv.disableReadOnly(); else einv.enableReadOnly();
    }

    for (const [k, [erst, zweit]] of Object.entries(NOTENFELDER)) {
      if (a.art.kurz === 'BB' && k === 'endnote') continue; // Beitrag hat keine Gesamtnote
      setzeNote(form, erst, p.noten[k]);
      if (a.zweitNoten && a.art.kurz !== 'BB') setzeNote(form, zweit, p.noten[k]);
    }
    if (a.art.kurz !== 'BB') {
      setzeNote(form, GESAMTBEWERTUNG[0], p.noten.endnote);
      if (a.zweitNoten) setzeNote(form, GESAMTBEWERTUNG[1], p.noten.endnote);
    }

    // pdf-lib schreibt beim Erzeugen der Darstellung eine eigene Schriftangabe
    // (DA) ins Feld. Die des Vordrucks (/Cour 10 Tf) wiederherstellen, damit
    // Acrobat beim spaeteren Weiterschreiben die Schrift des Formulars nimmt.
    const daSicherung = sichereDA(form);
    for (const f of GEAENDERT.get(form) || []) f.updateAppearances(courier);
    stelleDAwieder(daSicherung);
    pdf.setTitle('Dienstliche Beurteilung ' + p.name + ', ' + p.vorname);
    return pdf.save({ updateFieldAppearances: false });
  }

  // ------------------------------------------------------------------
  // Fertigstellen: Begruendung ueber Fortsetzungsseiten, Pruefung
  // ------------------------------------------------------------------
  function feldGeometrie(feld) {
    const w = feld.acroField.getWidgets()[0];
    const r = w.getRectangle();
    const bs = w.getBorderStyle();
    const rand = (bs ? bs.getWidth() : 1) + INNENABSTAND;
    return { rect: r, breite: r.width - 2 * rand, hoehe: r.height - 2 * rand, rand };
  }
  function passendeZeilen(geo, groesse) {
    return Math.max(1, Math.floor((geo.hoehe + (ZEILENABSTAND - 1) * groesse) / (groesse * ZEILENABSTAND)));
  }
  function schriftgroesse(feld) {
    const da = feld.acroField.getDefaultAppearance() || '';
    const m = da.match(/([\d.]+)\s+Tf/);
    const g = m ? parseFloat(m[1]) : SCHRIFT_PT;
    return g > 0 ? g : SCHRIFT_PT;
  }

  /** Eigene Darstellung eines mehrzeiligen Feldes (exakt unsere Zeilen). */
  function zeichneFeld(feld, font, groesse, zeilen) {
    const ops = PDFLib;
    feld.updateAppearances(font, (f, widget) => {
      const r = widget.getRectangle();
      const rand = 1 + INNENABSTAND;
      const lh = groesse * ZEILENABSTAND;
      const liste = [
        ops.pushGraphicsState(), ops.beginMarkedContent('Tx'), ops.beginText(),
        ops.setFontAndSize(font.name, groesse), ops.setFillingGrayscaleColor(0),
      ];
      let y = r.height - rand - groesse * 0.8;
      liste.push(ops.moveText(rand, y));
      zeilen.forEach((z, i) => {
        if (i > 0) liste.push(ops.moveText(0, -lh));
        liste.push(ops.showText(font.encodeText(z)));
      });
      liste.push(ops.endText(), ops.endMarkedContent(), ops.popGraphicsState());
      return liste;
    });
  }

  async function fertigstellen(bytes, dateiname) {
    let pdf;
    try {
      pdf = await PDFDocument.load(bytes);
    } catch (e) {
      if (/encrypt/i.test(e.message)) {
        throw new Error('„' + dateiname + '“ ist mit einem Kennwort/Schutz gespeichert. Bitte im PDF-Programm ohne ' +
          'Sicherheitseinstellungen speichern und erneut hineinziehen.');
      }
      throw new Error('„' + dateiname + '“ lässt sich nicht als PDF lesen (' + e.message + ').');
    }
    const info = pdf.getInfoDict();
    if (info.get(PDFName.of(FERTIG_MARKE))) {
      throw new Error('„' + dateiname + '“ wurde bereits fertiggestellt. Bitte die ausgefüllte Ausgangsdatei verwenden.');
    }
    const form = pdf.getForm();
    if (!form.getFields().length) {
      throw new Error('„' + dateiname + '“ enthält keine Formularfelder mehr – vermutlich wurde es über „Drucken als PDF“ ' +
        'gespeichert. Bitte die ausgefüllte Beurteilung mit „Speichern“ bzw. „Speichern unter“ sichern.');
    }
    let feld;
    try { feld = form.getTextField(BEGRUENDUNG); } catch (e) {
      throw new Error('„' + dateiname + '“ ist kein Beurteilungsvordruck BPOL 4 00 069.');
    }
    const courier = await pdf.embedFont(StandardFonts.Courier);
    const helv = await pdf.embedFont(StandardFonts.Helvetica);
    const helvB = await pdf.embedFont(StandardFonts.HelveticaBold);
    const hinweise = [];

    // Andere Felder pruefen (werden nicht veraendert, nur gemeldet)
    for (const f of form.getFields()) {
      if (f.getName() === BEGRUENDUNG || !(f instanceof PDFLib.PDFTextField) || f.isReadOnly()) continue;
      const wert = f.getText();
      if (leer(wert)) continue;
      const geo = feldGeometrie(f);
      const g = schriftgroesse(f);
      const zeilen = f.isMultiline()
        ? umbrechen(courier, g, bereinige(courier, wert), geo.breite).length
        : (courier.widthOfTextAtSize(bereinige(courier, wert).replace(/\n/g, ' '), g) > geo.breite ? 2 : 1);
      const platz = f.isMultiline() ? passendeZeilen(geo, g) : 1;
      if (zeilen > platz) {
        hinweise.push('Feld „' + feldBezeichnung(f.getName()) + '“ ist zu lang (' + zeilen + ' statt max. ' + platz +
          ' Zeilen) – bitte kürzen, es wird sonst nicht vollständig gedruckt.');
      }
    }

    const g = SCHRIFT_PT;
    const geo = feldGeometrie(feld);
    const alles = umbrechen(courier, g, bereinige(courier, feld.getText() || ''), geo.breite);
    while (alles.length && alles[alles.length - 1] === '') alles.pop();
    const platz = passendeZeilen(geo, g);
    let seiten = 0;

    if (alles.length > platz) {
      const hinweisZeile = '(Fortsetzung auf der nächsten Seite)';
      const teil1 = alles.slice(0, platz - 1);
      const rest = alles.slice(platz - 1);
      teil1.push(hinweisZeile);
      zeichneFeld(feld, courier, g, teil1);
      feld.acroField.dict.set(PDFName.of('V'), PDFString.of(bereinige(courier, teil1.slice(0, -1).join('\n'))));
      feld.enableReadOnly();

      // Fortsetzungsseiten direkt hinter Seite 5 (Index 4)
      const seite5 = pdf.findPageForAnnotationRef(feld.acroField.ref);
      const seiteIdx = seite5 ? pdf.getPages().indexOf(seite5) : -1;
      const nachSeite = seiteIdx >= 0 ? seiteIdx : 4;
      const fuss = (() => { try { return form.getTextField('h.fusszeile').getText() || ''; } catch (e) { return ''; } })();
      const [breiteSeite, hoeheSeite] = [595.276, 841.89];
      const x = 58, rechts = 566, oben = 740, unten = 70;
      const lh = g * ZEILENABSTAND;
      const proSeite = Math.floor((oben - unten - 2 * (1 + INNENABSTAND)) / lh);
      let i = 0;
      while (i < rest.length) {
        seiten++;
        const seite = pdf.insertPage(nachSeite + seiten, [breiteSeite, hoeheSeite]);
        const rechtsText = (s, y, f, gr) => seite.drawText(s, { x: rechts - f.widthOfTextAtSize(s, gr), y, size: gr, font: f });
        rechtsText('Dienstliche Beurteilung in der Bundespolizei', 800, helvB, 10);
        rechtsText('(Anlage 4 BeurtRL BPOL)', 787, helv, 10);
        rechtsText('Zutreffendes bitte ankreuzen oder ausfüllen', 776, helv, 7);
        seite.drawText('Begründung der Gesamtnote', { x, y: oben + 8, size: 10, font: helv });
        seite.drawText('(vgl. Nr. 4.3 und 4.5) – Fortsetzung ' + seiten, {
          x: x + helv.widthOfTextAtSize('Begründung der Gesamtnote ', 10), y: oben + 8, size: 7, font: helv,
        });
        seite.drawRectangle({ x, y: unten, width: rechts - x, height: oben - unten, borderColor: rgb(0.45, 0.45, 0.45), borderWidth: 0.75 });
        let y = oben - (1 + INNENABSTAND) - g * 0.8;
        const ende = Math.min(rest.length, i + proSeite);
        const letzteSeite = ende >= rest.length;
        const stueck = rest.slice(i, letzteSeite ? ende : ende - 1);
        if (!letzteSeite) stueck.push(hinweisZeile);
        for (const z of stueck) {
          seite.drawText(z, { x: x + 1 + INNENABSTAND, y, size: g, font: courier });
          y -= lh;
        }
        i += letzteSeite ? stueck.length : stueck.length - 1;
        if (fuss) seite.drawText(fuss, { x: 176, y: 36, size: 8, font: courier });
        seite.drawText('BPOL 4 00 069 08 16  (Fortsetzungsblatt ' + seiten + ' zu Seite 5)', { x: 57, y: 22, size: 6, font: helv });
      }
      hinweise.unshift('Begründung der Gesamtnote: ' + alles.length + ' Zeilen – ' + seiten +
        ' Fortsetzungsseite' + (seiten > 1 ? 'n' : '') + ' hinter Seite 5 eingefügt.');
    } else if (alles.length) {
      zeichneFeld(feld, courier, g, alles);
      hinweise.unshift('Begründung der Gesamtnote passt auf Seite 5 – keine Fortsetzungsseite nötig.');
    } else {
      hinweise.unshift('Begründung der Gesamtnote ist leer.');
    }

    info.set(PDFName.of(FERTIG_MARKE), PDFString.of(new Date().toISOString()));
    const aus = await pdf.save({ updateFieldAppearances: false });
    return { bytes: aus, seiten, hinweise };
  }

  const FELDNAMEN = {
    'f.zusatz': 'Anlass', 'f.name': 'Name, Vorname', 'f.amtsbez.1': 'Amtsbezeichnung',
    'f.dienststelle.1': 'Dienststelle, Organisationseinheit', 'f.funktion.1': 'Funktionsbezeichnung',
    'f.funktion.2': 'Funktionswertigkeit', 'f.erstbeurt.1': 'Erstbeurteilende/r', 'f.zweitbeurt.1': 'Zweitbeurteilende/r',
    'f.taetigkeit.1': 'Die Funktion prägende Tätigkeiten (Seite 2)', 'f.bemerk.1': 'Allgemeine Bemerkungen/Begründung (Seite 4)',
    'f.allg.1': 'Allgemeine Bemerkungen (Seite 5)', 'f.besonders.1': 'Besondere Interessen und Verwendungswünsche (Seite 6)',
    'f.foerderung.1': 'Förderungs- und Verwendungsempfehlungen (Seite 6)', 'f.beurteilungsgespraech.1': 'Das Beurteilungsgespräch führte(n)',
    'f.manuell.1': 'weiteres Leistungsmerkmal 1', 'f.manuell.2': 'weiteres Leistungsmerkmal 2', 'f.ergaenzung.1': 'weiteres Befähigungsmerkmal',
  };
  function feldBezeichnung(n) {
    return FELDNAMEN[n] || n;
  }


  // ------------------------------------------------------------------
  // Liste leeren: Eingaben ausgewaehlter Notenblaetter loeschen (neue Datei)
  // ------------------------------------------------------------------
  const TABELLE_ZEILEN = 60;

  /** Notenblaetter einer aufbereiteten Notenuebersicht mit Personenzahl. */
  function leerenVorschau(daten, dateiname) {
    const wb = XLSX.read(daten, { type: 'array' });
    const blaetter = [];
    for (const name of wb.SheetNames) {
      const z = zeilenVon(wb.Sheets[name]);
      const kopf = z.findIndex((r) => r && r.slice(0, 6).some((v) => norm(v) === 'lfd.nr.') && r.some((v) => norm(v) === 'name'));
      if (kopf < 0) continue;
      const pdf = z[kopf].findIndex((v) => norm(v).startsWith('pdf'));
      const nameSp = z[kopf].findIndex((v) => norm(v) === 'name');
      if (pdf < 0) {
        throw new Error('„' + dateiname + '“: Blatt „' + name + '“ ist nicht aufbereitet (keine Spalte „PDF“).');
      }
      const erste = kopf + 3; // 1-basiert: Kopfzeile + Kennziffern-Zeile
      let personen = 0;
      for (let i = erste - 1; i < erste - 1 + TABELLE_ZEILEN && i < z.length; i++) {
        if (z[i] && !leer(z[i][nameSp])) personen++;
      }
      blaetter.push({ name: name, erste: erste, letzte: erste + TABELLE_ZEILEN - 1, breite: pdf + 1, personen: personen });
    }
    if (!blaetter.length) throw new Error('„' + dateiname + '“: keine Notenblätter gefunden.');
    return blaetter;
  }

  function spaltenNr(buchstaben) {
    let n = 0;
    for (const ch of buchstaben) n = n * 26 + ch.charCodeAt(0) - 64;
    return n;
  }
  function xmlText(s) {
    return String(s).replace(/&quot;/g, '"').replace(/&apos;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
  }

  /** Loescht in den Tabellenzeilen der Blaetter alle Eingaben; Formeln,
   *  Formate, Auswahllisten und Blattschutz bleiben. Liefert die neue Datei. */
  function leereZeilen(xml, blatt) {
    return xml.replace(/<row\b([^>]*[^\/])>([\s\S]*?)<\/row>|<row\b[^>]*\/>/g, (ganz, attr, inhalt) => {
      if (attr === undefined) return ganz;
      const r = +(attr.match(/\br="(\d+)"/) || [])[1];
      if (!(r >= blatt.erste && r <= blatt.letzte)) return ganz;
      const zellen = inhalt.replace(/<c\b([^>]*?)(?:\/>|>([\s\S]*?)<\/c>)/g, (zelle, cAttr, cInhalt) => {
        const ref = (cAttr.match(/\br="([A-Z]+)\d+"/) || [])[1];
        if (!ref || spaltenNr(ref) > blatt.breite) return zelle;
        const ohneTyp = cAttr.replace(/\s+t="[^"]*"/, '');
        const formel = (cInhalt || '').match(/<f\b[^>]*\/>|<f\b[^>]*>[\s\S]*?<\/f>/);
        return formel ? '<c' + ohneTyp + '>' + formel[0] + '</c>' : '<c' + ohneTyp + '/>';
      });
      const zeile = attr.replace(/\s+(ht|customHeight)="[^"]*"/g, '');
      return '<row' + zeile + '>' + zellen + '</row>';
    });
  }

  async function leeren(JSZip, daten, auswahl) {
    const zip = await JSZip.loadAsync(daten);
    let wbXml = await zip.file('xl/workbook.xml').async('string');
    const rels = await zip.file('xl/_rels/workbook.xml.rels').async('string');
    const ziele = {};
    for (const m of rels.matchAll(/<Relationship\b[^>]*>/g)) {
      const id = (m[0].match(/\bId="([^"]+)"/) || [])[1];
      const ziel = (m[0].match(/\bTarget="([^"]+)"/) || [])[1];
      if (id && ziel) ziele[id] = ziel.startsWith('/') ? ziel.slice(1) : 'xl/' + ziel;
    }
    const pfad = {};
    for (const m of wbXml.matchAll(/<sheet\b[^>]*>/g)) {
      const name = xmlText((m[0].match(/\bname="([^"]*)"/) || [])[1] || '');
      const id = (m[0].match(/\br:id="([^"]+)"/) || m[0].match(/\bid="([^"]+)"/) || [])[1];
      pfad[name] = ziele[id];
    }
    for (const blatt of auswahl) {
      const p = pfad[blatt.name];
      if (!p || !zip.file(p)) throw new Error('Blatt „' + blatt.name + '“ nicht gefunden.');
      zip.file(p, leereZeilen(await zip.file(p).async('string'), blatt));
    }
    // Excel rechnet beim Oeffnen alles neu (Statistik, Lfd.Nr., ...)
    if (/<calcPr\b/.test(wbXml)) {
      wbXml = wbXml.replace(/<calcPr\b([^>]*?)(\/?)>/, (m, a, zu) =>
        '<calcPr' + a.replace(/\s+fullCalcOnLoad="[^"]*"/, '') + ' fullCalcOnLoad="1"' + zu + '>');
    } else {
      wbXml = wbXml.replace(/(<\/definedNames>|<\/sheets>)(?![\s\S]*<\/definedNames>)/, '$1<calcPr fullCalcOnLoad="1"/>');
    }
    zip.file('xl/workbook.xml', wbXml);
    return zip.generateAsync({ type: 'uint8array', compression: 'DEFLATE' });
  }

  return { leseArbeitsmappe, auftraege, erzeugePdf, fertigstellen, dateiname, dateinamensteil, datum, umbrechen, bemerkungText,
    leerenVorschau, leeren };
});
