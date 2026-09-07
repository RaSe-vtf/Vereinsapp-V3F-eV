<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$mitglied = requireMemberLogin('../login.php');

$fehler = [];
$erfolg = '';
$datenFehler = [];
$datenErfolg = '';
$werte = [
    'vorname' => $mitglied['vorname'],
    'nachname' => $mitglied['nachname'],
    'geburtsdatum' => $mitglied['geburtsdatum'],
    'geburtsort' => $mitglied['geburtsort'],
    'strasse_hausnummer' => $mitglied['strasse_hausnummer'],
    'plz' => $mitglied['plz'],
    'ort' => $mitglied['ort'],
    'telefon' => $mitglied['telefon'],
    'email' => $mitglied['email'],
    'instagram' => $mitglied['instagram'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'daten_aendern') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $datenFehler[] = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.';
    } else {
        foreach ($werte as $feld => $default) {
            $werte[$feld] = trim((string) ($_POST[$feld] ?? ''));
        }

        if ($werte['vorname'] === '') $datenFehler[] = 'Bitte gib deinen Vornamen an.';
        if ($werte['nachname'] === '') $datenFehler[] = 'Bitte gib deinen Nachnamen an.';

        if ($werte['geburtsdatum'] === '') {
            $datenFehler[] = 'Bitte gib dein Geburtsdatum an.';
        } else {
            $datum = DateTime::createFromFormat('Y-m-d', $werte['geburtsdatum']);
            if (!$datum || $datum > new DateTime()) {
                $datenFehler[] = 'Bitte gib ein gültiges Geburtsdatum an.';
            }
        }

        if ($werte['geburtsort'] === '') $datenFehler[] = 'Bitte gib deinen Geburtsort an.';
        if ($werte['strasse_hausnummer'] === '') $datenFehler[] = 'Bitte gib deine Straße und Hausnummer an.';
        if ($werte['plz'] === '' || !preg_match('/^\d{4,5}$/', $werte['plz'])) $datenFehler[] = 'Bitte gib eine gültige Postleitzahl an.';
        if ($werte['ort'] === '') $datenFehler[] = 'Bitte gib deinen Wohnort an.';
        if ($werte['telefon'] === '') $datenFehler[] = 'Bitte gib deine Telefonnummer an.';

        if ($werte['email'] === '' || !filter_var($werte['email'], FILTER_VALIDATE_EMAIL)) {
            $datenFehler[] = 'Bitte gib eine gültige E-Mail-Adresse an.';
        } else {
            $stmt = getPdo()->prepare('SELECT id FROM mitglieder WHERE email = :email AND id <> :id');
            $stmt->execute(['email' => $werte['email'], 'id' => $mitglied['id']]);
            if ($stmt->fetch()) {
                $datenFehler[] = 'Diese E-Mail-Adresse wird bereits von einem anderen Konto verwendet.';
            }
        }

        if ($werte['instagram'] !== '') {
            $werte['instagram'] = ltrim($werte['instagram'], '@');
            if (!preg_match('/^[A-Za-z0-9._]{1,60}$/', $werte['instagram'])) {
                $datenFehler[] = 'Bitte gib einen gültigen Instagram-Benutzernamen an (oder lasse das Feld leer).';
            }
        }

        $neuesFoto = null;
        $fotoDatei = $_FILES['foto'] ?? null;
        if ($fotoDatei !== null && ($fotoDatei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (empty($datenFehler)) {
                try {
                    $neuesFoto = handleFotoUpload($fotoDatei);
                } catch (RuntimeException $e) {
                    $datenFehler[] = $e->getMessage();
                } catch (\Throwable $e) {
                    $datenFehler[] = 'Foto konnte nicht verarbeitet werden. Bitte ein anderes Foto versuchen.';
                }
            }
        }

        if (empty($datenFehler)) {
            $altesFoto = $mitglied['foto_dateiname'];
            $stmt = getPdo()->prepare(
                'UPDATE mitglieder SET
                    vorname = :vorname, nachname = :nachname, geburtsdatum = :geburtsdatum,
                    geburtsort = :geburtsort, strasse_hausnummer = :strasse_hausnummer, plz = :plz,
                    ort = :ort, telefon = :telefon, email = :email, instagram = :instagram
                    ' . ($neuesFoto !== null ? ', foto_dateiname = :foto_dateiname' : '') . '
                 WHERE id = :id'
            );
            $parameter = [
                'vorname' => $werte['vorname'],
                'nachname' => $werte['nachname'],
                'geburtsdatum' => $werte['geburtsdatum'],
                'geburtsort' => $werte['geburtsort'],
                'strasse_hausnummer' => $werte['strasse_hausnummer'],
                'plz' => $werte['plz'],
                'ort' => $werte['ort'],
                'telefon' => $werte['telefon'],
                'email' => $werte['email'],
                'instagram' => $werte['instagram'] !== '' ? $werte['instagram'] : null,
                'id' => $mitglied['id'],
            ];
            if ($neuesFoto !== null) {
                $parameter['foto_dateiname'] = $neuesFoto;
            }
            $stmt->execute($parameter);

            if ($neuesFoto !== null && $altesFoto !== null && $altesFoto !== $neuesFoto) {
                $altesFotoPfad = __DIR__ . '/../../private/uploads/fotos/' . basename($altesFoto);
                if (is_file($altesFotoPfad)) {
                    unlink($altesFotoPfad);
                }
            }

            header('Location: index.php?daten_gespeichert=1');
            exit;
        }
    }
}

if (isset($_GET['daten_gespeichert'])) {
    $datenErfolg = 'Deine Daten wurden gespeichert.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'passwort_aendern') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $fehler[] = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.';
    } else {
        $aktuelles = (string) ($_POST['aktuelles_passwort'] ?? '');
        $neu = (string) ($_POST['neues_passwort'] ?? '');
        $neuWiederholt = (string) ($_POST['neues_passwort_wiederholt'] ?? '');

        if ($mitglied['passwort_hash'] !== null && !password_verify($aktuelles, $mitglied['passwort_hash'])) {
            $fehler[] = 'Das aktuelle Passwort ist nicht korrekt.';
        }
        if (strlen($neu) < 8) {
            $fehler[] = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        }
        if ($neu !== $neuWiederholt) {
            $fehler[] = 'Die Passwort-Wiederholung stimmt nicht überein.';
        }

        if (empty($fehler)) {
            $stmt = getPdo()->prepare('UPDATE mitglieder SET passwort_hash = :hash WHERE id = :id');
            $stmt->execute(['hash' => password_hash($neu, PASSWORD_DEFAULT), 'id' => $mitglied['id']]);
            invalidiereAndereRememberTokens((int) $mitglied['id']);
            $erfolg = 'Dein Passwort wurde geändert.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mein Bereich &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php
    $tiefe = '';
    $seitenUntertitel = rollenLabel($mitglied['rolle']);
    $aktivReiter = 'meine-daten';
    $zurueck = 'home.php';
    require __DIR__ . '/../../includes/kopf.php';
    ?>

    <main class="container">
        <div class="card">
            <h2>Meine Daten</h2>
            <p class="text-muted">Rolle, Status und Passwort verwaltet der Vorstand bzw. das Formular weiter unten. Alle anderen Angaben kannst du hier selbst ändern.</p>

            <?php if (!empty($datenFehler)): ?>
                <div class="alert alert-error">
                    <ul style="margin:0; padding-left:20px;">
                        <?php foreach ($datenFehler as $f): ?><li><?= e($f) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($datenErfolg !== ''): ?>
                <div class="alert alert-success"><?= e($datenErfolg) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="daten_aendern">

                <label class="text-muted" style="font-weight:600;">Rolle</label>
                <div style="margin-bottom:14px;"><?= e(rollenLabel($mitglied['rolle'])) ?></div>

                <div class="form-row">
                    <div>
                        <label class="required" for="vorname">Vorname</label>
                        <input type="text" id="vorname" name="vorname" value="<?= e($werte['vorname']) ?>" required>
                    </div>
                    <div>
                        <label class="required" for="nachname">Nachname</label>
                        <input type="text" id="nachname" name="nachname" value="<?= e($werte['nachname']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label class="required" for="geburtsdatum">Geburtsdatum</label>
                        <input type="date" id="geburtsdatum" name="geburtsdatum" value="<?= e($werte['geburtsdatum']) ?>" required>
                    </div>
                    <div>
                        <label class="required" for="geburtsort">Geburtsort</label>
                        <input type="text" id="geburtsort" name="geburtsort" value="<?= e($werte['geburtsort']) ?>" required>
                    </div>
                </div>

                <label class="required" for="strasse_hausnummer">Straße und Hausnummer</label>
                <input type="text" id="strasse_hausnummer" name="strasse_hausnummer" value="<?= e($werte['strasse_hausnummer']) ?>" required>

                <div class="form-row">
                    <div>
                        <label class="required" for="plz">Postleitzahl</label>
                        <input type="text" id="plz" name="plz" inputmode="numeric" value="<?= e($werte['plz']) ?>" required>
                    </div>
                    <div>
                        <label class="required" for="ort">Ort</label>
                        <input type="text" id="ort" name="ort" value="<?= e($werte['ort']) ?>" required>
                    </div>
                </div>

                <label class="required" for="telefon">Telefonnummer</label>
                <input type="tel" id="telefon" name="telefon" value="<?= e($werte['telefon']) ?>" required>

                <label class="required" for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" value="<?= e($werte['email']) ?>" required>

                <label for="instagram">Instagram (optional)</label>
                <input type="text" id="instagram" name="instagram" placeholder="dein_benutzername" value="<?= e($werte['instagram']) ?>">

                <label for="foto">Foto</label>
                <?php if ($mitglied['foto_dateiname']): ?>
                    <div style="margin-bottom:8px;">
                        <img class="foto-preview foto-zoombar" src="foto.php?typ=mitglied&id=<?= (int) $mitglied['id'] ?>" alt="Aktuelles Foto">
                    </div>
                <?php endif; ?>
                <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                <div class="hint">Nur ausfüllen, wenn du dein Foto ersetzen möchtest. JPG, PNG oder WebP, maximal 10 MB.</div>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn">Daten speichern</button>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:20px;">
            <h2>Passwort ändern</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="passwort_aendern">

                <?php if ($mitglied['passwort_hash'] !== null): ?>
                    <label class="required" for="aktuelles_passwort">Aktuelles Passwort</label>
                    <input type="password" id="aktuelles_passwort" name="aktuelles_passwort" required>
                <?php endif; ?>

                <label class="required" for="neues_passwort">Neues Passwort</label>
                <input type="password" id="neues_passwort" name="neues_passwort" minlength="8" required>

                <label class="required" for="neues_passwort_wiederholt">Neues Passwort wiederholen</label>
                <input type="password" id="neues_passwort_wiederholt" name="neues_passwort_wiederholt" minlength="8" required>

                <div style="margin-top:16px;">
                    <button type="submit" class="btn">Passwort speichern</button>
                </div>
            </form>
        </div>

        <?php if ($mitglied['sepa_erteilt_am'] !== null): ?>
        <div class="card" style="margin-top:20px;">
            <h2>Bankverbindung</h2>
            <p class="text-muted">Aus Sicherheitsgründen nicht direkt änderbar. Für eine neue Bankverbindung bitte das SEPA-Mandat erneut ausfüllen &ndash; der Vorstand wird darüber automatisch informiert.</p>

            <table class="tabelle-eigenschaften">
                <tr><th>Kontoinhaber</th><td><?= e((string) $mitglied['sepa_kontoinhaber']) ?></td></tr>
                <tr><th>IBAN</th><td class="nowrap-wert"><?= e((string) $mitglied['sepa_iban']) ?></td></tr>
                <tr><th>BIC</th><td class="nowrap-wert"><?= $mitglied['sepa_bic'] ? e($mitglied['sepa_bic']) : '&ndash;' ?></td></tr>
                <tr><th>Mandatsreferenz</th><td class="nowrap-wert"><?= e((string) $mitglied['sepa_mandatsreferenz']) ?></td></tr>
                <tr><th>Erteilt am</th><td class="nowrap-wert"><?= e((new DateTime($mitglied['sepa_erteilt_am']))->format('d.m.Y')) ?></td></tr>
            </table>

            <a href="sepa_mandat.php" class="btn btn-secondary">Bankverbindung ändern</a>
        </div>
        <?php endif; ?>
    </main>
    <script src="../assets/js/lightbox.js" defer></script>
    <script src="../assets/js/menue.js" defer></script>
</body>
</html>
