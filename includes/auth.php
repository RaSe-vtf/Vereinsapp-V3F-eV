<?php
declare(strict_types=1);

/**
 * Einfacher Passwortschutz fuer den Vorstandsbereich (Admin).
 * Ein volles Mitglieder-Login-System folgt in einer spaeteren Ausbaustufe.
 */
function requireAdminLogin(): void
{
    if (empty($_SESSION['admin_eingeloggt'])) {
        header('Location: login.php');
        exit;
    }
}
