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
        $antragId = (int) $_POST['id'];
        $stmt = $pdo->prepare('SELECT * FROM antraege WHERE id = :id');
        $stmt->execute(['id' => $antragId]);
        $antrag = $stmt->fetch();

        if ($antrag) {
            if ($_POST['aktion'] === 'annehmen') {
                if ($antrag['mitglied_id'] === null) {
                    $insert = $pdo->prepare(
                        'INSERT INTO mitglieder
                            (antrag_id, vorname, nachname, geburtsdatum, geburtsort, strasse_hausnummer, plz, ort, telefon, email, instagram, foto_dateiname, rolle, aktiv)
                         VALUES
                            (:antrag_id, :vorname, :nachname, :geburtsdatum, :geburtsort, :strasse_hausnummer, :plz, :ort, :telefon, :email, :instagram, :foto_dateiname, "vollmitglied", 1)'
                    );
                    $insert->execute([
                        'antrag_id' => $antrag['id'],
                        'vorname' => $antrag['vorname'],
                        'nachname' => $antrag['nachname'],
                        'geburtsdatum' => $antrag['geburtsdatum'],
                        'geburtsort' => $antrag['geburtsort'],
                        'strasse_hausnummer' => $antrag['strasse_hausnummer'],
                        'plz' => $antrag['plz'],
                        'ort' => $antrag['ort'],
                        'telefon' => $antrag['telefon'],
                        'email' => $antrag['email'],
                        'instagram' => $antrag['instagram'],
                        'foto_dateiname' => $antrag['foto_dateiname'],
                    ]);
                    $mitgliedId = (int) $pdo->lastInsertId();

                    $update = $pdo->prepare('UPDATE antraege SET status = "angenommen", mitglied_id = :mitglied_id WHERE id = :id');
                    $update->execute(['mitglied_id' => $mitgliedId, 'id' => $antragId]);

                    setFlash('success', 'Antrag angenommen und Mitgliedskonto angelegt. Bitte in der Mitgliederverwaltung ein Passwort vergeben.');
                } else {
                    $pdo->prepare('UPDATE antraege SET status = "angenommen" WHERE id = :id')->execute(['id' => $antragId]);
                }
            } elseif ($_POST['aktion'] === 'ablehnen') {
                $pdo->prepare('UPDATE antraege SET status = "abgelehnt" WHERE id = :id')->execute(['id' => $antragId]);
            } elseif ($_POST['aktion'] === 'zuruecksetzen') {
                $pdo->prepare('UPDATE antraege SET status = "neu" WHERE id = :id')->execute(['id' => $antragId]);
            }
        }
    }
    header('Location: antraege.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$filter = $_GET['status'] ?? 'neu';
$gueltigeFilter = ['alle', 'neu', 'angenommen', 'abgelehnt'];
if (!in_array($filter, $gueltigeFilter, true)) {
    $filter = 'neu';
}

if ($filter === 'alle') {
    $stmt = $pdo->query('SELECT * FROM antraege ORDER BY erstellt_am DESC');
} else {
    $stmt = $pdo->prepare('SELECT * FROM antraege WHERE status = :status ORDER BY erstellt_am DESC');
    $stmt->execute(['status' => $filter]);
}
$antraege = $stmt->fetchAll();
$flash = takeFlash();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anträge &ndash; Vorstand &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
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

    <main class="container" style="max-width:960px;">
        <nav class="tabs">
            <a href="../index.php">Meine Daten</a>
            <a href="antraege.php" class="active">Vorstand</a>
        </nav>

        <nav class="subnav">
            <a href="antraege.php" class="active">Aufnahmeanträge</a>
            <a href="mitglieder.php">Mitgliederverwaltung</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Aufnahmeanträge</h2>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
            <?php endif; ?>

            <div style="margin:16px 0;">
                <?php foreach ($gueltigeFilter as $f): ?>
                    <a href="?status=<?= e($f) ?>" class="btn btn-secondary" style="<?= $filter === $f ? 'font-weight:700;' : '' ?>"><?= e(ucfirst($f)) ?></a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($antraege)): ?>
                <p>Keine Anträge in dieser Ansicht.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>E-Mail</th>
                            <th>Eingegangen</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($antraege as $antrag): ?>
                            <tr>
                                <td><?= e($antrag['vorname'] . ' ' . $antrag['nachname']) ?></td>
                                <td><?= e($antrag['email']) ?></td>
                                <td><?= e((new DateTime($antrag['erstellt_am']))->format('d.m.Y H:i')) ?></td>
                                <td><span class="badge badge-<?= e($antrag['status']) ?>"><?= e($antrag['status']) ?></span></td>
                                <td><a href="antrag_ansehen.php?id=<?= (int) $antrag['id'] ?>">Details</a></td>
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
