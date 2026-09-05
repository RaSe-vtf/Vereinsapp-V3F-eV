<?php
declare(strict_types=1);

/**
 * Gibt das eingeloggte, aktive Mitglied zurueck oder null.
 * Wird pro Request frisch aus der DB geladen, damit eine Rollenaenderung
 * oder Deaktivierung durch den Vorstand sofort wirkt.
 */
function currentMitglied(): ?array
{
    static $cached = false;

    if ($cached !== false) {
        return $cached;
    }

    if (empty($_SESSION['mitglied_id'])) {
        $cached = null;
        return null;
    }

    $stmt = getPdo()->prepare('SELECT * FROM mitglieder WHERE id = :id AND aktiv = 1');
    $stmt->execute(['id' => $_SESSION['mitglied_id']]);
    $mitglied = $stmt->fetch();

    $cached = $mitglied !== false ? $mitglied : null;
    return $cached;
}

/**
 * Erzwingt ein eingeloggtes Mitglied, sonst Weiterleitung zum Login.
 * $loginPfad ist relativ zur aufrufenden Datei (unterschiedliche Verzeichnistiefe).
 */
function requireMemberLogin(string $loginPfad = 'login.php'): array
{
    $mitglied = currentMitglied();
    if ($mitglied === null) {
        header('Location: ' . $loginPfad);
        exit;
    }
    return $mitglied;
}

/**
 * Erzwingt die Rolle Vorstandsmitglied, sonst Weiterleitung zum eigenen Bereich.
 */
function requireVorstand(string $loginPfad = 'login.php', string $bereichPfad = 'index.php'): array
{
    $mitglied = requireMemberLogin($loginPfad);
    if ($mitglied['rolle'] !== 'vorstandsmitglied') {
        header('Location: ' . $bereichPfad);
        exit;
    }
    return $mitglied;
}
