Attribute VB_Name = "Einsatzverwaltung"
Option Explicit

' =====================================================================
'  Einsatzverwaltung (Excel fuer Windows)
'  - EinsatzLaden                  : Einsatzgliederung in naechsten freien Platz laden
'  - EinsatzEntfernen              : einen geladenen Einsatz wieder entfernen
'  - DoppelgliederungenEntscheiden : offene Doppelgliederungen abfragen
'  - TabelleLeeren                 : Grunddaten + Wochenend-Entscheidungen leeren
'  - GrunddatenAufbereiten         : eingefuegten ePlan-Text in Spalten verteilen
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
