Attribute VB_Name = "Einsatzverwaltung"
Option Explicit

' =====================================================================
'  Einsatzverwaltung (Excel fuer Windows)
'  - EinsatzLaden                  : Einsatzgliederung in naechsten freien Platz laden
'  - EinsatzEntfernen              : einen geladenen Einsatz wieder entfernen
'  - DoppelgliederungenEntscheiden : offene Doppelgliederungen abfragen
'  - TabelleLeeren                 : Grunddaten + Wochenend-Entscheidungen leeren
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
    If MsgBox("Grunddaten und die Wochenend-Entscheidungen wirklich leeren?" & vbCr & _
              "(Geladene Einsaetze bleiben erhalten - dafuer gibt es ""Einsatz entfernen"".)", _
              vbYesNo + vbQuestion, "Tabelle leeren") = vbNo Then Exit Sub
    ThisWorkbook.Worksheets("Grunddaten").Range("E2:AQ300").ClearContents
    ThisWorkbook.Worksheets("Verf" & ChrW(252) & "gbarkeit Wochenende").Range("L27:L163").ClearContents
    MsgBox "Geleert. Jetzt die neuen ePlan-Daten einfuegen.", vbInformation, "Tabelle leeren"
End Sub

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
