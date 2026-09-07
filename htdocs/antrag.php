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

    if ($werte['instagram'] !== '') {
        $werte['instagram'] = ltrim($werte['instagram'], '@');
        if (!preg_match('/^[A-Za-z0-9._]{1,60}$/', $werte['instagram'])) {
            $fehler[] = 'Bitte gib einen gültigen Instagram-Benutzernamen an (oder lasse das Feld leer).';
        }
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
        }
    }

    if (empty($fehler)) {
        try {
            $stmt = getPdo()->prepare(
                'INSERT INTO antraege
                    (vorname, nachname, geburtsdatum, geburtsort, strasse_hausnummer, plz, ort, telefon, email, instagram, foto_dateiname, einverstaendnis_satzung, einverstaendnis_datenschutz, einverstaendnis_bildnutzung)
                 VALUES
                    (:vorname, :nachname, :geburtsdatum, :geburtsort, :strasse_hausnummer, :plz, :ort, :telefon, :email, :instagram, :foto_dateiname, :ein_satzung, :ein_datenschutz, :ein_bildnutzung)'
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
                <div class="top-header__subtitle"><?= e(VEREIN_NAME) ?></div>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <h2>Aufnahmeantrag</h2>
            <p>Mit diesem Formular beantragst du gleichzeitig deine Mitgliedschaft bei <?= e(VEREIN_NAME) ?>. Pflichtfelder sind mit * markiert.</p>

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

                <fieldset>
                    <legend>Persönliche Daten</legend>

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
                </fieldset>

                <fieldset>
                    <legend>Adresse & Kontakt</legend>

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
                    <div class="hint">Freiwillige Angabe, z.B. für Vereins-Verlinkungen.</div>
                </fieldset>

                <fieldset>
                    <legend>Foto</legend>
                    <label class="required" for="foto">Foto von dir</label>
                    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" required>
                    <div class="hint">JPG, PNG oder WebP, maximal 6 MB.</div>
                </fieldset>

                <fieldset>
                    <legend>Einverständniserklärungen</legend>

                    <label class="inline">
                        <input type="checkbox" name="ein_satzung" value="1" <?= !empty($_POST['ein_satzung']) ? 'checked' : '' ?> required>
                        <span class="required">Ich habe die Satzung und alle Ordnungen von <?= e(VEREIN_NAME) ?> gelesen und erkenne diese verbindlich an.</span>
                    </label>

                    <label class="inline">
                        <input type="checkbox" name="ein_datenschutz" value="1" <?= !empty($_POST['ein_datenschutz']) ? 'checked' : '' ?> required>
                        <span class="required">Ich habe das <a href="impressum.php" target="_blank" rel="noopener">Impressum</a> und die <a href="datenschutz.php" target="_blank" rel="noopener">Datenschutzerklärung</a> der App zur Kenntnis genommen.</span>
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
