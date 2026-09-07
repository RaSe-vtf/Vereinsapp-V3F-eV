<?php
/**
 * Gemeinsamer Kopfbereich fuer den eingeloggten Mitgliederbereich: Logo als
 * Home-Button, Menü-Symbol im Banner (mit den Reitern + Abmelden) und ein
 * kleiner Zurück-Pfeil unterhalb des Banners zur naechst hoeheren Seite.
 *
 * Erwartet vor dem Einbinden gesetzt:
 *   $mitglied         array, eingeloggtes Mitglied
 *   $tiefe             string, '' innerhalb von bereich/, '../' innerhalb von bereich/vorstand/ bzw. bereich/admin/
 *   $seitenUntertitel string, Untertitel im Banner (z.B. "Geschäftsstelle")
 *   $aktivReiter       string|null, 'meine-daten'|'geschaeftsstelle'|'admin'|null
 *   $zurueck           string|null, relativer Link fuer den Zurück-Pfeil, null = kein Pfeil
 */
?>
<header class="top-header">
    <div class="top-header__inner">
        <a href="<?= e($tiefe) ?>home.php" class="top-header__logo-link" aria-label="Startseite">
            <img class="top-header__logo" src="<?= e($tiefe) ?>../assets/img/logo.jpg" alt="Logo <?= e(VEREIN_NAME) ?>">
        </a>
        <div>
            <div class="top-header__title"><?= e(APP_NAME) ?></div>
            <div class="top-header__subtitle"><?= e($mitglied['vorname'] . ' ' . $mitglied['nachname']) ?> &middot; <?= e($seitenUntertitel) ?></div>
        </div>
        <div class="menu-wrapper">
            <button type="button" class="menu-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="hauptmenue">&#9776;</button>
            <nav class="menu-dropdown" id="hauptmenue" hidden>
                <a href="<?= e($tiefe) ?>index.php" class="<?= $aktivReiter === 'meine-daten' ? 'active' : '' ?>">Meine Daten</a>
                <?php if ($mitglied['rolle'] === 'vorstandsmitglied'): ?>
                    <a href="<?= e($tiefe) ?>vorstand/antraege.php" class="<?= $aktivReiter === 'geschaeftsstelle' ? 'active' : '' ?>">Geschäftsstelle</a>
                <?php endif; ?>
                <?php if (!empty($mitglied['ist_admin'])): ?>
                    <a href="<?= e($tiefe) ?>admin/konten.php" class="<?= $aktivReiter === 'admin' ? 'active' : '' ?>">Admin</a>
                <?php endif; ?>
                <hr>
                <a href="<?= e($tiefe) ?>../logout.php" class="menu-logout">Abmelden</a>
            </nav>
        </div>
    </div>
</header>
<?php if ($zurueck !== null): ?>
    <div class="container zurueck-zeile"><a class="zurueck-link" href="<?= e($zurueck) ?>">&larr; Zurück</a></div>
<?php endif; ?>
