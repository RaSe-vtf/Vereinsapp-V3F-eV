<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

$mitglied = requireVorstand('../../login.php', '../index.php');
$pdo = getPdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aktion'], $_POST['id'])) {
    if (checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $zielId = (int) $_POST['id'];
        $rolleInput = $_POST['rolle'] ?? null;

        if ($_POST['aktion'] === 'rolle_aendern' && $rolleInput !== null && array_key_exists($rolleInput, ROLLEN_LABELS)) {
            if ($zielId === (int) $mitglied['id'] && $rolleInput !== 'vorstandsmitglied') {
                setFlash('error', 'Du kannst dir nicht selbst die Rolle Vorstandsmitglied entziehen. Bitte ein anderes Vorstandsmitglied bitten.');
            } else {
                $pdo->prepare('UPDATE mitglieder SET rolle = :rolle WHERE id = :id')
                    ->execute(['rolle' => $rolleInput, 'id' => $zielId]);
                setFlash('success', 'Rolle wurde aktualisiert.');
            }
        } elseif ($_POST['aktion'] === 'aktiv_umschalten') {
            if ($zielId === (int) $mitglied['id']) {
                setFlash('error', 'Du kannst dein eigenes Konto nicht deaktivieren.');
            } else {
                $pdo->prepare('UPDATE mitglieder SET aktiv = NOT aktiv WHERE id = :id')->execute(['id' => $zielId]);
                $nochAktiv = $pdo->prepare('SELECT aktiv FROM mitglieder WHERE id = :id');
                $nochAktiv->execute(['id' => $zielId]);
                if ((int) $nochAktiv->fetchColumn() === 0) {
                    loescheAlleRememberTokens($zielId);
                }
                setFlash('success', 'Status wurde aktualisiert.');
            }
        } elseif ($_POST['aktion'] === 'passwort_vergeben') {
            $neuesPasswort = generateInitialPasswort();
            $pdo->prepare('UPDATE mitglieder SET passwort_hash = :hash WHERE id = :id')
                ->execute(['hash' => password_hash($neuesPasswort, PASSWORD_DEFAULT), 'id' => $zielId]);
            loescheAlleRememberTokens($zielId);
            setFlash('success', 'Neues Passwort für dieses Mitglied: "' . $neuesPasswort . '" — bitte sicher übermitteln, es wird nur einmal angezeigt.');
        } elseif ($_POST['aktion'] === 'loeschen') {
            if ($zielId === (int) $mitglied['id']) {
                setFlash('error', 'Du kannst dein eigenes Konto nicht löschen. Bitte ein anderes Vorstandsmitglied bitten.');
            } else {
                $pdo->prepare('DELETE FROM mitglieder WHERE id = :id')->execute(['id' => $zielId]);
                setFlash('success', 'Mitglied wurde gelöscht.');
            }
        }
    }
    header('Location: mitglieder.php');
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
    <title>Mitgliederverwaltung &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png">
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#1f7a8c">
</head>
<body>
    <header class="top-header">
        <div class="top-header__inner">
            <img class="top-header__logo" src="../../assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <div>
                <div class="top-header__title"><?= e(APP_NAME) ?></div>
                <div class="top-header__subtitle"><?= e($mitglied['vorname'] . ' ' . $mitglied['nachname']) ?> &middot; Vorstand</div>
            </div>
            <div style="margin-left:auto;">
                <a href="../../logout.php" class="btn btn-secondary">Abmelden</a>
            </div>
        </div>
    </header>

    <main class="container" style="max-width:1040px;">
        <nav class="tabs">
            <a href="../index.php">Meine Daten</a>
            <a href="antraege.php" class="active">Vorstand</a>
        </nav>

        <nav class="subnav">
            <a href="antraege.php">Aufnahmeanträge</a>
            <a href="mitglieder.php" class="active">Mitgliederverwaltung</a>
            <a href="verteiler.php">E-Mail-Verteiler</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Mitgliederverwaltung</h2>

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
                            <th>Status</th>
                            <th>Passwort</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mitgliederListe as $m): ?>
                            <tr>
                                <td><a href="mitglied_ansehen.php?id=<?= (int) $m['id'] ?>"><?= e($m['vorname'] . ' ' . $m['nachname']) ?></a></td>
                                <td><?= e($m['email']) ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                        <input type="hidden" name="aktion" value="rolle_aendern">
                                        <select name="rolle" class="role-select" onchange="this.form.submit()">
                                            <?php foreach (ROLLEN_LABELS as $wert => $label): ?>
                                                <option value="<?= e($wert) ?>" <?= $m['rolle'] === $wert ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td><?= $m['aktiv'] ? '<span class="badge badge-angenommen">aktiv</span>' : '<span class="badge badge-abgelehnt">inaktiv</span>' ?></td>
                                <td><?= $m['passwort_hash'] !== null ? 'gesetzt' : '<em>nicht gesetzt</em>' ?></td>
                                <td style="white-space:nowrap;">
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                        <input type="hidden" name="aktion" value="passwort_vergeben">
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Passwort für <?= e($m['vorname']) ?> zurücksetzen? Das alte Passwort wird ungültig.');">Passwort zurücksetzen</button>
                                    </form>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                        <input type="hidden" name="aktion" value="aktiv_umschalten">
                                        <button type="submit" class="btn btn-secondary"><?= $m['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
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
</body>
</html>
