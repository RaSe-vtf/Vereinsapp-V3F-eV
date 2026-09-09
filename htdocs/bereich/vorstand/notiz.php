<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');
$pdo = getPdo();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.');
        header('Location: notiz.php' . ($id !== null ? '?id=' . $id : ''));
        exit;
    }

    $postId = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

    if (($_POST['aktion'] ?? '') === 'speichern') {
        $titel = trim((string) ($_POST['titel'] ?? ''));
        $inhalt = trim((string) ($_POST['inhalt'] ?? ''));

        if ($titel === '') {
            setFlash('error', 'Bitte eine Überschrift angeben.');
            header('Location: notiz.php' . ($postId !== null ? '?id=' . $postId : ''));
            exit;
        }

        if ($postId === null) {
            $stmt = $pdo->prepare('INSERT INTO notizen (titel, inhalt, mitglied_id) VALUES (:titel, :inhalt, :mitglied_id)');
            $stmt->execute(['titel' => $titel, 'inhalt' => $inhalt, 'mitglied_id' => $mitglied['id']]);
            $postId = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE notizen SET titel = :titel, inhalt = :inhalt WHERE id = :id')
                ->execute(['titel' => $titel, 'inhalt' => $inhalt, 'id' => $postId]);
        }
        setFlash('success', 'Notiz wurde gespeichert.');
        header('Location: notiz.php?id=' . $postId);
        exit;
    }

    if (($_POST['aktion'] ?? '') === 'loeschen' && $postId !== null) {
        $pdo->prepare('DELETE FROM notizen WHERE id = :id')->execute(['id' => $postId]);
        setFlash('success', 'Notiz wurde gelöscht.');
        header('Location: notizen.php');
        exit;
    }
}

$notiz = ['titel' => '', 'inhalt' => ''];
if ($id !== null) {
    $stmt = $pdo->prepare('SELECT * FROM notizen WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $gefunden = $stmt->fetch();
    if (!$gefunden) {
        setFlash('error', 'Diese Notiz wurde nicht gefunden.');
        header('Location: notizen.php');
        exit;
    }
    $notiz = $gefunden;
}

$flash = takeFlash();
$tiefe = '../';
$aktivReiter = 'geschaeftsstelle';
$zurueck = 'notizen.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $id !== null ? e($notiz['titel']) : 'Neue Seite' ?> &ndash; Notizen &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <?php require __DIR__ . '/../../../includes/kopf.php'; ?>

    <main class="container" style="max-width:1040px;">

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="aktion" value="speichern">
                <input type="hidden" name="id" value="<?= $id !== null ? (int) $id : '' ?>">

                <label class="required" for="titel">Überschrift</label>
                <input type="text" id="titel" name="titel" value="<?= e($notiz['titel']) ?>" placeholder="z.B. Vorstandssitzung 12.09.2026" required>

                <label for="inhalt">Text</label>
                <div class="notiz-inhalt-feld">
                    <textarea id="inhalt" name="inhalt" rows="16"><?= e($notiz['inhalt']) ?></textarea>
                </div>
                <button type="button" id="diktier-btn" class="btn btn-secondary btn-diktieren">🎤 Diktieren</button>
                <p class="hint" id="diktier-hinweis" hidden>Diktieren wird von diesem Browser nicht unterstützt (funktioniert z.B. in Chrome/Edge). Text kann trotzdem normal eingetippt werden.</p>

                <div style="margin-top:20px; display:flex; gap:10px; align-items:center;">
                    <button type="submit" class="btn">Speichern</button>
                    <?php if ($id !== null): ?>
                        <button type="submit" form="loeschen-form" class="btn btn-secondary" onclick="return confirm('Diese Notiz &quot;<?= e($notiz['titel']) ?>&quot; wirklich löschen?');">Löschen</button>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ($id !== null): ?>
                <form id="loeschen-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="aktion" value="loeschen">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                </form>
            <?php endif; ?>
        </div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
    <script>
        (function () {
            var btn = document.getElementById('diktier-btn');
            var textarea = document.getElementById('inhalt');
            var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

            if (!SpeechRecognition) {
                btn.disabled = true;
                document.getElementById('diktier-hinweis').hidden = false;
                return;
            }

            var recognition = new SpeechRecognition();
            recognition.lang = 'de-DE';
            recognition.continuous = true;
            recognition.interimResults = true;

            var aufnahmeLaeuft = false;
            var textVorAufnahme = '';

            function trennzeichenAnfuegen() {
                if (textarea.value.length > 0 && !/\s$/.test(textarea.value)) {
                    textarea.value += ' ';
                }
                return textarea.value;
            }

            recognition.onresult = function (event) {
                var text = '';
                for (var i = 0; i < event.results.length; i++) {
                    text += event.results[i][0].transcript;
                }
                textarea.value = textVorAufnahme + text;
            };

            recognition.onerror = function () {
                aufnahmeLaeuft = false;
                btn.textContent = '🎤 Diktieren';
                btn.classList.remove('diktier-aktiv');
            };

            recognition.onend = function () {
                aufnahmeLaeuft = false;
                btn.textContent = '🎤 Diktieren';
                btn.classList.remove('diktier-aktiv');
            };

            btn.addEventListener('click', function () {
                if (aufnahmeLaeuft) {
                    recognition.stop();
                    return;
                }
                textVorAufnahme = trennzeichenAnfuegen();
                recognition.start();
                aufnahmeLaeuft = true;
                btn.textContent = '⏹ Aufnahme stoppen';
                btn.classList.add('diktier-aktiv');
            });
        })();
    </script>
</body>
</html>
