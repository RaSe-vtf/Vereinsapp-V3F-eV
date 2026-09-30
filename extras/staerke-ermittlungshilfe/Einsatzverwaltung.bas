Attribute VB_Name = "Einsatzverwaltung"
Option Explicit

' =====================================================================
'  Einsatzverwaltung (Excel fuer Windows)
'  - EinsatzLaden                  : Einsatzgliederung in naechsten freien Platz laden
'  - EinsatzEntfernen              : einen geladenen Einsatz wieder entfernen
'  - DoppelgliederungenEntscheiden : offene Doppelgliederungen abfragen
'  - TabelleLeeren                 : Grunddaten leeren (Einsaetze + Entscheidungen bleiben)
'  - GrunddatenAufbereiten         : eingefuegten ePlan-Text in Spalten verteilen
'  - GliederungErstellen / GliederungAlsEinsatz / NeuePlanung
'                                  : Einsatzgliederung aus "Verfuegbarkeit" (siehe unten)
'  - EntscheidungJa / EntscheidungNein / EntscheidungLoeschen
'                                  : Wochenend-Pruefaelle entscheiden (gespeichert je
'                                    Person + Freitag in EinsatzDaten AT:AU)
'  Die Reiter sind geschuetzt (Blattschutz ohne Kennwort); die Makros heben
'  den Schutz kurz auf und setzen ihn danach wieder.
'  Die Zuordnung der Namen und das Setzen von E / EE im Reiter "Staerke"
'  erledigen die Formeln der Arbeitsmappe.
' =====================================================================

Private Const BLATT As String = "Einsatz importieren"
Private Const SLOT0 As Long = 10        ' Einsatz-Plaetze Zeilen 10..19
Private Const SLOTS As Long = 10
Private Const K0 As Long = 24           ' Doppelgliederungen / Entscheidungen Zeilen 24..33
Private Const KN As Long = 33
Private Const L0 As Long = 38           ' Personenliste Zeilen 38..1037
Private Const LN As Long = 1037
Private Const QUELLE As String = "A1:AN600"
Private Const KENNWORT As String = ""       ' Blattschutz-Kennwort (leer = ohne)
Private Const WE0 As Long = 27          ' Wochenend-Liste Zeilen 27..163
Private Const WEN As Long = 163
Private Const S0 As Long = 10           ' Speicher Wochenend-Entscheidungen EinsatzDaten AT:AU, Zeilen 10..509
Private Const SN As Long = 509
Private Const V0 As Long = 25           ' Liste in "Verfuegbarkeit" Zeilen 25..161
Private Const VN As Long = 161
Private Const GS0 As Long = 10          ' Speicher Zuweisungen EinsatzDaten AW:AZ
Private Const GSN As Long = 509
Private mAnzeige As Boolean

' ---------------------------------------------------------------------
Public Sub EinsatzLaden()
    Dim ws As Worksheet, wbQ As Workbook
    Dim datei As Variant, arr As Variant
    Dim platz As Long, k As Long, r As Long, n As Long, frei As Long
    Dim dVon As Date, dBis As Date, gefunden As Boolean
    Dim amt() As String, nam() As String
    Dim ausgabe() As Variant, bez As String, dname As String, s As String
    Dim d As String, e As String

    Set ws = ThisWorkbook.Worksheets(BLATT)

    ' naechster freier Platz
    For k = 1 To SLOTS
        If Trim$(CStr(ws.Cells(SLOT0 + k - 1, 2).Value)) = "" Then platz = k: Exit For
    Next k
    If platz = 0 Then
        MsgBox "Alle " & SLOTS & " Einsatz-Plaetze sind belegt." & vbCr & _
               "Bitte zuerst einen Einsatz entfernen.", vbExclamation, "Einsatz laden"
        Exit Sub
    End If

    datei = Application.GetOpenFilename("Excel-Dateien (*.xls*),*.xls*", , "Einsatzgliederung auswaehlen")
    If VarType(datei) = vbBoolean Then Exit Sub
    dname = DateiName(CStr(datei))

    ' gleiche Datei schon geladen?
    For k = 1 To SLOTS
        If StrComp(CStr(ws.Cells(SLOT0 + k - 1, 2).Value), dname, vbTextCompare) = 0 Then
            If MsgBox("Die Datei """ & dname & """ ist bereits als Einsatz " & k & " geladen." & vbCr & _
                      "Trotzdem zusaetzlich laden?", vbYesNo + vbQuestion, "Einsatz laden") = vbNo Then Exit Sub
            Exit For
        End If
    Next k

    On Error GoTo Fehler
    Application.ScreenUpdating = False
    Application.DisplayAlerts = False
    Set wbQ = Workbooks.Open(Filename:=CStr(datei), UpdateLinks:=0, ReadOnly:=True)
    Application.DisplayAlerts = True
    arr = wbQ.Worksheets(1).Range(QUELLE).Value
    wbQ.Close SaveChanges:=False
    Set wbQ = Nothing
    On Error GoTo 0

    ' Personen herausziehen (Amtsbez. Spalte D, Name Spalte E)
    ReDim amt(1 To 600): ReDim nam(1 To 600)
    For r = 1 To 600
        d = ZellText(arr(r, 4)): e = ZellText(arr(r, 5))
        If d <> "" And e <> "" And InStr(d, ":") = 0 And InStr(e, ":") = 0 Then
            n = n + 1: amt(n) = d: nam(n) = e
        End If
    Next r
    If n = 0 Then
        Application.ScreenUpdating = True
        MsgBox "In der Datei wurden keine Personen gefunden (Amtsbez. in Spalte D, Name in Spalte E)." & vbCr & _
               "Bitte pruefen, ob die Gliederung in der vorgegebenen Form vorliegt.", vbExclamation, "Einsatz laden"
        Exit Sub
    End If

    ' freie Zeilen in der Personenliste
    frei = ErsteFreieZeile(ws)
    If frei + n - 1 > LN Then
        Application.ScreenUpdating = True
        MsgBox "Die Personenliste ist voll (" & (LN - L0 + 1) & " Zeilen). Bitte zuerst einen Einsatz entfernen.", _
               vbExclamation, "Einsatz laden"
        Exit Sub
    End If
    ws.Unprotect KENNWORT
    ReDim ausgabe(1 To n, 1 To 3)
    For r = 1 To n
        ausgabe(r, 1) = platz: ausgabe(r, 2) = amt(r): ausgabe(r, 3) = nam(r)
    Next r
    ws.Range(ws.Cells(frei, 1), ws.Cells(frei + n - 1, 3)).Value = ausgabe
    ws.Range(ws.Cells(frei, 6), ws.Cells(frei + n - 1, 6)).ClearContents

    ' Einsatz-Platz fuellen
    bez = Trim$(ZellText(arr(7, 4)) & " " & ZellText(arr(8, 4)))
    If bez = "" Then bez = dname
    gefunden = ZeitraumFinden(arr, dVon, dBis)
    Application.ScreenUpdating = True
    If Not gefunden Then gefunden = ZeitraumAbfragen(dVon, dBis)

    r = SLOT0 + platz - 1
    ws.Cells(r, 2).Value = dname
    ws.Cells(r, 3).Value = bez
    If gefunden Then
        ws.Cells(r, 4).Value = dVon
        ws.Cells(r, 5).Value = dBis
    Else
        ws.Range(ws.Cells(r, 4), ws.Cells(r, 5)).ClearContents
    End If
    ws.Cells(r, 6).Value = "ja"
    ws.Rows(r).AutoFit                          ' Datei-/Einsatzname vollstaendig anzeigen
    Schuetzen ws

    Application.Calculate
    ws.Activate

    s = "Einsatz " & platz & " geladen: " & bez & vbCr & vbCr & _
        "Personen: " & n & vbCr & _
        "zugeordnet: " & ws.Cells(r, 8).Value & vbCr & _
        "nicht zugeordnet: " & ws.Cells(r, 9).Value & vbCr
    If gefunden Then
        s = s & "Zeitraum: " & Format(dVon, "dd.mm.yyyy") & " - " & Format(dBis, "dd.mm.yyyy")
    Else
        s = s & "Zeitraum fehlt - bitte in Zeile " & r & " (von / bis) eintragen."
    End If
    MsgBox s, vbInformation, "Einsatz laden"

    DoppelgliederungenAbfragen
    Exit Sub

Fehler:
    Application.DisplayAlerts = True
    Application.ScreenUpdating = True
    If Not wbQ Is Nothing Then
        On Error Resume Next
        wbQ.Close SaveChanges:=False
    End If
    MsgBox "Die Datei konnte nicht geladen werden:" & vbCr & Err.Description, vbExclamation, "Einsatz laden"
End Sub

' ---------------------------------------------------------------------
Public Sub EinsatzEntfernen()
    Dim ws As Worksheet
    Dim k As Long, r As Long, n As Long, liste As String, eingabe As String, platz As Long
    Dim daten As Variant, manuell As Variant, neuD() As Variant, neuM() As Variant
    Dim ent As Variant, neuE() As Variant

    Set ws = ThisWorkbook.Worksheets(BLATT)
    For k = 1 To SLOTS
        r = SLOT0 + k - 1
        If Trim$(CStr(ws.Cells(r, 2).Value)) <> "" Then
            liste = liste & "  " & k & ":  " & ws.Cells(r, 3).Value & "  (" & DatumText(ws.Cells(r, 4).Value) & _
                    " - " & DatumText(ws.Cells(r, 5).Value) & ")" & vbCr
        End If
    Next k
    If liste = "" Then
        MsgBox "Es ist kein Einsatz geladen.", vbInformation, "Einsatz entfernen"
        Exit Sub
    End If

    eingabe = InputBox("Geladene Einsaetze:" & vbCr & liste & vbCr & "Welchen Einsatz entfernen? Nummer eingeben:", _
                       "Einsatz entfernen")
    If Trim$(eingabe) = "" Then Exit Sub
    If Not IsNumeric(eingabe) Then MsgBox "Bitte eine Nummer eingeben.", vbExclamation: Exit Sub
    platz = CLng(eingabe)
    If platz < 1 Or platz > SLOTS Then MsgBox "Nummer 1 bis " & SLOTS & " eingeben.", vbExclamation: Exit Sub
    r = SLOT0 + platz - 1
    If Trim$(CStr(ws.Cells(r, 2).Value)) = "" Then MsgBox "Platz " & platz & " ist leer.", vbExclamation: Exit Sub
    If MsgBox("Einsatz " & platz & " (" & ws.Cells(r, 3).Value & ") wirklich entfernen?" & vbCr & _
              "Die E dieses Einsatzes verschwinden aus ""Staerke"".", vbYesNo + vbQuestion, "Einsatz entfernen") = vbNo Then Exit Sub

    Application.ScreenUpdating = False
    ws.Unprotect KENNWORT

    ' Personenliste ohne diesen Einsatz neu schreiben (Spalten A:C + manuelle Zuordnung F)
    daten = ws.Range(ws.Cells(L0, 1), ws.Cells(LN, 3)).Value
    manuell = ws.Range(ws.Cells(L0, 6), ws.Cells(LN, 6)).Value
    ReDim neuD(1 To LN - L0 + 1, 1 To 3): ReDim neuM(1 To LN - L0 + 1, 1 To 1)
    For k = 1 To LN - L0 + 1
        If Trim$(CStr(daten(k, 3))) <> "" Then
            If CStr(daten(k, 1)) <> CStr(platz) Then
                n = n + 1
                neuD(n, 1) = daten(k, 1): neuD(n, 2) = daten(k, 2): neuD(n, 3) = daten(k, 3)
                neuM(n, 1) = manuell(k, 1)
            End If
        End If
    Next k
    For k = n + 1 To LN - L0 + 1
        neuD(k, 1) = Empty: neuD(k, 2) = Empty: neuD(k, 3) = Empty: neuM(k, 1) = Empty
    Next k
    ws.Range(ws.Cells(L0, 1), ws.Cells(LN, 3)).Value = neuD
    ws.Range(ws.Cells(L0, 6), ws.Cells(LN, 6)).Value = neuM

    ' Entscheidungen, die auf diesen Einsatz zeigen, entfernen
    ent = ws.Range(ws.Cells(K0, 10), ws.Cells(KN, 11)).Value
    ReDim neuE(1 To KN - K0 + 1, 1 To 2)
    n = 0
    For k = 1 To KN - K0 + 1
        If Trim$(CStr(ent(k, 1))) <> "" And CStr(ent(k, 2)) <> CStr(platz) Then
            n = n + 1: neuE(n, 1) = ent(k, 1): neuE(n, 2) = ent(k, 2)
        End If
    Next k
    ws.Range(ws.Cells(K0, 10), ws.Cells(KN, 11)).ClearContents
    If n > 0 Then ws.Range(ws.Cells(K0, 10), ws.Cells(K0 + n - 1, 11)).Value = neuE

    ' Platz leeren
    ws.Range(ws.Cells(r, 2), ws.Cells(r, 6)).ClearContents
    ws.Rows(r).AutoFit
    Schuetzen ws

    Application.ScreenUpdating = True
    Application.Calculate
    MsgBox "Einsatz " & platz & " wurde entfernt.", vbInformation, "Einsatz entfernen"
    DoppelgliederungenAbfragen
End Sub

' ---------------------------------------------------------------------
Public Sub DoppelgliederungenEntscheiden()
    Application.Calculate
    If DoppelgliederungenAbfragen() = 0 Then
        MsgBox "Es gibt keine offenen Doppelgliederungen.", vbInformation, "Doppelgliederungen"
    End If
End Sub

' Fragt fuer jede offene Doppelgliederung, in welchem Einsatz der Mitarbeiter faehrt.
' Rueckgabe: Anzahl der gefundenen offenen Faelle.
Private Function DoppelgliederungenAbfragen() As Long
    Dim ws As Worksheet
    Dim r As Long, versuch As Long, anzahl As Long, platz As Long
    Dim ePlan As String, nachname As String, einsaetze As String, frage As String, eingabe As String
    Dim teile() As String, i As Long, erlaubt As Boolean

    Set ws = ThisWorkbook.Worksheets(BLATT)
    For r = K0 To KN
        If Trim$(CStr(ws.Cells(r, 2).Value)) = "" Then Exit For
        If Left$(CStr(ws.Cells(r, 8).Value), 5) = "OFFEN" Then
            anzahl = anzahl + 1
            ePlan = CStr(ws.Cells(r, 2).Value)
            nachname = CStr(ws.Cells(r, 3).Value)
            einsaetze = CStr(ws.Cells(r, 4).Value)
            teile = Split(einsaetze, ",")

            frage = "ACHTUNG - Doppelgliederung!" & vbCr & vbCr & _
                   nachname & " (" & ePlan & ") ist vom " & ws.Cells(r, 6).Value & ". bis " & ws.Cells(r, 7).Value & _
                   ". in mehreren Einsaetzen gegliedert:" & vbCr & vbCr
            For i = 0 To UBound(teile)
                platz = CLng(Trim$(teile(i)))
                frage = frage & "  Einsatz " & platz & ":  " & ws.Cells(SLOT0 + platz - 1, 3).Value & "  (" & _
                       DatumText(ws.Cells(SLOT0 + platz - 1, 4).Value) & " - " & _
                       DatumText(ws.Cells(SLOT0 + platz - 1, 5).Value) & ")" & vbCr
            Next i
            frage = frage & vbCr & "In welchem Einsatz soll er / sie fahren? Nummer eingeben" & vbCr & _
                   "(Abbrechen = spaeter entscheiden, bleibt als EE markiert):"

            For versuch = 1 To 3
                eingabe = InputBox(frage, "Doppelgliederung " & nachname, Trim$(teile(0)))
                If Trim$(eingabe) = "" Then Exit For
                erlaubt = False
                For i = 0 To UBound(teile)
                    If Trim$(teile(i)) = Trim$(eingabe) Then erlaubt = True
                Next i
                If erlaubt Then
                    EntscheidungSpeichern ws, ePlan, CLng(eingabe)
                    Application.Calculate
                    Exit For
                End If
                MsgBox "Bitte eine der angezeigten Einsatz-Nummern eingeben: " & einsaetze, vbExclamation
            Next versuch
        End If
    Next r
    DoppelgliederungenAbfragen = anzahl
End Function

Private Sub EntscheidungSpeichern(ByVal ws As Worksheet, ByVal ePlan As String, ByVal platz As Long)
    Dim r As Long, frei As Long
    For r = K0 To KN
        If StrComp(CStr(ws.Cells(r, 10).Value), ePlan, vbTextCompare) = 0 Then
            ws.Cells(r, 11).Value = platz
            Exit Sub
        End If
        If frei = 0 And Trim$(CStr(ws.Cells(r, 10).Value)) = "" Then frei = r
    Next r
    If frei = 0 Then
        MsgBox "Die Entscheidungstabelle ist voll. Bitte alte Eintraege in den Spalten J/K loeschen.", vbExclamation
        Exit Sub
    End If
    ws.Cells(frei, 10).Value = ePlan
    ws.Cells(frei, 11).Value = platz
End Sub

' ---------------------------------------------------------------------
Public Sub TabelleLeeren()
    If MsgBox("Grunddaten wirklich leeren?" & vbCr & _
              "(Geladene Einsaetze und Wochenend-Entscheidungen bleiben erhalten.)", _
              vbYesNo + vbQuestion, "Tabelle leeren") = vbNo Then Exit Sub
    With ThisWorkbook.Worksheets("Grunddaten")
        .Unprotect KENNWORT
        .Range("E2:AQ600").ClearContents
        .Range("E2:AQ600").NumberFormat = "General"
        Schuetzen ThisWorkbook.Worksheets("Grunddaten")
    End With
    ThisWorkbook.Worksheets("EinsatzDaten").Range("C3:D3").ClearContents
    MsgBox "Geleert. Jetzt die ePlan-Daten in die rote Zelle einfuegen" & vbCr & _
           "(Rechtsklick - Inhalte einfuegen - Text) und danach ""Grunddaten aufbereiten"" klicken.", _
           vbInformation, "Tabelle leeren"
End Sub

' ---------------------------------------------------------------------
' Verteilt den aus der ePlan-Druckansicht eingefuegten Text (eine Zeile je Zelle
' in Spalte E) auf die Spalten E:AQ - ersetzt "Daten - Text in Spalten".
' Personenzeilen werden geradegezogen: Amtsbez. | Initial | Name | Tage...
' (Zusaetze wie "m.Z." fallen weg, Namen mit Leerzeichen bleiben zusammen).
' Bereits aufgeteilte Zeilen werden ebenfalls geprueft und korrigiert.
Public Sub GrunddatenAufbereiten()
    Const Z0 As Long = 2, ZN As Long = 300, C0 As Long = 5, CN As Long = 43
    Dim ws As Worksheet, daten As Variant, aus() As Variant
    Dim zeilen() As Variant, t As Variant, werte As Variant
    Dim r As Long, i As Long, tage As Long, personen As Long, anz As Long
    Dim korrigiert As String, stand As String, korr As Boolean

    Set ws = ThisWorkbook.Worksheets("Grunddaten")
    If Application.WorksheetFunction.CountA(ws.Range(ws.Cells(ZN + 1, C0), ws.Cells(600, CN))) > 0 Then
        MsgBox "Die ePlan-Daten reichen ueber Zeile " & ZN & " hinaus." & vbCr & _
               "Alles ab Zeile " & ZN + 1 & " wird NICHT ausgewertet - bitte melden, die Tabelle muss erweitert werden.", _
               vbExclamation, "Grunddaten aufbereiten"
    End If
    daten = ws.Range(ws.Cells(Z0, C0), ws.Cells(ZN, CN)).Value
    ReDim zeilen(1 To ZN - Z0 + 1)
    For r = 1 To ZN - Z0 + 1
        zeilen(r) = ZeileTokens(daten, r)
    Next r

    ' Anzahl Tage aus der Zeile "1 2 3 ... 31"
    tage = 31
    For r = 1 To ZN - Z0 + 1
        If IstTageszeile(zeilen(r)) Then tage = UBound(zeilen(r)) + 1: Exit For
    Next r

    ReDim aus(1 To ZN - Z0 + 1, 1 To CN - C0 + 1)
    For r = 1 To ZN - Z0 + 1
        t = zeilen(r)
        If IsArray(t) Then
            werte = Personenzeile(t, tage, korr)
            If IsArray(werte) Then
                personen = personen + 1
                If korr Then korrigiert = korrigiert & "  - " & werte(2) & vbCr
            Else
                werte = t
                If stand = "" Then stand = EPlanStand(t)
            End If
            anz = UBound(werte) + 1
            If anz > CN - C0 + 1 Then anz = CN - C0 + 1
            For i = 0 To anz - 1
                aus(r, i + 1) = AlsWert(CStr(werte(i)))
            Next i
        End If
    Next r

    If personen = 0 Then
        MsgBox "Es wurden keine Personenzeilen erkannt." & vbCr & _
               "Bitte pruefen, ob die ePlan-Daten in die rote Zelle (E2) eingefuegt wurden.", vbExclamation, "Grunddaten aufbereiten"
        Exit Sub
    End If

    Application.ScreenUpdating = False
    ws.Unprotect KENNWORT
    ws.Range(ws.Cells(Z0, C0), ws.Cells(ZN, CN)).ClearContents
    ws.Range(ws.Cells(Z0, C0), ws.Cells(ZN, CN)).NumberFormat = "General"
    ws.Range(ws.Cells(Z0, C0), ws.Cells(ZN, CN)).Value = aus
    Schuetzen ws
    With ThisWorkbook.Worksheets("EinsatzDaten")
        .Range("C3").Value = "'" & stand
        .Range("D3").Value = "'" & Format(Now, "dd.mm.yyyy hh:nn")
    End With
    Application.ScreenUpdating = True
    Application.Calculate

    MsgBox "Grunddaten aufbereitet." & vbCr & vbCr & _
           "Personenzeilen: " & personen & vbCr & _
           "Tage im Monat: " & tage & vbCr & _
           "ePlan-Stand: " & IIf(stand = "", "nicht gefunden", stand) & _
           IIf(korrigiert = "", "", vbCr & vbCr & "Korrigierte Zeilen:" & vbCr & korrigiert), _
           vbInformation, "Grunddaten aufbereiten"
End Sub

' ---------------------------------------------------------------------
' Wochenend-Pruefaelle entscheiden: Zeile(n) in der Liste markieren, dann Button.
' Die Entscheidung wird fest an Name ePlan + Freitag gespeichert (nicht an die Zeile).
Public Sub EntscheidungJa()
    EntscheidungSetzen "ja"
End Sub

Public Sub EntscheidungNein()
    EntscheidungSetzen "nein"
End Sub

Public Sub EntscheidungLoeschen()
    EntscheidungSetzen ""
End Sub

Private Sub EntscheidungSetzen(ByVal wert As String)
    Dim ws As Worksheet, sp As Worksheet, bereich As Range, zelle As Range
    Dim fr As Variant, schluessel As String, r As Long, k As Long
    Dim anzahl As Long, namen As String

    Set ws = ThisWorkbook.Worksheets("Verf" & ChrW(252) & "gbarkeit Wochenende")
    Set sp = ThisWorkbook.Worksheets("EinsatzDaten")
    fr = ws.Range("AI6").Value2
    If VarType(fr) <> vbDouble Then
        MsgBox "Bitte zuerst oben einen Freitag auswaehlen.", vbExclamation, "Entscheidung"
        Exit Sub
    End If
    If TypeName(Selection) <> "Range" Then
        MsgBox "Bitte zuerst eine oder mehrere Zeilen in der Liste markieren.", vbExclamation, "Entscheidung"
        Exit Sub
    End If
    If Not Selection.Worksheet Is ws Then
        MsgBox "Bitte die Zeilen im Reiter ""Verfuegbarkeit Wochenende"" markieren.", vbExclamation, "Entscheidung"
        Exit Sub
    End If
    Set bereich = Intersect(Selection.EntireRow, ws.Range(ws.Cells(WE0, 1), ws.Cells(WEN, 1)))
    If bereich Is Nothing Then
        MsgBox "Bitte eine oder mehrere orange Zeilen (Prueffall) in der Liste markieren.", vbExclamation, "Entscheidung"
        Exit Sub
    End If

    Application.ScreenUpdating = False
    For Each zelle In bereich.Cells
        r = zelle.Row
        If CStr(ws.Cells(r, 11).Value) = "Pr" & ChrW(252) & "ffall" And Trim$(CStr(ws.Cells(r, 6).Value)) <> "" Then
            schluessel = Trim$(CStr(ws.Cells(r, 6).Value)) & "|" & CStr(CLng(fr))
            k = SpeicherZeile(sp, schluessel)
            If k = 0 And wert <> "" Then
                k = FreieSpeicherZeile(sp, CLng(fr))
                If k = 0 Then
                    Application.ScreenUpdating = True
                    MsgBox "Der Speicher fuer Wochenend-Entscheidungen ist voll.", vbExclamation, "Entscheidung"
                    Exit Sub
                End If
                sp.Cells(k, 46).Value = schluessel
            End If
            If k > 0 Then
                If wert = "" Then
                    sp.Range(sp.Cells(k, 46), sp.Cells(k, 47)).ClearContents
                Else
                    sp.Cells(k, 47).Value = wert
                End If
            End If
            anzahl = anzahl + 1
            namen = namen & "  - " & ws.Cells(r, 5).Value & vbCr
        End If
    Next zelle
    Application.ScreenUpdating = True
    Application.Calculate

    If anzahl = 0 Then
        MsgBox "In der Markierung ist kein Prueffall." & vbCr & _
               "Bitte eine oder mehrere orange Zeilen markieren und dann klicken.", vbExclamation, "Entscheidung"
    ElseIf anzahl > 1 Then
        If wert = "" Then
            MsgBox "Entscheidung geloescht fuer:" & vbCr & namen, vbInformation, "Entscheidung"
        Else
            MsgBox "Entscheidung """ & wert & """ gespeichert fuer:" & vbCr & namen, vbInformation, "Entscheidung"
        End If
    End If
End Sub

Private Function SpeicherZeile(ByVal sp As Worksheet, ByVal schluessel As String) As Long
    Dim r As Long
    For r = S0 To SN
        If StrComp(CStr(sp.Cells(r, 46).Value), schluessel, vbTextCompare) = 0 Then SpeicherZeile = r: Exit Function
    Next r
End Function

' Erste freie Speicherzeile; ist keine frei, werden Entscheidungen aelter als 60 Tage entfernt.
Private Function FreieSpeicherZeile(ByVal sp As Worksheet, ByVal freitag As Long) As Long
    Dim r As Long, t As String, p As Long
    For r = S0 To SN
        If Trim$(CStr(sp.Cells(r, 46).Value)) = "" Then FreieSpeicherZeile = r: Exit Function
    Next r
    For r = S0 To SN
        t = CStr(sp.Cells(r, 46).Value)
        p = InStrRev(t, "|")
        If p > 0 Then
            If Val(Mid$(t, p + 1)) < freitag - 60 Then sp.Range(sp.Cells(r, 46), sp.Cells(r, 47)).ClearContents
        End If
    Next r
    For r = S0 To SN
        If Trim$(CStr(sp.Cells(r, 46).Value)) = "" Then FreieSpeicherZeile = r: Exit Function
    Next r
End Function

' Zerlegt eine Zeile in Einzelwerte - egal ob noch als ein Text in Spalte E
' oder schon auf mehrere Spalten verteilt.
Private Function ZeileTokens(ByVal daten As Variant, ByVal r As Long) As Variant
    Dim c As Long, v As Variant, s As String, anzahl As Long, einzeln As String
    For c = 1 To UBound(daten, 2)
        v = daten(r, c)
        If Not IsError(v) And Not IsEmpty(v) Then
            If Trim$(CStr(v)) <> "" Then
                anzahl = anzahl + 1
                If VarType(v) = vbDate Then
                    If Year(v) = 1900 Then
                        einzeln = CStr(Day(v))
                    ElseIf Int(CDbl(v)) = 0 Then
                        einzeln = Format(v, "hh:nn:ss")
                    Else
                        einzeln = Format(v, "dd.mm.yyyy")
                    End If
                ElseIf VarType(v) = vbDouble Then
                    If v = Int(v) Then einzeln = CStr(CLng(v)) Else einzeln = CStr(v)
                Else
                    einzeln = CStr(v)
                End If
                s = s & " " & einzeln
            End If
        End If
    Next c
    s = Replace(Replace(s, Chr(160), " "), vbTab, " ")
    s = Application.WorksheetFunction.Trim(s)
    If s = "" Then ZeileTokens = Empty Else ZeileTokens = Split(s, " ")
End Function

Private Function IstTageszeile(ByVal t As Variant) As Boolean
    Dim i As Long
    If Not IsArray(t) Then Exit Function
    If UBound(t) < 27 Then Exit Function
    For i = 0 To UBound(t)
        If Not NurZiffern(CStr(t(i))) Then Exit Function
        If CLng(t(i)) <> i + 1 Then Exit Function
    Next i
    IstTageszeile = True
End Function

' Personenzeile: Amtsbez. ... Initial Name ... + genau "tage" Tageskuerzel am Ende
Private Function Personenzeile(ByVal t As Variant, ByVal tage As Long, ByRef korr As Boolean) As Variant
    Dim n As Long, i As Long, k As Long, pos As Long, nm As String, erg() As String
    korr = False
    n = UBound(t) + 1
    If n < tage + 3 Then Exit Function
    For i = n - tage To n - 1
        If CStr(t(i)) Like "*#*" Then Exit Function
    Next i
    For i = 1 To n - tage - 2
        If IstInitial(CStr(t(i))) Then pos = i: Exit For
    Next i
    If pos = 0 Then Exit Function
    For i = pos + 1 To n - tage - 1
        nm = nm & IIf(nm = "", "", " ") & t(i)
    Next i
    ReDim erg(0 To tage + 2)
    erg(0) = t(0): erg(1) = t(pos): erg(2) = nm
    k = 3
    For i = n - tage To n - 1
        erg(k) = t(i): k = k + 1
    Next i
    korr = (pos <> 1) Or (n - tage - 1 - pos <> 1)
    Personenzeile = erg
End Function

Private Function IstInitial(ByVal s As String) As Boolean
    IstInitial = (s Like "[A-Za-z]." Or s Like "[A-Za-z][A-Za-z]." Or _
                  (Len(s) = 2 And Right$(s, 1) = "." And Not Left$(s, 1) Like "[0-9.]"))
End Function

Private Function NurZiffern(ByVal s As String) As Boolean
    NurZiffern = (Len(s) > 0 And Len(s) <= 9 And Not s Like "*[!0-9]*")
End Function

' Zahlen als Zahl, alles andere als Text (z. B. "3." nicht in 3 umwandeln lassen)
Private Function AlsWert(ByVal s As String) As Variant
    If NurZiffern(s) Then
        AlsWert = CLng(s)
    ElseIf s Like "*#*" Then
        AlsWert = "'" & s
    Else
        AlsWert = s
    End If
End Function

' "Stand: 30. September 2026 06:59:06 MESZ" -> "30.09.2026 06:59"
Private Function EPlanStand(ByVal t As Variant) As String
    Dim monate As Variant, m As Long, i As Long
    If UBound(t) < 4 Then Exit Function
    If CStr(t(0)) <> "Stand:" Then Exit Function
    monate = Array("Januar", "Februar", "M" & ChrW(228) & "rz", "April", "Mai", "Juni", "Juli", _
                   "August", "September", "Oktober", "November", "Dezember")
    For i = 0 To 11
        If StrComp(CStr(t(2)), monate(i), vbTextCompare) = 0 Then m = i + 1
    Next i
    If m = 0 Or Not NurZiffern(Replace(CStr(t(1)), ".", "")) Then Exit Function
    EPlanStand = Format(Val(t(1)), "00") & "." & Format(m, "00") & "." & t(3) & " " & Left$(CStr(t(4)), 5)
End Function

' =====================================================================
'  Hilfsfunktionen
' =====================================================================
Private Sub Schuetzen(ByVal ws As Worksheet)
    ws.Protect Password:=KENNWORT, DrawingObjects:=True, Contents:=True, Scenarios:=True, _
               AllowFormattingColumns:=True, AllowFormattingRows:=True, AllowFiltering:=True
End Sub

Private Function ErsteFreieZeile(ByVal ws As Worksheet) As Long
    Dim r As Long
    For r = LN To L0 Step -1
        If Trim$(CStr(ws.Cells(r, 3).Value)) <> "" Then ErsteFreieZeile = r + 1: Exit Function
    Next r
    ErsteFreieZeile = L0
End Function

Private Function ZellText(ByVal v As Variant) As String
    If IsError(v) Then Exit Function
    If IsEmpty(v) Then Exit Function
    ZellText = Trim$(CStr(v))
End Function

Private Function DatumText(ByVal v As Variant) As String
    If IsDate(v) Then DatumText = Format(v, "dd.mm.yyyy") Else DatumText = "?"
End Function

Private Function ZeitraumAbfragen(ByRef dVon As Date, ByRef dBis As Date) As Boolean
    Dim a As String, b As String
    a = InputBox("Der Einsatzzeitraum wurde in der Datei nicht erkannt." & vbCr & "Beginn (TT.MM.JJJJ):", "Einsatzzeitraum")
    If Not IsDate(a) Then Exit Function
    b = InputBox("Ende (TT.MM.JJJJ):", "Einsatzzeitraum", a)
    If Not IsDate(b) Then Exit Function
    dVon = CDate(a): dBis = CDate(b)
    ZeitraumAbfragen = (dBis >= dVon)
End Function

' Sucht in Zeile 1-20 den Text mit dem Einsatzzeitraum, z.B. "anl. Migration vom 22.09.-30.09.2026"
Private Function ZeitraumFinden(ByVal arr As Variant, ByRef dVon As Date, ByRef dBis As Date) As Boolean
    Dim r As Long, c As Long, n As Long, punkte As Long, bestPunkte As Long
    Dim txt As String, best As String
    Dim t() As Long, m() As Long, y() As Long
    Dim yVon As Long, yBis As Long

    For r = 1 To 20
        For c = 1 To 40
            If VarType(arr(r, c)) = vbString Then
                txt = arr(r, c)
                n = DatumsTeile(txt, t, m, y)
                punkte = 0
                If n >= 2 Then
                    punkte = 2
                    If InStr(1, txt, "vom", vbTextCompare) > 0 Then punkte = 3
                ElseIf n = 1 Then
                    punkte = 1
                End If
                If punkte > bestPunkte Then bestPunkte = punkte: best = txt
            End If
        Next c
    Next r
    If bestPunkte = 0 Then Exit Function

    n = DatumsTeile(best, t, m, y)
    yVon = y(1): yBis = y(n)
    If yBis = 0 Then yBis = yVon
    If yBis = 0 Then
        If IsNumeric(ThisWorkbook.Worksheets("Grunddaten").Range("G2").Value) Then
            yBis = CLng(ThisWorkbook.Worksheets("Grunddaten").Range("G2").Value)
        Else
            yBis = Year(Date)
        End If
    End If
    If yVon = 0 Then yVon = yBis
    If m(1) = 0 Then m(1) = m(n)                 ' z.B. "vom 01.-03.10.2026"
    dVon = DateSerial(yVon, m(1), t(1))
    dBis = DateSerial(yBis, m(n), t(n))
    If dBis < dVon And y(1) = 0 Then dVon = DateSerial(yVon - 1, m(1), t(1))
    ZeitraumFinden = True
End Function

' Zerlegt einen Text in Datumsangaben TT.MM. bzw. TT.MM.JJ(JJ)
Private Function DatumsTeile(ByVal s As String, ByRef t() As Long, ByRef m() As Long, ByRef y() As Long) As Long
    Dim n As Long, p As Long, q As Long, r As Long
    Dim a As String, b As String, c As String
    Dim treffer As Boolean
    ReDim t(1 To 10): ReDim m(1 To 10): ReDim y(1 To 10)
    p = 1
    Do While p <= Len(s) And n < 10
        If Mid$(s, p, 1) Like "#" Then
            treffer = False
            q = p
            a = Ziffern(s, q)
            If Len(a) <= 2 And Mid$(s, q, 1) = "." Then
                q = q + 1
                b = Ziffern(s, q)
                If Len(b) >= 1 And Len(b) <= 2 And Mid$(s, q, 1) = "." Then
                    q = q + 1
                    c = Ziffern(s, q)
                    If CLng(a) >= 1 And CLng(a) <= 31 And CLng(b) >= 1 And CLng(b) <= 12 _
                       And (Len(c) = 0 Or Len(c) = 2 Or Len(c) = 4) Then
                        n = n + 1
                        t(n) = CLng(a): m(n) = CLng(b)
                        If Len(c) = 0 Then
                            y(n) = 0
                        Else
                            y(n) = CLng(c)
                            If y(n) < 100 Then y(n) = y(n) + 2000
                        End If
                        treffer = True
                        p = q
                    End If
                ElseIf Len(b) = 0 Then
                    ' nur Tag, Monat folgt spaeter: "01.-03.10.2026" / "01. bis 03.10.2026"
                    r = q
                    Do While Mid$(s, r, 1) = " "
                        r = r + 1
                    Loop
                    If (Mid$(s, r, 1) = "-" Or LCase$(Mid$(s, r, 3)) = "bis") And CLng(a) >= 1 And CLng(a) <= 31 Then
                        n = n + 1
                        t(n) = CLng(a): m(n) = 0: y(n) = 0
                        treffer = True
                        p = r
                    End If
                End If
            End If
            If Not treffer Then p = p + Len(a)
        Else
            p = p + 1
        End If
    Loop
    DatumsTeile = n
End Function

Private Function Ziffern(ByVal s As String, ByRef pos As Long) As String
    Dim r As String
    Do While pos <= Len(s)
        If Mid$(s, pos, 1) Like "#" Then
            r = r & Mid$(s, pos, 1): pos = pos + 1
        Else
            Exit Do
        End If
    Loop
    Ziffern = r
End Function

Private Function DateiName(ByVal pfad As String) As String
    Dim i As Long
    i = InStrRev(pfad, "\")
    If InStrRev(pfad, "/") > i Then i = InStrRev(pfad, "/")
    DateiName = Mid$(pfad, i + 1)
End Function

' =====================================================================
'  Einsatzgliederung aus dem Reiter "Verfuegbarkeit"
'  - GliederungEingabe / GliederungAnzeigen : werden vom Blatt-Code in
'    "Verfuegbarkeit" aufgerufen (Auswahl bleibt an der Person haengen)
'  - GliederungErstellen      : Kopie der Gliederungsvorlage fuellen
'  - GliederungAlsEinsatz     : zugewiesene Mitarbeiter als E eintragen
'  - NeuePlanung              : alle Zuweisungen loeschen
'  Speicher: EinsatzDaten AW:AZ (Name ePlan | Funktion | Zug | Trupp),
'  Zeilen 10..509; BB25:BB161 = zuletzt angezeigte Namen.
' =====================================================================
Private Function BlattV() As Worksheet
    Set BlattV = ThisWorkbook.Worksheets("Verf" & ChrW(252) & "gbarkeit")
End Function

' Auswahl in G:I der Liste wurde geaendert -> an die Person speichern
Public Sub GliederungEingabe(ByVal Target As Range)
    Dim ws As Worksheet, sp As Worksheet, bereich As Range, z As Range
    Dim r As Long, k As Long, nm As String
    Set ws = Target.Worksheet
    Set bereich = Intersect(Target, ws.Range(ws.Cells(V0, 7), ws.Cells(VN, 9)))
    If bereich Is Nothing Then Exit Sub
    Set sp = ThisWorkbook.Worksheets("EinsatzDaten")
    Application.EnableEvents = False
    On Error GoTo Ende
    For Each z In bereich.Cells
        r = z.Row
        nm = Trim$(CStr(ws.Cells(r, 6).Value))
        If nm = "" Then
            z.ClearContents
        Else
            k = GliedZeile(sp, nm, Trim$(CStr(z.Value)) <> "")
            If k > 0 Then
                sp.Cells(k, 50 + z.Column - 7).Value = z.Value
                If Trim$(CStr(sp.Cells(k, 50).Value) & CStr(sp.Cells(k, 51).Value) & CStr(sp.Cells(k, 52).Value)) = "" Then
                    sp.Range(sp.Cells(k, 49), sp.Cells(k, 52)).ClearContents
                End If
            End If
            sp.Cells(r, 54).Value = nm
        End If
    Next z
Ende:
    Application.EnableEvents = True
End Sub

' Liste hat sich umsortiert -> Auswahl wieder an die richtigen Personen schreiben
Public Sub GliederungAnzeigen()
    Dim ws As Worksheet, sp As Worksheet, namen As Variant, snap As Variant
    Dim i As Long, n As Long, geaendert As Boolean, d As Object, nm As String, aus() As Variant, w As Variant
    If mAnzeige Then Exit Sub
    Set ws = BlattV()
    Set sp = ThisWorkbook.Worksheets("EinsatzDaten")
    n = VN - V0 + 1
    namen = ws.Range(ws.Cells(V0, 6), ws.Cells(VN, 6)).Value
    snap = sp.Range(sp.Cells(V0, 54), sp.Cells(VN, 54)).Value
    For i = 1 To n
        If CStr(namen(i, 1)) <> CStr(snap(i, 1)) Then geaendert = True: Exit For
    Next i
    If Not geaendert Then Exit Sub
    mAnzeige = True
    Application.EnableEvents = False
    On Error GoTo Ende
    Set d = GliedDaten(sp)
    ReDim aus(1 To n, 1 To 3)
    For i = 1 To n
        nm = Trim$(CStr(namen(i, 1)))
        If nm <> "" Then
            If d.Exists(nm) Then
                w = d(nm)
                aus(i, 1) = w(1): aus(i, 2) = w(2): aus(i, 3) = w(3)
            End If
        End If
    Next i
    ws.Range(ws.Cells(V0, 7), ws.Cells(VN, 9)).Value = aus
    sp.Range(sp.Cells(V0, 54), sp.Cells(VN, 54)).Value = namen
Ende:
    Application.EnableEvents = True
    mAnzeige = False
End Sub

Public Sub NeuePlanung()
    Dim ws As Worksheet, sp As Worksheet
    If MsgBox("Alle Zuweisungen (Funktion / Zug / Trupp) und die Kopfangaben der Gliederung loeschen?", _
              vbYesNo + vbQuestion, "Neue Planung") = vbNo Then Exit Sub
    Set ws = BlattV()
    Set sp = ThisWorkbook.Worksheets("EinsatzDaten")
    Application.EnableEvents = False
    sp.Range(sp.Cells(GS0, 49), sp.Cells(GSN, 52)).ClearContents
    ws.Range(ws.Cells(V0, 7), ws.Cells(VN, 9)).ClearContents
    ws.Range("H6:K9").ClearContents
    sp.Range(sp.Cells(V0, 54), sp.Cells(VN, 54)).Value = ws.Range(ws.Cells(V0, 6), ws.Cells(VN, 6)).Value
    sp.Range("BD4").ClearContents
    Application.EnableEvents = True
    MsgBox "Neue Planung begonnen - alle Zuweisungen sind geloescht.", vbInformation, "Neue Planung"
End Sub

' ---------------------------------------------------------------------
Public Sub GliederungErstellen()
    Dim ws As Worksheet, sp As Worksheet, wbG As Workbook, wv As Worksheet
    Dim einheit As String, hu As Long, zuege As Long, verst As String, anl As String, verf As String, enr As String
    Dim slots As Object, belegt As Object, pers As Collection, p As Variant
    Dim fehler As String, hinweis As String, key As String, zeilen As Variant, i As Long, r As Long
    Dim datei As Variant, ordner As String, neu As String, makro As String, vName As String
    Dim vdat As Variant, schutz As Boolean, anzahl As Long, mFehler As String, unbekannt As String

    Set ws = BlattV()
    Set sp = ThisWorkbook.Worksheets("EinsatzDaten")
    einheit = Trim$(CStr(ws.Range("H4").Value))
    hu = Val(einheit)
    zuege = KraefteZuege(CStr(ws.Range("H5").Value))
    verst = Trim$(CStr(ws.Range("H6").Value)): anl = Trim$(CStr(ws.Range("H7").Value))
    verf = Trim$(CStr(ws.Range("H8").Value)): enr = Trim$(CStr(ws.Range("H9").Value))
    If hu < 1 Or hu > 4 Then MsgBox "Bitte oben die Einheit (Hundertschaft) waehlen.", vbExclamation, "Gliederung": Exit Sub
    If zuege = 0 Then MsgBox "Bitte oben die Kraefteanforderung waehlen.", vbExclamation, "Gliederung": Exit Sub

    ' --- Zuweisungen einsammeln und pruefen
    Set pers = GliedPersonen(sp, ws, zuege, fehler, hinweis)
    If pers.Count = 0 And fehler = "" Then
        MsgBox "Es ist noch niemand einer Funktion zugewiesen.", vbExclamation, "Gliederung"
        Exit Sub
    End If
    Set slots = GliedSlots(zuege)
    Set belegt = CreateObject("Scripting.Dictionary")
    For Each p In pers
        key = p(5)
        If key = "" Then
            ' Fehler wurde bereits gemeldet
        ElseIf Not slots.Exists(key) Then
            fehler = fehler & "  - " & p(1) & ": " & p(6) & " gibt es bei dieser Kraefteanforderung nicht" & vbCr
        Else
            zeilen = slots(key)
            If Not belegt.Exists(key) Then belegt.Add key, 0
            If belegt(key) > UBound(zeilen) Then
                fehler = fehler & "  - " & p(1) & ": kein Platz mehr bei " & p(6) & " (" & UBound(zeilen) + 1 & " Plaetze)" & vbCr
            Else
                belegt(key) = belegt(key) + 1
            End If
        End If
    Next p
    If fehler <> "" Then
        MsgBox "Die Gliederung kann so nicht erstellt werden:" & vbCr & vbCr & fehler, vbExclamation, "Gliederung"
        Exit Sub
    End If
    If hinweis <> "" Then
        If MsgBox("Bitte pruefen:" & vbCr & vbCr & hinweis & vbCr & "Trotzdem weiter?", vbYesNo + vbQuestion, "Gliederung") = vbNo Then Exit Sub
    End If

    ' --- Vorlage waehlen und als Kopie speichern
    On Error Resume Next
    ordner = CStr(sp.Range("BD3").Value)
    If ordner <> "" Then ChDrive Left$(ordner, 1): ChDir ordner
    On Error GoTo 0
    datei = Application.GetOpenFilename("Excel-Dateien (*.xls*),*.xls*", , "Leere Gliederungsvorlage auswaehlen")
    If VarType(datei) = vbBoolean Then Exit Sub
    ordner = Left$(CStr(datei), InStrRev(CStr(datei), "\") - 1)
    sp.Range("BD3").Value = ordner
    neu = ordner & "\" & Format(Date, "yyyymmdd") & "_Gliederung_" & DateiTeil(IIf(verst <> "", verst, "Einsatz"))
    If Dir(neu & ".xlsm") <> "" Then
        i = 2
        Do While Dir(neu & "_" & i & ".xlsm") <> ""
            i = i + 1
        Loop
        neu = neu & "_" & i
    End If
    neu = neu & ".xlsm"

    Application.DisplayAlerts = False
    Set wbG = Workbooks.Open(Filename:=CStr(datei), UpdateLinks:=0)
    wbG.SaveAs Filename:=neu, FileFormat:=52
    Application.DisplayAlerts = True
    wbG.Activate

    ' --- Einheit und Kraefteanforderung mit den Makros der Vorlage
    On Error Resume Next
    Application.Run "'" & wbG.Name & "'!Planung" & hu & "Hu"
    If Err.Number <> 0 Then mFehler = mFehler & "  - Einheit (Planung" & hu & "Hu)" & vbCr
    Err.Clear
    Select Case zuege
        Case 1: makro = "EZug1"
        Case 2: makro = "HU2Z" & ChrW(252) & "ge"
        Case 3: makro = "Ehu3Z" & ChrW(252) & "ge"
        Case 4: makro = "Ehu4Z" & ChrW(220) & "GENEU"
    End Select
    wbG.Activate
    Application.Run "'" & wbG.Name & "'!" & makro
    If Err.Number <> 0 Then mFehler = mFehler & "  - Kraefteanforderung (" & makro & ")" & vbCr
    Err.Clear
    Set wv = wbG.Worksheets("Vorlage")
    If Err.Number <> 0 Then
        On Error GoTo 0
        MsgBox "In der gewaehlten Datei gibt es keinen Reiter ""Vorlage"". Ist es die richtige Gliederungsvorlage?", vbExclamation, "Gliederung"
        Exit Sub
    End If
    schutz = wv.ProtectContents
    wv.Unprotect
    On Error GoTo 0

    ' --- Kopf und Namen eintragen
    If verst <> "" Then wv.Range("D7").Value = "Einsatz zur Verst" & ChrW(228) & "rkung der " & verst
    If anl <> "" Then wv.Range("D9").Value = "anl. " & anl
    If verf <> "" Then wv.Range("D11").Value = "'" & verf
    If enr <> "" Then wv.Range("AA11").Value = "'" & enr
    For Each p In slots.Items
        For i = 0 To UBound(p)
            wv.Cells(p(i), 5).ClearContents
        Next i
    Next p
    vdat = wv.Range("AX389:BD1503").Value
    Set belegt = CreateObject("Scripting.Dictionary")
    For Each p In pers
        key = p(5)
        zeilen = slots(key)
        If Not belegt.Exists(key) Then belegt.Add key, 0
        r = zeilen(belegt(key))
        belegt(key) = belegt(key) + 1
        vName = VorlageName(vdat, CStr(p(2)), CStr(p(3)), hu)
        If vName = "" Then
            vName = CStr(p(2))
            unbekannt = unbekannt & "  - " & p(1) & ": " & p(6) & vbCr
        End If
        wv.Cells(r, 5).Value = vName
        anzahl = anzahl + 1
    Next p
    If schutz Then wv.Protect AllowFormattingCells:=True, AllowInsertingColumns:=True, AllowInsertingRows:=True, _
                              AllowDeletingColumns:=True, AllowSorting:=True, AllowFiltering:=True
    wv.Activate
    wbG.Save
    sp.Range("BD4").Value = Mid$(neu, InStrRev(neu, "\") + 1)

    MsgBox "Gliederung erstellt und gespeichert:" & vbCr & neu & vbCr & vbCr & _
           "Eingetragen: " & anzahl & " Mitarbeiter" & _
           IIf(mFehler = "", "", vbCr & vbCr & "Folgende Makros der Vorlage fehlen - bitte auf der Startflaeche selbst waehlen:" & vbCr & mFehler) & _
           IIf(unbekannt = "", "", vbCr & vbCr & "Nicht in der Personalliste der Vorlage gefunden (Name bitte in Spalte E pruefen):" & vbCr & unbekannt), _
           vbInformation, "Gliederung"

    If MsgBox("Bist du mit dieser Gliederung fertig?" & vbCr & vbCr & _
              "Sollen die Mitarbeiter jetzt als E (Einsatz) in ""Staerke"" eingetragen werden?" & vbCr & _
              "(Nein = spaeter ueber ""Gliederung als E eintragen"")", vbYesNo + vbQuestion, "Gliederung") = vbYes Then
        ThisWorkbook.Activate
        GliederungAlsEinsatz
    End If
End Sub

' Traegt alle zugewiesenen Mitarbeiter als neuen Einsatz in "Einsatz importieren" ein.
Public Sub GliederungAlsEinsatz()
    Dim ws As Worksheet, wsE As Worksheet, sp As Worksheet, pers As Collection, p As Variant
    Dim fehler As String, hinweis As String, platz As Long, k As Long, r As Long, frei As Long, n As Long
    Dim dVon As Date, dBis As Date, ok As Boolean, bez As String, dname As String, m As Long, y As Long
    Dim ausgabe() As Variant, manuell() As Variant

    Set ws = BlattV()
    Set sp = ThisWorkbook.Worksheets("EinsatzDaten")
    Set wsE = ThisWorkbook.Worksheets(BLATT)
    Set pers = GliedPersonen(sp, ws, 4, fehler, hinweis)
    If pers.Count = 0 Then MsgBox "Es ist niemand einer Funktion zugewiesen.", vbExclamation, "Gliederung als E": Exit Sub

    dname = CStr(sp.Range("BD4").Value)
    If dname = "" Then dname = "Gliederung aus Verfuegbarkeit"
    bez = Trim$(CStr(ws.Range("H6").Value))
    If bez <> "" Then bez = "Einsatz zur Verst" & ChrW(228) & "rkung der " & bez Else bez = "Einsatz"
    If Trim$(CStr(ws.Range("H7").Value)) <> "" Then bez = bez & " anl. " & Trim$(CStr(ws.Range("H7").Value))

    For k = 1 To SLOTS
        If StrComp(CStr(wsE.Cells(SLOT0 + k - 1, 2).Value), dname, vbTextCompare) = 0 Then
            If MsgBox("""" & dname & """ ist bereits als Einsatz " & k & " eingetragen." & vbCr & _
                      "Trotzdem noch einmal eintragen?", vbYesNo + vbQuestion, "Gliederung als E") = vbNo Then Exit Sub
            Exit For
        End If
    Next k
    For k = 1 To SLOTS
        If Trim$(CStr(wsE.Cells(SLOT0 + k - 1, 2).Value)) = "" Then platz = k: Exit For
    Next k
    If platz = 0 Then MsgBox "Alle " & SLOTS & " Einsatz-Plaetze sind belegt.", vbExclamation, "Gliederung als E": Exit Sub

    ' Zeitraum = Zeitraum im Reiter "Verfuegbarkeit"
    On Error Resume Next
    m = CLng(ThisWorkbook.Worksheets("Verf" & ChrW(252) & "gbarkeit Wochenende").Range("AI2").Value)
    y = CLng(ThisWorkbook.Worksheets("Verf" & ChrW(252) & "gbarkeit Wochenende").Range("AI3").Value)
    dVon = DateSerial(y, m, CLng(ws.Range("C4").Value))
    dBis = DateSerial(y, m, CLng(ws.Range("C5").Value))
    ok = (Err.Number = 0 And m > 0 And y > 2000 And dBis >= dVon)
    On Error GoTo 0
    If ok Then
        If MsgBox("Einsatz eintragen fuer " & pers.Count & " Mitarbeiter" & vbCr & _
                  "Zeitraum: " & Format(dVon, "dd.mm.yyyy") & " - " & Format(dBis, "dd.mm.yyyy") & vbCr & vbCr & _
                  "Stimmt der Zeitraum? (Nein = anderen Zeitraum eingeben)", vbYesNo + vbQuestion, "Gliederung als E") = vbNo Then ok = False
    End If
    If Not ok Then
        If Not ZeitraumAbfragen(dVon, dBis) Then MsgBox "Kein gueltiger Zeitraum - abgebrochen.", vbExclamation: Exit Sub
    End If

    frei = ErsteFreieZeile(wsE)
    n = pers.Count
    If frei + n - 1 > LN Then MsgBox "Die Personenliste in ""Einsatz importieren"" ist voll.", vbExclamation: Exit Sub
    ReDim ausgabe(1 To n, 1 To 3): ReDim manuell(1 To n, 1 To 1)
    r = 0
    For Each p In pers
        r = r + 1
        ausgabe(r, 1) = platz: ausgabe(r, 2) = p(4): ausgabe(r, 3) = p(2)
        manuell(r, 1) = p(0)
    Next p
    Application.ScreenUpdating = False
    wsE.Unprotect KENNWORT
    wsE.Range(wsE.Cells(frei, 1), wsE.Cells(frei + n - 1, 3)).Value = ausgabe
    wsE.Range(wsE.Cells(frei, 6), wsE.Cells(frei + n - 1, 6)).Value = manuell
    r = SLOT0 + platz - 1
    wsE.Cells(r, 2).Value = dname
    wsE.Cells(r, 3).Value = bez
    wsE.Cells(r, 4).Value = dVon
    wsE.Cells(r, 5).Value = dBis
    wsE.Cells(r, 6).Value = "ja"
    wsE.Rows(r).AutoFit
    Schuetzen wsE
    Application.ScreenUpdating = True
    Application.Calculate
    MsgBox "Als Einsatz " & platz & " eingetragen: " & n & " Mitarbeiter, " & _
           Format(dVon, "dd.mm.yyyy") & " - " & Format(dBis, "dd.mm.yyyy") & ".", vbInformation, "Gliederung als E"
    DoppelgliederungenAbfragen
End Sub

' --- Hilfsfunktionen Gliederung -------------------------------------
Private Function KraefteZuege(ByVal s As String) As Long
    If InStr(1, s, "1 E-Zug", vbTextCompare) > 0 Then KraefteZuege = 1: Exit Function
    If InStr(s, "2") > 0 Then KraefteZuege = 2
    If InStr(s, "3") > 0 Then KraefteZuege = 3
    If InStr(s, "4") > 0 Then KraefteZuege = 4
End Function

Private Function GliedZeile(ByVal sp As Worksheet, ByVal nm As String, ByVal anlegen As Boolean) As Long
    Dim r As Long, frei As Long
    For r = GS0 To GSN
        If StrComp(CStr(sp.Cells(r, 49).Value), nm, vbTextCompare) = 0 Then GliedZeile = r: Exit Function
        If frei = 0 And Trim$(CStr(sp.Cells(r, 49).Value)) = "" Then frei = r
    Next r
    If Not anlegen Then Exit Function
    If frei = 0 Then MsgBox "Der Speicher fuer Zuweisungen ist voll.", vbExclamation: Exit Function
    sp.Cells(frei, 49).Value = nm
    GliedZeile = frei
End Function

' Name ePlan -> Array(Name, Funktion, Zug, Trupp)
Private Function GliedDaten(ByVal sp As Worksheet) As Object
    Dim d As Object, v As Variant, i As Long, nm As String
    Set d = CreateObject("Scripting.Dictionary")
    d.CompareMode = vbTextCompare
    v = sp.Range(sp.Cells(GS0, 49), sp.Cells(GSN, 52)).Value
    For i = 1 To UBound(v)
        nm = Trim$(CStr(v(i, 1)))
        If nm <> "" And Not d.Exists(nm) Then d.Add nm, Array(nm, v(i, 2), v(i, 3), v(i, 4))
    Next i
    Set GliedDaten = d
End Function

' Sammelt alle Zuweisungen: Array(ePlan, Anzeigename, Nachname, Initial, Amtsbez., Platz-Schluessel, Beschreibung)
Private Function GliedPersonen(ByVal sp As Worksheet, ByVal ws As Worksheet, ByVal zuege As Long, _
                               ByRef fehler As String, ByRef hinweis As String) As Collection
    Dim c As New Collection, v As Variant, i As Long, st As Worksheet, rr As Variant
    Dim ep As String, f As String, zg As String, tr As String, nach As String, ini As String, amt As String
    Dim key As String, besch As String, zn As Long, gelistet As Variant
    Set st = ThisWorkbook.Worksheets("St" & ChrW(228) & "rke")
    v = sp.Range(sp.Cells(GS0, 49), sp.Cells(GSN, 52)).Value
    gelistet = ws.Range(ws.Cells(V0, 6), ws.Cells(VN, 6)).Value
    For i = 1 To UBound(v)
        ep = Trim$(CStr(v(i, 1)))
        f = Trim$(CStr(v(i, 2))): zg = Trim$(CStr(v(i, 3))): tr = Trim$(CStr(v(i, 4)))
        If ep <> "" And f <> "" Then
            rr = Application.Match(ep, st.Range("B10:B146"), 0)
            If IsError(rr) Then
                nach = ep: amt = ""
                If InStr(ep, ".") > 0 Then nach = Mid$(ep, InStr(ep, ".") + 1)
            Else
                nach = Trim$(CStr(st.Cells(rr + 9, 6).Value))
                amt = Trim$(CStr(st.Cells(rr + 9, 5).Value))
                If nach = "" Then nach = Mid$(ep, InStr(ep, ".") + 1)
            End If
            ini = ""
            If Mid$(ep, 2, 1) = "." Then ini = Left$(ep, 1)
            If Not InListe(gelistet, ep) Then
                hinweis = hinweis & "  - " & nach & " (" & ep & ") ist im gewaehlten Zeitraum nicht (mehr) verfuegbar" & vbCr
            End If

            key = "": besch = f
            Select Case f
                Case "ZF", "sZF", "Bearb.", "KF", "TF", "PVB", "sMkw", "BeDo"
                    If zuege = 1 Then
                        If zg <> "" And zg <> "1" Then
                            fehler = fehler & "  - " & nach & ": Zug " & zg & " gibt es bei 1 E-Zug nicht" & vbCr
                        End If
                        zn = 1
                    Else
                        zn = Val(zg)
                        If zn < 1 Or zn > zuege Then
                            fehler = fehler & "  - " & nach & ": bei " & f & " fehlt der Zug (1-" & zuege & ")" & vbCr
                        End If
                    End If
                    If f = "TF" Or f = "PVB" Then
                        If tr = "BAT" Or (Val(tr) >= 1 And Val(tr) <= 6 And tr = CStr(Val(tr))) Then
                            key = f & "|" & zn & "|" & tr
                            besch = f & " " & IIf(tr = "BAT", "BAT-Trupp", "Trupp " & tr) & ", " & zn & ". Zug"
                        Else
                            fehler = fehler & "  - " & nach & ": bei " & f & " fehlt der Trupp (BAT oder 1-6)" & vbCr
                        End If
                    Else
                        key = f & "|" & zn
                        besch = f & ", " & zn & ". Zug"
                    End If
                Case "TF Spez", "PVB Spez"
                    Select Case tr
                        Case "BeDo", "A-Trupp", "P" & ChrW(196) & "D", "FLT", "Sonst."
                            key = f & "|" & tr
                            besch = Left$(f, InStr(f, " ") - 1) & " " & tr
                        Case Else
                            fehler = fehler & "  - " & nach & ": bei " & f & " als Trupp BeDo, A-Trupp, P" & ChrW(196) & "D, FLT oder Sonst. waehlen" & vbCr
                    End Select
                Case Else
                    key = f
            End Select
            c.Add Array(ep, nach & " (" & ep & ")", nach, ini, amt, key, besch)
        End If
    Next i
    Set GliedPersonen = c
End Function

' Plaetze (Zeilen in "Vorlage") je Schluessel
Private Function GliedSlots(ByVal zuege As Long) As Object
    Dim d As Object, k As Long, b As Long, t As Long
    Set d = CreateObject("Scripting.Dictionary")
    If zuege >= 2 Then
        d.Add "HF", Array(21): d.Add "sHF", Array(22): d.Add "FGr", Array(23, 24): d.Add "KF FGr", Array(25)
        d.Add "TF BefSt", Array(29): d.Add "Bearb BefSt", Array(30, 31): d.Add "KF BefSt", Array(32)
        d.Add "TF BearbTr", Array(37): d.Add "Bearb BearbTr", Array(38, 39): d.Add "KF BearbTr", Array(40)
        d.Add "TF Spez|BeDo", Array(80, 82): d.Add "PVB Spez|BeDo", Array(81, 83, 84)
        d.Add "TF Spez|A-Trupp", Array(88): d.Add "PVB Spez|A-Trupp", Array(89, 90, 91, 92)
        d.Add "TF Spez|P" & ChrW(196) & "D", Array(96): d.Add "PVB Spez|P" & ChrW(196) & "D", Array(97, 98, 99, 100)
        d.Add "TF Spez|FLT", Array(104): d.Add "PVB Spez|FLT", Array(105, 106, 107, 108)
        d.Add "TF Spez|Sonst.", Array(120): d.Add "PVB Spez|Sonst.", Array(121, 122, 123, 124)
    End If
    For k = 1 To zuege
        b = 136 + (k - 1) * 64
        d.Add "ZF|" & k, Array(b)
        d.Add "sZF|" & k, Array(b + 1)
        d.Add "Bearb.|" & k, Array(b + 2, b + 3)
        d.Add "KF|" & k, Array(b + 4)
        d.Add "TF|" & k & "|BAT", Array(b + 6)
        d.Add "PVB|" & k & "|BAT", Array(b + 7, b + 8, b + 9, b + 10)
        For t = 1 To 6
            d.Add "TF|" & k & "|" & t, Array(b + 12 + (t - 1) * 6)
            d.Add "PVB|" & k & "|" & t, Array(b + 13 + (t - 1) * 6, b + 14 + (t - 1) * 6, b + 15 + (t - 1) * 6, b + 16 + (t - 1) * 6)
        Next t
        d.Add "sMkw|" & k, Array(b + 48, b + 49, b + 50)
        d.Add "BeDo|" & k, Array(b + 51, b + 52, b + 53)
    Next k
    Set GliedSlots = d
End Function

' Sucht die Schreibweise in der Personalliste der Vorlage (AX = Name, AY = Vorname, BD = Hu)
Private Function VorlageName(ByVal vdat As Variant, ByVal nach As String, ByVal ini As String, ByVal hu As Long) As String
    Dim i As Long, basis As String, t As String, treffer As New Collection, auswahl As New Collection, x As Variant
    For i = 1 To UBound(vdat)
        t = Trim$(CStr(vdat(i, 1)))
        If t <> "" Then
            basis = t
            If InStr(t, ",") > 0 Then basis = Trim$(Left$(t, InStr(t, ",") - 1))
            If StrComp(basis, nach, vbTextCompare) = 0 Then treffer.Add i
        End If
    Next i
    If treffer.Count = 0 Then Exit Function
    If treffer.Count = 1 Then VorlageName = Trim$(CStr(vdat(treffer(1), 1))): Exit Function
    If ini <> "" Then
        For Each x In treffer
            If StrComp(Left$(Trim$(CStr(vdat(x, 2))), 1), ini, vbTextCompare) = 0 Then auswahl.Add x
        Next x
        If auswahl.Count = 1 Then VorlageName = Trim$(CStr(vdat(auswahl(1), 1))): Exit Function
        If auswahl.Count > 1 Then Set treffer = auswahl
    End If
    Set auswahl = New Collection
    For Each x In treffer
        If Trim$(CStr(vdat(x, 7))) = hu & "." Then auswahl.Add x
    Next x
    If auswahl.Count = 1 Then VorlageName = Trim$(CStr(vdat(auswahl(1), 1)))
End Function

Private Function InListe(ByVal liste As Variant, ByVal nm As String) As Boolean
    Dim i As Long
    For i = 1 To UBound(liste)
        If StrComp(Trim$(CStr(liste(i, 1))), nm, vbTextCompare) = 0 Then InListe = True: Exit Function
    Next i
End Function

Private Function DateiTeil(ByVal s As String) As String
    Dim i As Long, ch As String, r As String
    For i = 1 To Len(s)
        ch = Mid$(s, i, 1)
        If ch Like "[A-Za-z0-9-]" Or AscW(ch) > 191 Then
            r = r & ch
        ElseIf Right$(r, 1) <> "_" Then
            r = r & "_"
        End If
    Next i
    DateiTeil = Left$(r, 40)
End Function

