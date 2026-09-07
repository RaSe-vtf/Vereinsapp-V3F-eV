<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireAdmin('../../login.php', '../index.php');
$pdo = getPdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aktion'], $_POST['id'])) {
    if (checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $zielId = (int) $_POST['id'];

        if ($_POST['aktion'] === 'passwort_vergeben') {
            $neuesPasswort = generateInitialPasswort();
            $pdo->prepare('UPDATE mitglieder SET passwort_hash = :hash WHERE id = :id')
                ->execute(['hash' => password_hash($neuesPasswort, PASSWORD_DEFAULT), 'id' => $zielId]);
            loescheAlleRememberTokens($zielId);
            setFlash('success', 'Neues Passwort für dieses Mitglied: "' . $neuesPasswort . '" — bitte sicher übermitteln, es wird nur einmal angezeigt.');
        } elseif ($_POST['aktion'] === 'loeschen') {
            if ($zielId === (int) $mitglied['id']) {
                setFlash('error', 'Du kannst dein eigenes Konto nicht löschen. Bitte einen anderen Admin bitten.');
            } else {
                $pdo->prepare('DELETE FROM mitglieder WHERE id = :id')->execute(['id' => $zielId]);
                setFlash('success', 'Mitglied wurde gelöscht.');
            }
        } elseif ($_POST['aktion'] === 'admin_umschalten') {
            if ($zielId === (int) $mitglied['id']) {
                setFlash('error', 'Du kannst dir dein eigenes Admin-Recht nicht selbst entziehen. Bitte einen anderen Admin bitten.');
            } else {
                $pdo->prepare('UPDATE mitglieder SET ist_admin = NOT ist_admin WHERE id = :id')->execute(['id' => $zielId]);
                setFlash('success', 'Admin-Status wurde aktualisiert.');
            }
        }
    }
    header('Location: konten.php');
    exit;
}

$mitgliederListe = $pdo->query('SELECT * FROM mitglieder ORDER BY nachname, vorname')->fetchAll();
$flash = takeFlash();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Konten &ndash; Admin &ndash; <?= e(APP_NAME) ?></title>
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
    $seitenUntertitel = 'Admin';
    $aktivReiter = 'admin';
    $zurueck = '../home.php';
    require __DIR__ . '/../../../includes/kopf.php';
    ?>

    <main class="container" style="max-width:1040px;">
        <nav class="subnav">
            <a href="konten.php" class="active">Konten</a>
            <a href="bilder.php">Bilder</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Konten</h2>
            <p class="text-muted">Passwort zurücksetzen, Konten löschen und Admin-Rechte vergeben. Rolle und Aktiv/Inaktiv-Status verwaltest du weiterhin in der Geschäftsstelle unter Mitgliederverwaltung.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
            <?php endif; ?>

            <?php if (empty($mitgliederListe)): ?>
                <p>Noch keine Mitglieder angelegt.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>E-Mail</th>
                            <th>Rolle</th>
                            <th>Admin</th>
                            <th>Passwort</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mitgliederListe as $m): ?>
                            <tr>
                                <td><?= e($m['vorname'] . ' ' . $m['nachname']) ?></td>
                                <td><?= e($m['email']) ?></td>
                                <td><?= e(rollenLabel($m['rolle'])) ?></td>
                                <td>
                                    <?php if ((int) $m['id'] === (int) $mitglied['id']): ?>
                                        <span class="badge badge-angenommen">du</span>
                                    <?php else: ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                            <input type="hidden" name="aktion" value="admin_umschalten">
                                            <button type="submit" class="btn btn-secondary"><?= $m['ist_admin'] ? 'Admin entziehen' : 'Zum Admin machen' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td><?= $m['passwort_hash'] !== null ? 'gesetzt' : '<em>nicht gesetzt</em>' ?></td>
                                <td style="white-space:nowrap;">
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                        <input type="hidden" name="aktion" value="passwort_vergeben">
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Passwort für <?= e($m['vorname']) ?> zurücksetzen? Das alte Passwort wird ungültig.');">Passwort zurücksetzen</button>
                                    </form>
                                    <?php if ((int) $m['id'] !== (int) $mitglied['id']): ?>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                            <input type="hidden" name="aktion" value="loeschen">
                                            <button type="submit" class="btn btn-secondary" onclick="return confirm('<?= e($m['vorname'] . ' ' . $m['nachname']) ?> wirklich endgültig löschen? Das kann nicht rückgängig gemacht werden.');">Löschen</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="../../assets/js/menue.js" defer></script>
</body>
</html>
