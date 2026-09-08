<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');
$pdo = getPdo();

$fehler = [];
$betreff = '';
$nachricht = '';
$ausgewaehlteIds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $fehler[] = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.';
    } else {
        $betreff = trim((string) ($_POST['betreff'] ?? ''));
        $nachricht = trim((string) ($_POST['nachricht'] ?? ''));
        $ausgewaehlteIds = array_map('intval', (array) ($_POST['empfaenger'] ?? []));

        if ($betreff === '') $fehler[] = 'Bitte gib einen Betreff an.';
        if ($nachricht === '') $fehler[] = 'Bitte gib eine Nachricht an.';
        if (empty($ausgewaehlteIds)) {
            $fehler[] = 'Bitte wähle mindestens einen Empfänger aus.';
        }

        if (empty($fehler)) {
            $platzhalter = implode(',', array_fill(0, count($ausgewaehlteIds), '?'));
            $stmt = $pdo->prepare("SELECT email FROM mitglieder WHERE aktiv = 1 AND id IN ($platzhalter)");
            $stmt->execute($ausgewaehlteIds);
            $empfaenger = array_column($stmt->fetchAll(), 'email');

            if (empty($empfaenger)) {
                $fehler[] = 'Für die gewählte Auswahl gibt es keine aktiven Mitglieder mit E-Mail-Adresse.';
            } else {
                $ergebnis = sendeRundmail($betreff, $nachricht, $empfaenger);
                if ($ergebnis['fehlgeschlagen'] > 0) {
                    setFlash('error', $ergebnis['erfolgreich'] . ' E-Mail(s) verschickt, ' . $ergebnis['fehlgeschlagen'] . ' fehlgeschlagen.');
                } else {
                    setFlash('success', 'Rundmail an ' . $ergebnis['erfolgreich'] . ' Mitglied(er) verschickt.');
                }
                header('Location: verteiler.php');
                exit;
            }
        }
    }
}

$alleMitglieder = $pdo->query('SELECT id, vorname, nachname, email, rolle FROM mitglieder WHERE aktiv = 1 ORDER BY nachname, vorname')->fetchAll();

$flash = takeFlash();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>E-Mail-Verteiler &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php
    $tiefe = '../';
    $seitenUntertitel = 'Geschäftsstelle';
    $aktivReiter = 'geschaeftsstelle';
    $zurueck = '../home.php';
    require __DIR__ . '/../../../includes/kopf.php';
    ?>

    <main class="container">
        <nav class="subnav">
            <a href="antraege.php">Aufnahmeanträge</a>
            <a href="mitglieder.php">Mitgliederverwaltung</a>
            <a href="verteiler.php" class="active">E-Mail-Verteiler</a>
            <a href="kassenwart/index.php">Kassenwart</a>
            <a href="vereinsdokumente.php">Vereinsdokumente</a>
            <a href="protokolle.php">Protokolle</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">E-Mail-Verteiler</h2>
            <p class="text-muted">Verschickt eine Rundmail per Bcc, sodass Mitglieder die E-Mail-Adressen der anderen Empfänger nicht sehen. Häkchen setzen, wer die Mail bekommen soll &ndash; über das Filter-Symbol bei "Rolle" lässt sich die Liste zum schnelleren Auswählen eingrenzen.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
            <?php endif; ?>

            <?php if (!empty($fehler)): ?>
                <div class="alert alert-error">
                    <ul style="margin:0; padding-left:20px;">
                        <?php foreach ($fehler as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" id="rundmail-form">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                <p class="text-muted"><span id="empfaenger-anzahl">0</span> Empfänger ausgewählt:</p>
                <div style="overflow-x:auto; margin-bottom:20px;">
                <table id="empfaenger-tabelle" class="tabelle-einzeilig">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="empfaenger-alle-checkbox" title="Alle sichtbaren Zeilen auswählen"></th>
                            <th>Nachname</th>
                            <th>Vorname</th>
                            <th>
                                Rolle
                                <button type="button" class="th-filter-btn" id="rollen-filter-btn" aria-haspopup="true" aria-expanded="false">Filter &#9662;</button>
                            </th>
                            <th>E-Mail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alleMitglieder as $m): ?>
                            <tr data-rolle="<?= e($m['rolle']) ?>">
                                <td><input type="checkbox" name="empfaenger[]" class="empfaenger-checkbox" value="<?= (int) $m['id'] ?>" <?= in_array((int) $m['id'], $ausgewaehlteIds, true) ? 'checked' : '' ?>></td>
                                <td><?= e($m['nachname']) ?></td>
                                <td><?= e($m['vorname']) ?></td>
                                <td><?= e(rollenLabel($m['rolle'])) ?></td>
                                <td><?= e($m['email']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <div id="rollen-filter-dropdown" class="filter-dropdown-panel" hidden>
                    <label class="inline">
                        <input type="checkbox" id="rollen-filter-alle" checked>
                        <span><strong>Alle Rollen</strong></span>
                    </label>
                    <hr style="border:none; border-top:1px solid var(--farbe-border); margin:8px 0;">
                    <?php foreach (ROLLEN_LABELS as $wert => $label): ?>
                        <label class="inline">
                            <input type="checkbox" class="rollen-filter-checkbox" value="<?= e($wert) ?>" checked>
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label class="required" for="betreff">Betreff</label>
                <input type="text" id="betreff" name="betreff" value="<?= e($betreff) ?>" required>

                <label class="required" for="nachricht">Nachricht</label>
                <textarea id="nachricht" name="nachricht" rows="10" required style="width:100%; padding:10px 12px; border:1px solid var(--farbe-border); border-radius:8px; font-family:inherit; font-size:1rem;"><?= e($nachricht) ?></textarea>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn" onclick="return confirm('Rundmail jetzt an die ausgewählten Mitglieder verschicken?');">Rundmail senden</button>
                </div>
            </form>
        </div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
    <script>
        (function () {
            var form = document.getElementById('rundmail-form');
            if (!form) {
                return;
            }
            var zeilen = Array.prototype.slice.call(document.querySelectorAll('#empfaenger-tabelle tbody tr'));
            var checkboxen = Array.prototype.slice.call(document.querySelectorAll('.empfaenger-checkbox'));
            var alleCheckbox = document.getElementById('empfaenger-alle-checkbox');
            var zaehler = document.getElementById('empfaenger-anzahl');

            var filterBtn = document.getElementById('rollen-filter-btn');
            var filterPanel = document.getElementById('rollen-filter-dropdown');
            var filterAlleCheckbox = document.getElementById('rollen-filter-alle');
            var filterCheckboxen = Array.prototype.slice.call(document.querySelectorAll('.rollen-filter-checkbox'));

            // Panel aus der scrollenden Tabelle herausloesen, damit es nicht
            // vom overflow-x:auto-Container abgeschnitten wird.
            document.body.appendChild(filterPanel);

            function sichtbareZeilen() {
                return zeilen.filter(function (z) { return !z.hidden; });
            }

            function aktualisiereZaehlerUndMaster() {
                var anzahl = 0;
                checkboxen.forEach(function (cb) {
                    if (cb.checked) {
                        anzahl++;
                    }
                });
                if (zaehler) {
                    zaehler.textContent = String(anzahl);
                }

                var sichtbar = sichtbareZeilen();
                var sichtbareCheckboxen = sichtbar.map(function (z) { return z.querySelector('.empfaenger-checkbox'); });
                var angehaktSichtbar = sichtbareCheckboxen.filter(function (cb) { return cb.checked; }).length;
                if (sichtbareCheckboxen.length === 0) {
                    alleCheckbox.checked = false;
                    alleCheckbox.indeterminate = false;
                } else if (angehaktSichtbar === sichtbareCheckboxen.length) {
                    alleCheckbox.checked = true;
                    alleCheckbox.indeterminate = false;
                } else if (angehaktSichtbar === 0) {
                    alleCheckbox.checked = false;
                    alleCheckbox.indeterminate = false;
                } else {
                    alleCheckbox.checked = false;
                    alleCheckbox.indeterminate = true;
                }
            }

            checkboxen.forEach(function (cb) {
                cb.addEventListener('change', aktualisiereZaehlerUndMaster);
            });

            alleCheckbox.addEventListener('change', function () {
                var sollAngehaktSein = alleCheckbox.checked;
                sichtbareZeilen().forEach(function (z) {
                    z.querySelector('.empfaenger-checkbox').checked = sollAngehaktSein;
                });
                aktualisiereZaehlerUndMaster();
            });

            function wendeFilterAn() {
                var ausgewaehlteRollen = filterCheckboxen.filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
                zeilen.forEach(function (zeile) {
                    zeile.hidden = ausgewaehlteRollen.indexOf(zeile.dataset.rolle) === -1;
                });
                aktualisiereZaehlerUndMaster();
            }

            filterAlleCheckbox.addEventListener('change', function () {
                filterCheckboxen.forEach(function (cb) {
                    cb.checked = filterAlleCheckbox.checked;
                });
                wendeFilterAn();
            });

            filterCheckboxen.forEach(function (cb) {
                cb.addEventListener('change', function () {
                    filterAlleCheckbox.checked = filterCheckboxen.every(function (c) { return c.checked; });
                    wendeFilterAn();
                });
            });

            function oeffnePanel() {
                var rect = filterBtn.getBoundingClientRect();
                filterPanel.style.top = (rect.bottom + window.scrollY + 4) + 'px';
                filterPanel.style.left = Math.min(rect.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - 220) + 'px';
                filterPanel.hidden = false;
                filterBtn.setAttribute('aria-expanded', 'true');
            }

            function schliessePanel() {
                filterPanel.hidden = true;
                filterBtn.setAttribute('aria-expanded', 'false');
            }

            filterBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (filterPanel.hidden) {
                    oeffnePanel();
                } else {
                    schliessePanel();
                }
            });

            document.addEventListener('click', function (e) {
                if (!filterPanel.hidden && !filterPanel.contains(e.target) && e.target !== filterBtn) {
                    schliessePanel();
                }
            });

            aktualisiereZaehlerUndMaster();
        })();
    </script>
</body>
</html>
