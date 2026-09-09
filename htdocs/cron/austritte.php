<?php
declare(strict_types=1);

/**
 * Von einem Cronjob (z.B. im all-inkl KAS-Panel als periodischer URL-Aufruf
 * eingerichtet) taeglich aufzurufen: verarbeitet faellige Mitglieder-
 * Austritte tagesgenau, unabhaengig davon, ob jemand die App gerade nutzt.
 * Durch CRON_SECRET (private/config.php) vor fremdem Aufruf geschuetzt, da
 * diese Datei oeffentlich per URL erreichbar sein muss (kein SSH/CLI-Cron
 * auf diesem Hosting verfuegbar). Kein Login noetig - laeuft ausserhalb
 * jeder Mitglieder-Session.
 *
 * Aufruf: https://deine-domain.de/cron/austritte.php?token=DEIN_GEHEIMWORT
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!defined('CRON_SECRET') || CRON_SECRET === 'HIER_EIGENES_GEHEIMWORT_EINTRAGEN') {
    http_response_code(500);
    echo "CRON_SECRET ist in private/config.php nicht gesetzt.\n";
    exit;
}

$token = $_GET['token'] ?? '';
if (!is_string($token) || !hash_equals(CRON_SECRET, $token)) {
    http_response_code(403);
    echo "Ungueltiges Token.\n";
    exit;
}

$anzahl = verarbeiteFaelligeAustritte(getPdo());
echo "OK: $anzahl Mitglied(er) automatisch ausgetreten.\n";
