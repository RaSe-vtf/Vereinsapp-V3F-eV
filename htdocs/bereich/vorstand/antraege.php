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
                    // Normalfall: Antragsteller hat beim Aufnahmeantrag bereits ein
                    // eigenes Passwort gewaehlt, das direkt uebernommen wird. Nur bei
                    // Altantraegen ohne gespeichertes Passwort (vor Einfuehrung dieser
                    // Funktion) wird ersatzweise eines generiert und angezeigt.
                    $generiertesPasswort = null;
                    $passwortHash = $antrag['passwort_hash'];
                    if ($passwortHash === null) {
                        $generiertesPasswort = generateInitialPasswort();
                        $passwortHash = password_hash($generiertesPasswort, PASSWORD_DEFAULT);
                    }

                    $insert = $pdo->prepare(
                        'INSERT INTO mitglieder
                            (antrag_id, vorname, nachname, geburtsdatum, geburtsort, strasse_hausnummer, plz, ort, telefon, email, instagram, foto_dateiname, shirt_groesse, portraet, rolle, passwort_hash, aktiv)
                         VALUES
                            (:antrag_id, :vorname, :nachname, :geburtsdatum, :geburtsort, :strasse_hausnummer, :plz, :ort, :telefon, :email, :instagram, :foto_dateiname, :shirt_groesse, :portraet, "vollmitglied", :passwort_hash, 1)'
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
                        'shirt_groesse' => $antrag['shirt_groesse'],
                        'portraet' => $antrag['portraet'],
                        'passwort_hash' => $passwortHash,
                    ]);
                    $mitgliedId = (int) $pdo->lastInsertId();

                    $update = $pdo->prepare('UPDATE antraege SET status = "angenommen", mitglied_id = :mitglied_id WHERE id = :id');
                    $update->execute(['mitglied_id' => $mitgliedId, 'id' => $antragId]);

                    sendeEinzelMail(
                        $antrag['email'],
                        'Willkommen bei ' . VEREIN_NAME . '!',
                        "Herzlich willkommen bei " . VEREIN_NAME . "!\n\n"
                        . "Wir freuen uns, dich als neues Mitglied im Verein begrüßen zu dürfen.\n\n"
                        . "Du kannst dich ab sofort mit deiner E-Mail-Adresse und dem beim Aufnahmeantrag von dir gewählten Passwort im Mitgliederbereich einloggen.\n\n"
                        . "Mit sportlichen Grüßen\n"
                        . "Dein Team " . VEREIN_NAME
                    );

                    if ($generiertesPasswort !== null) {
                        setFlash('success', 'Antrag angenommen und Mitgliedskonto angelegt. Dieser Antrag hatte noch kein eigenes Passwort hinterlegt, daher wurde eines generiert: "' . $generiertesPasswort . '" — bitte sicher übermitteln, es wird nur einmal angezeigt.');
                    } else {
                        setFlash('success', 'Antrag angenommen und Mitgliedskonto angelegt. Das Mitglied kann sich mit dem beim Aufnahmeantrag selbst gewählten Passwort einloggen.');
                    }
                } else {
                    $pdo->prepare('UPDATE antraege SET status = "angenommen" WHERE id = :id')->execute(['id' => $antragId]);
                }
            } elseif ($_POST['aktion'] === 'ablehnen') {
                $pdo->prepare('UPDATE antraege SET status = "abgelehnt" WHERE id = :id')->execute(['id' => $antragId]);

                sendeEinzelMail(
                    $antrag['email'],
                    'Ihr Aufnahmeantrag bei ' . VEREIN_NAME,
                    "Sehr geehrte Damen und Herren,\n\n"
                    . "leider müssen wir Ihnen mitteilen, dass wir Ihren Antrag vorstandsseitig abgelehnt haben.\n\n"
                    . "Wir wünschen Ihnen weiterhin alles Gute.\n\n"
                    . "Mit sportlichen Grüßen\n"
                    . "Der Vorstand " . VEREIN_NAME
                );
            } elseif ($_POST['aktion'] === 'zuruecksetzen') {
                $pdo->prepare('UPDATE antraege SET status = "neu" WHERE id = :id')->execute(['id' => $antragId]);
            }
        }
    }
    header('Location: antraege.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
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
$flash = takeFlash();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anträge &ndash; Geschäftsstelle &ndash; <?= e(APP_NAME) ?></title>
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

    <main class="container" style="max-width:960px;">
        <nav class="subnav">
            <a href="antraege.php" class="active">Aufnahmeanträge</a>
            <a href="mitglieder.php">Mitgliederverwaltung</a>
            <a href="verteiler.php">E-Mail-Verteiler</a>
            <a href="kassenwart/index.php">Kassenwart</a>
            <a href="vereinsdokumente.php">Vereinsdokumente</a>
        </nav>

        <div class="card">
            <h2 style="margin-top:0;">Aufnahmeanträge</h2>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['typ']) ?>"><?= e($flash['text']) ?></div>
            <?php endif; ?>

            <div style="margin:10px 0 16px;">
                <?php foreach ($gueltigeFilter as $f): ?>
                    <a href="?status=<?= e($f) ?>" class="filter-pill<?= $filter === $f ? ' active' : '' ?>"><?= e(ucfirst($f)) ?></a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($antraege)): ?>
                <p>Keine Anträge in dieser Ansicht.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="tabelle-einzeilig">
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
                                <td><a href="antrag_ansehen.php?id=<?= (int) $antrag['id'] ?>">Details ansehen &rarr;</a></td>
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
