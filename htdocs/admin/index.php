<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdminLogin();

$pdo = getPdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aktion'], $_POST['id'])) {
    if (checkCsrfToken($_POST['csrf_token'] ?? null)) {
        $neuerStatus = match ($_POST['aktion']) {
            'annehmen' => 'angenommen',
            'ablehnen' => 'abgelehnt',
            'zuruecksetzen' => 'neu',
            default => null,
        };
        if ($neuerStatus !== null) {
            $stmt = $pdo->prepare('UPDATE antraege SET status = :status WHERE id = :id');
            $stmt->execute(['status' => $neuerStatus, 'id' => (int) $_POST['id']]);
        }
    }
    header('Location: index.php');
    exit;
}

$filter = $_GET['status'] ?? 'alle';
$gueltigeFilter = ['alle', 'neu', 'angenommen', 'abgelehnt'];
if (!in_array($filter, $gueltigeFilter, true)) {
    $filter = 'alle';
}

if ($filter === 'alle') {
    $stmt = $pdo->query('SELECT * FROM antraege ORDER BY erstellt_am DESC');
} else {
    $stmt = $pdo->prepare('SELECT * FROM antraege WHERE status = :status ORDER BY erstellt_am DESC');
    $stmt->execute(['status' => $filter]);
}
$antraege = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aufnahmeanträge &ndash; <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="top-header">
        <div class="top-header__inner">
            <img class="top-header__logo" src="../assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
            <div>
                <div class="top-header__title"><?= e(APP_NAME) ?></div>
                <div class="top-header__subtitle">Vorstandsbereich</div>
            </div>
        </div>
    </header>

    <main class="container" style="max-width:960px;">
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <h2 style="margin:0;">Aufnahmeanträge</h2>
                <a href="logout.php" class="btn btn-secondary">Abmelden</a>
            </div>

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
                                <td><a href="view.php?id=<?= (int) $antrag['id'] ?>">Details</a></td>
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
