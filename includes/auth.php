<?php
declare(strict_types=1);

const REMEMBER_COOKIE = 'vereinsapp_anmeldung';

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
        versucheAutoLoginPerToken();
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

/**
 * Erzwingt das Admin-Flag, sonst Weiterleitung zum eigenen Bereich. Das
 * Admin-Flag ist unabhaengig von der Rolle (z.B. Vorstandsmitglied UND
 * Admin gleichzeitig).
 */
function requireAdmin(string $loginPfad = 'login.php', string $bereichPfad = 'index.php'): array
{
    $mitglied = requireMemberLogin($loginPfad);
    if (empty($mitglied['ist_admin'])) {
        header('Location: ' . $bereichPfad);
        exit;
    }
    return $mitglied;
}

/**
 * "Angemeldet bleiben": legt fuer das Mitglied ein neues Auto-Login-Token an
 * und hinterlegt es als langlebiges Cookie im Browser. Wird nach jedem
 * erfolgreichen Passwort-Login aufgerufen.
 */
function issueRememberToken(int $mitgliedId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));

    getPdo()->prepare('INSERT INTO anmelde_tokens (mitglied_id, selector, validator_hash) VALUES (:mitglied_id, :selector, :hash)')
        ->execute([
            'mitglied_id' => $mitgliedId,
            'selector' => $selector,
            'hash' => hash('sha256', $validator),
        ]);

    setzeRememberCookie($selector, $validator);
}

/**
 * Prueft, ob ein gueltiges Auto-Login-Cookie vorliegt, und loggt bei Erfolg
 * ein (setzt $_SESSION['mitglied_id']). Rotiert das Token bei jeder
 * Verwendung, damit ein einzelner abgefangener Cookie-Wert nur einmal
 * unbemerkt wiederverwendet werden kann.
 */
function versucheAutoLoginPerToken(): void
{
    if (empty($_COOKIE[REMEMBER_COOKIE])) {
        return;
    }

    $teile = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    if (count($teile) !== 2) {
        loescheRememberCookie();
        return;
    }
    [$selector, $validator] = $teile;

    $stmt = getPdo()->prepare('SELECT * FROM anmelde_tokens WHERE selector = :selector');
    $stmt->execute(['selector' => $selector]);
    $token = $stmt->fetch();

    if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
        loescheRememberCookie();
        return;
    }

    $mitgliedStmt = getPdo()->prepare('SELECT id FROM mitglieder WHERE id = :id AND aktiv = 1');
    $mitgliedStmt->execute(['id' => $token['mitglied_id']]);
    if (!$mitgliedStmt->fetch()) {
        // Konto deaktiviert oder geloescht: verwaistes Token aufraeumen.
        getPdo()->prepare('DELETE FROM anmelde_tokens WHERE id = :id')->execute(['id' => $token['id']]);
        loescheRememberCookie();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['mitglied_id'] = (int) $token['mitglied_id'];

    $neuerValidator = bin2hex(random_bytes(32));
    getPdo()->prepare('UPDATE anmelde_tokens SET validator_hash = :hash, zuletzt_verwendet_am = NOW() WHERE id = :id')
        ->execute(['hash' => hash('sha256', $neuerValidator), 'id' => $token['id']]);
    setzeRememberCookie($selector, $neuerValidator);
}

/**
 * Loescht das Auto-Login-Token, das zum aktuellen Cookie gehoert (falls
 * vorhanden), sowie das Cookie selbst. Wird beim expliziten Abmelden
 * aufgerufen - sonst waere "Abmelden" wirkungslos.
 */
function loescheRememberTokenUndCookie(): void
{
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $teile = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
        if (count($teile) === 2) {
            getPdo()->prepare('DELETE FROM anmelde_tokens WHERE selector = :selector')->execute(['selector' => $teile[0]]);
        }
    }
    loescheRememberCookie();
}

/**
 * Loescht alle Auto-Login-Tokens eines Mitglieds bis auf das ggf. aktuell
 * verwendete. Wird bei Passwort-Aenderung/-Reset aufgerufen, damit ein
 * gestohlenes Passwort nicht durch ein weiterhin gueltiges Auto-Login-Cookie
 * auf einem anderen Geraet nutzlos wird.
 */
function invalidiereAndereRememberTokens(int $mitgliedId): void
{
    $aktuellerSelector = null;
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $teile = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
        if (count($teile) === 2) {
            $aktuellerSelector = $teile[0];
        }
    }

    if ($aktuellerSelector !== null) {
        getPdo()->prepare('DELETE FROM anmelde_tokens WHERE mitglied_id = :id AND selector <> :selector')
            ->execute(['id' => $mitgliedId, 'selector' => $aktuellerSelector]);
    } else {
        loescheAlleRememberTokens($mitgliedId);
    }
}

/**
 * Loescht ausnahmslos alle Auto-Login-Tokens eines Mitglieds. Wird genutzt,
 * wenn der Vorstand ein Konto deaktiviert oder ein neues Passwort vergibt
 * (dann soll kein Geraet mehr automatisch eingeloggt bleiben).
 */
function loescheAlleRememberTokens(int $mitgliedId): void
{
    getPdo()->prepare('DELETE FROM anmelde_tokens WHERE mitglied_id = :id')->execute(['id' => $mitgliedId]);
}

function setzeRememberCookie(string $selector, string $validator): void
{
    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires' => time() + 10 * 365 * 24 * 60 * 60, // ~10 Jahre, praktisch "nie"
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function loescheRememberCookie(): void
{
    unset($_COOKIE[REMEMBER_COOKIE]);
    setcookie(REMEMBER_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
