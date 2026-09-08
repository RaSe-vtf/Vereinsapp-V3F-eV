<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$fehler = [];
$werte = [
    'vorname' => '',
    'nachname' => '',
    'geburtsdatum' => '',
    'geburtsort' => '',
    'strasse_hausnummer' => '',
    'plz' => '',
    'ort' => '',
    'telefon' => '',
    'email' => '',
    'instagram' => '',
    'shirt_groesse' => '',
    'portraet' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $fehler[] = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.';
    }

    // Honeypot: Bots fuellen dieses versteckte Feld oft aus.
    if (!empty($_POST['website'] ?? '')) {
        $fehler[] = 'Ungueltige Anfrage.';
    }

    foreach ($werte as $feld => $default) {
        $werte[$feld] = trim((string) ($_POST[$feld] ?? ''));
    }

    if ($werte['vorname'] === '') $fehler[] = 'Bitte gib deinen Vornamen an.';
    if ($werte['nachname'] === '') $fehler[] = 'Bitte gib deinen Nachnamen an.';

    if ($werte['geburtsdatum'] === '') {
        $fehler[] = 'Bitte gib dein Geburtsdatum an.';
    } else {
        $datum = DateTime::createFromFormat('Y-m-d', $werte['geburtsdatum']);
        if (!$datum || $datum > new DateTime()) {
            $fehler[] = 'Bitte gib ein gültiges Geburtsdatum an.';
        }
    }

    if ($werte['geburtsort'] === '') $fehler[] = 'Bitte gib deinen Geburtsort an.';
    if ($werte['strasse_hausnummer'] === '') $fehler[] = 'Bitte gib deine Straße und Hausnummer an.';
    if ($werte['plz'] === '' || !preg_match('/^\d{4,5}$/', $werte['plz'])) $fehler[] = 'Bitte gib eine gültige Postleitzahl an.';
    if ($werte['ort'] === '') $fehler[] = 'Bitte gib deinen Wohnort an.';
    if ($werte['telefon'] === '') $fehler[] = 'Bitte gib deine Telefonnummer an.';

    if ($werte['email'] === '' || !filter_var($werte['email'], FILTER_VALIDATE_EMAIL)) {
        $fehler[] = 'Bitte gib eine gültige E-Mail-Adresse an.';
    }

    if ($werte['instagram'] === '') {
        $fehler[] = 'Bitte gib deinen Instagram-Benutzernamen an.';
    } else {
        $werte['instagram'] = ltrim($werte['instagram'], '@');
        if (!preg_match('/^[A-Za-z0-9._]{1,60}$/', $werte['instagram'])) {
            $fehler[] = 'Bitte gib einen gültigen Instagram-Benutzernamen an.';
        }
    }

    if ($werte['shirt_groesse'] === '' || !in_array($werte['shirt_groesse'], SHIRT_GROESSEN, true)) {
        $fehler[] = 'Bitte wähle deine Shirt-Größe aus.';
    }

    if ($werte['portraet'] === '') {
        $fehler[] = 'Bitte gib ein kurzes Porträt zur Vorstellung an.';
    }

    $passwort = (string) ($_POST['passwort'] ?? '');
    $passwortWiederholt = (string) ($_POST['passwort_wiederholt'] ?? '');
    if (strlen($passwort) < 8) {
        $fehler[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif ($passwort !== $passwortWiederholt) {
        $fehler[] = 'Die Passwort-Wiederholung stimmt nicht überein.';
    }

    $einSatzung = isset($_POST['ein_satzung']) ? 1 : 0;
    $einDatenschutz = isset($_POST['ein_datenschutz']) ? 1 : 0;
    $einBildnutzung = isset($_POST['ein_bildnutzung']) ? 1 : 0;

    if (!$einSatzung) $fehler[] = 'Du musst der Satzung und den Ordnungen des Vereins zustimmen, um Mitglied zu werden.';
    if (!$einDatenschutz) $fehler[] = 'Du musst das Impressum und die Datenschutzerklärung zur Kenntnis nehmen.';

    $fotoDateiname = null;
    if (empty($fehler)) {
        try {
            $fotoDateiname = handleFotoUpload($_FILES['foto'] ?? []);
        } catch (RuntimeException $e) {
            $fehler[] = $e->getMessage();
        } catch (\Throwable $e) {
            $fehler[] = 'Foto konnte nicht verarbeitet werden. Bitte ein anderes Foto versuchen.';
        }
    }

    if (empty($fehler)) {
        try {
            $stmt = getPdo()->prepare(
                'INSERT INTO antraege
                    (vorname, nachname, geburtsdatum, geburtsort, strasse_hausnummer, plz, ort, telefon, email, instagram, foto_dateiname, shirt_groesse, portraet, passwort_hash, einverstaendnis_satzung, einverstaendnis_datenschutz, einverstaendnis_bildnutzung)
                 VALUES
                    (:vorname, :nachname, :geburtsdatum, :geburtsort, :strasse_hausnummer, :plz, :ort, :telefon, :email, :instagram, :foto_dateiname, :shirt_groesse, :portraet, :passwort_hash, :ein_satzung, :ein_datenschutz, :ein_bildnutzung)'
            );
            $stmt->execute([
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
                'foto_dateiname' => $fotoDateiname,
                'shirt_groesse' => $werte['shirt_groesse'] !== '' ? $werte['shirt_groesse'] : null,
                'portraet' => $werte['portraet'] !== '' ? $werte['portraet'] : null,
                'passwort_hash' => password_hash($passwort, PASSWORD_DEFAULT),
                'ein_satzung' => $einSatzung,
                'ein_datenschutz' => $einDatenschutz,
                'ein_bildnutzung' => $einBildnutzung,
            ]);

            header('Location: antrag_erfolg.php');
            exit;
        } catch (PDOException $e) {
            $fehler[] = 'Dein Antrag konnte nicht gespeichert werden. Bitte versuche es später erneut.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aufnahmeantrag &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <header class="top-header">
        <div class="top-header__inner">
            <img class="top-header__logo" src="assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <div>
                <div class="top-header__title"><?= e(APP_NAME) ?></div>
                <div class="top-header__subtitle"><?= e(vereinNameNowrap()) ?></div>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <h2>Aufnahmeantrag</h2>
            <p>Mit diesem Formular beantragst du deine Mitgliedschaft bei <?= e(vereinNameNowrap()) ?> Das Formular ist vollständig auszufüllen.</p>

            <?php if (!empty($fehler)): ?>
                <div class="alert alert-error">
                    <strong>Bitte korrigiere folgende Angaben:</strong>
                    <ul>
                        <?php foreach ($fehler as $f): ?>
                            <li><?= e($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <div class="honeypot" aria-hidden="true">
                    <label for="website">Bitte leer lassen</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <h3 style="margin-bottom:2px;">Für alle Mitglieder sichtbar</h3>
                <p class="text-muted" style="margin-top:0;">Diese Angaben erscheinen auf deinem Sportlerprofil, das alle Mitglieder von <?= e(vereinNameNowrap()) ?> sehen können.</p>

                <fieldset>
                    <legend>Persönliche Daten</legend>

                    <div class="form-row">
                        <div>
                            <label for="vorname">Vorname</label>
                            <input type="text" id="vorname" name="vorname" value="<?= e($werte['vorname']) ?>" required>
                        </div>
                        <div>
                            <label for="nachname">Nachname</label>
                            <input type="text" id="nachname" name="nachname" value="<?= e($werte['nachname']) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label for="geburtsdatum">Geburtsdatum</label>
                            <input type="date" id="geburtsdatum" name="geburtsdatum" value="<?= e($werte['geburtsdatum']) ?>" required>
                        </div>
                        <div>
                            <label for="ort">Wohnort</label>
                            <input type="text" id="ort" name="ort" value="<?= e($werte['ort']) ?>" required>
                        </div>
                    </div>

                    <label for="telefon">Telefonnummer</label>
                    <input type="tel" id="telefon" name="telefon" value="<?= e($werte['telefon']) ?>" required>
                    <div class="hint">Auf dem Sportlerprofil erscheinen nur die letzten 4 Ziffern (zur WhatsApp-Zuordnung), die vollständige Nummer sieht nur der Vorstand.</div>

                    <label for="instagram">Instagram</label>
                    <input type="text" id="instagram" name="instagram" placeholder="dein_benutzername" value="<?= e($werte['instagram']) ?>" required>

                    <label for="shirt_groesse">Shirt-Größe</label>
                    <select id="shirt_groesse" name="shirt_groesse" required>
                        <option value="">Bitte auswählen</option>
                        <?php foreach (SHIRT_GROESSEN as $groesse): ?>
                            <option value="<?= e($groesse) ?>" <?= $werte['shirt_groesse'] === $groesse ? 'selected' : '' ?>><?= e($groesse) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="portraet">Kurzes Porträt zur Vorstellung</label>
                    <textarea id="portraet" name="portraet" rows="5" placeholder="Erzähl den anderen Mitgliedern etwas über deine sportlichen Vorlieben und Interessen ..." style="width:100%; padding:10px 12px; border:1px solid var(--farbe-border); border-radius:8px; font-family:inherit; font-size:1rem;" required><?= e($werte['portraet']) ?></textarea>

                    <label for="foto" style="margin-top:14px;">Foto von dir</label>
                    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" required>
                    <div class="hint">JPG, PNG oder WebP, maximal 10 MB.</div>
                </fieldset>

                <hr style="border:none; border-top:2px solid var(--farbe-border); margin:28px 0;">

                <h3 style="margin-bottom:2px;">Nur für den Vorstand sichtbar</h3>
                <p class="text-muted" style="margin-top:0;">Diese Angaben sind aus Sicherheitsgründen nicht öffentlich einsehbar &ndash; Zugriff hat ausschließlich der Vorstand.</p>

                <fieldset>
                    <legend>Weitere persönliche Daten</legend>

                    <label for="geburtsort">Geburtsort</label>
                    <input type="text" id="geburtsort" name="geburtsort" value="<?= e($werte['geburtsort']) ?>" required>

                    <label for="strasse_hausnummer">Straße und Hausnummer</label>
                    <input type="text" id="strasse_hausnummer" name="strasse_hausnummer" value="<?= e($werte['strasse_hausnummer']) ?>" required>

                    <label for="plz">Postleitzahl</label>
                    <input type="text" id="plz" name="plz" inputmode="numeric" value="<?= e($werte['plz']) ?>" required>

                    <label for="email">E-Mail-Adresse</label>
                    <input type="email" id="email" name="email" value="<?= e($werte['email']) ?>" required>
                </fieldset>

                <fieldset>
                    <legend>Zugangsdaten</legend>
                    <p class="text-muted" style="margin-top:0;">Wähle hier dein Passwort für den Mitgliederbereich. Sobald der Vorstand deinen Antrag annimmt, kannst du dich direkt damit einloggen.</p>

                    <label for="passwort">Passwort</label>
                    <input type="password" id="passwort" name="passwort" minlength="8" required>

                    <label for="passwort_wiederholt">Passwort wiederholen</label>
                    <input type="password" id="passwort_wiederholt" name="passwort_wiederholt" minlength="8" required>
                </fieldset>

                <fieldset>
                    <legend>Einverständniserklärungen</legend>

                    <label class="inline">
                        <input type="checkbox" name="ein_satzung" value="1" <?= !empty($_POST['ein_satzung']) ? 'checked' : '' ?> required>
                        <span>Ich habe die Satzung und alle Ordnungen von <?= e(vereinNameNowrap()) ?> gelesen und erkenne diese verbindlich an.</span>
                    </label>

                    <label class="inline">
                        <input type="checkbox" name="ein_datenschutz" value="1" <?= !empty($_POST['ein_datenschutz']) ? 'checked' : '' ?> required>
                        <span>Ich habe das <a href="impressum.php" target="_blank" rel="noopener">Impressum</a> und die <a href="datenschutz.php" target="_blank" rel="noopener">Datenschutzerklärung</a> der App zur Kenntnis genommen.</span>
                    </label>

                    <label class="inline">
                        <input type="checkbox" name="ein_bildnutzung" value="1" <?= !empty($_POST['ein_bildnutzung']) ? 'checked' : '' ?>>
                        <span>Ich bin damit einverstanden, dass im Rahmen des Vereinslebens entstandene Fotos/Videos von mir für Social-Media-Kanäle des Vereins verwendet werden dürfen. Diese Einwilligung ist freiwillig und kann jederzeit widerrufen werden.</span>
                    </label>
                </fieldset>

                <button type="submit" class="btn">Antrag absenden</button>
            </form>
        </div>
    </main>

    <footer>
        <a href="index.php">Startseite</a>
        <a href="impressum.php">Impressum</a>
        <a href="datenschutz.php">Datenschutz</a>
    </footer>
</body>
</html>
