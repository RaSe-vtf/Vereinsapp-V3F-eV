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
$alleAusgewaehlt = $_SERVER['REQUEST_METHOD'] !== 'POST';
$rollenAusgewaehlt = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $fehler[] = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.';
    } else {
        $betreff = trim((string) ($_POST['betreff'] ?? ''));
        $nachricht = trim((string) ($_POST['nachricht'] ?? ''));
        $alleAusgewaehlt = !empty($_POST['alle']);
        $rollenAusgewaehlt = array_values(array_intersect((array) ($_POST['rollen'] ?? []), array_keys(ROLLEN_LABELS)));

        if ($betreff === '') $fehler[] = 'Bitte gib einen Betreff an.';
        if ($nachricht === '') $fehler[] = 'Bitte gib eine Nachricht an.';
        if (!$alleAusgewaehlt && empty($rollenAusgewaehlt)) {
            $fehler[] = 'Bitte wähle mindestens eine Empfängergruppe aus.';
        }

        if (empty($fehler)) {
            if ($alleAusgewaehlt) {
                $stmt = $pdo->query('SELECT email FROM mitglieder WHERE aktiv = 1');
            } else {
                $platzhalter = implode(',', array_fill(0, count($rollenAusgewaehlt), '?'));
                $stmt = $pdo->prepare("SELECT email FROM mitglieder WHERE aktiv = 1 AND rolle IN ($platzhalter)");
                $stmt->execute($rollenAusgewaehlt);
            }
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

$alleMitglieder = $pdo->query('SELECT vorname, nachname, email, rolle FROM mitglieder WHERE aktiv = 1 ORDER BY nachname, vorname')->fetchAll();

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
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">E-Mail-Verteiler</h2>
            <p class="text-muted">Verschickt eine Rundmail per Bcc, sodass Mitglieder die E-Mail-Adressen der anderen Empfänger nicht sehen.</p>

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

                <fieldset>
                    <legend>Empfänger</legend>
                    <label class="inline">
                        <input type="checkbox" name="alle" value="1" <?= $alleAusgewaehlt ? 'checked' : '' ?>>
                        <span>Alle aktiven Mitglieder</span>
                    </label>
                    <p class="text-muted" style="margin:10px 0 6px;">Oder gezielt nach Rolle:</p>
                    <?php foreach (ROLLEN_LABELS as $wert => $label): ?>
                        <label class="inline">
                            <input type="checkbox" name="rollen[]" value="<?= e($wert) ?>" <?= in_array($wert, $rollenAusgewaehlt, true) ? 'checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <p class="text-muted"><span id="empfaenger-anzahl">0</span> Empfänger ausgewählt:</p>
                <div style="overflow-x:auto; margin-bottom:20px;">
                <table id="empfaenger-tabelle">
                    <thead>
                        <tr>
                            <th>Nachname</th>
                            <th>Vorname</th>
                            <th>E-Mail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alleMitglieder as $m): ?>
                            <tr data-rolle="<?= e($m['rolle']) ?>" hidden>
                                <td><?= e($m['nachname']) ?></td>
                                <td><?= e($m['vorname']) ?></td>
                                <td><?= e($m['email']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
            var alleCheckbox = form.querySelector('input[name="alle"]');
            var rollenCheckboxen = form.querySelectorAll('input[name="rollen[]"]');
            var zeilen = document.querySelectorAll('#empfaenger-tabelle tbody tr');
            var zaehler = document.getElementById('empfaenger-anzahl');

            function aktualisieren() {
                var alle = alleCheckbox.checked;
                var ausgewaehlteRollen = [];
                rollenCheckboxen.forEach(function (cb) {
                    if (cb.checked) {
                        ausgewaehlteRollen.push(cb.value);
                    }
                });
                var anzahlSichtbar = 0;
                zeilen.forEach(function (zeile) {
                    var sichtbar = alle || ausgewaehlteRollen.indexOf(zeile.dataset.rolle) !== -1;
                    zeile.hidden = !sichtbar;
                    if (sichtbar) {
                        anzahlSichtbar++;
                    }
                });
                if (zaehler) {
                    zaehler.textContent = String(anzahlSichtbar);
                }
            }

            alleCheckbox.addEventListener('change', aktualisieren);
            rollenCheckboxen.forEach(function (cb) {
                cb.addEventListener('change', aktualisieren);
            });
            aktualisieren();
        })();
    </script>
</body>
</html>
