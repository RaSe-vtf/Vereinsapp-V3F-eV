<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/auth.php';

$mitglied = requireVorstand('../../../login.php', '../../index.php');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beitragsrechner &ndash; Kassenwart &ndash; <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="../../../assets/img/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="../../../assets/img/favicon-16.png">
<link rel="apple-touch-icon" href="../../../assets/img/apple-touch-icon.png">
<link rel="manifest" href="../../../manifest.json">
<meta name="theme-color" content="#1f7a8c">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap">
<style>
  :root {
    --ink: #16213E;
    --ink-soft: #4C5A78;
    --surface: #F4F8FB;
    --card: #FFFFFF;
    --card-border: #DCE6EE;
    --primary: #075A82;
    --primary-ink: #ffffff;
    --teal: #1C7293;
    --navy: #10182B;
    --amber: #C97B12;
    --amber-bg: #FBF0DE;
    --good: #1F7A50;
    --good-bg: #E6F5EC;
    --warn: #B4650C;
    --warn-bg: #FCEEDA;
    --bad: #A23131;
    --bad-bg: #FBE9E9;
    --track: #E3ECF3;
    --focus: #0B6FA0;
    color-scheme: light;
  }

  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
      --ink: #E9EFF6;
      --ink-soft: #A9B7CC;
      --surface: #0E1526;
      --card: #161F35;
      --card-border: #2A3752;
      --primary: #3A9BCB;
      --primary-ink: #071018;
      --teal: #4FB3C9;
      --navy: #0A0F1E;
      --amber: #E6A94A;
      --amber-bg: #2E2410;
      --good: #4FBE8A;
      --good-bg: #10281D;
      --warn: #E6A94A;
      --warn-bg: #2E2410;
      --bad: #E27070;
      --bad-bg: #2E1414;
      --track: #24304A;
      --focus: #6FC4EE;
      color-scheme: dark;
    }
  }
  :root[data-theme="dark"] {
    --ink: #E9EFF6;
    --ink-soft: #A9B7CC;
    --surface: #0E1526;
    --card: #161F35;
    --card-border: #2A3752;
    --primary: #3A9BCB;
    --primary-ink: #071018;
    --teal: #4FB3C9;
    --navy: #0A0F1E;
    --amber: #E6A94A;
    --amber-bg: #2E2410;
    --good: #4FBE8A;
    --good-bg: #10281D;
    --warn: #E6A94A;
    --warn-bg: #2E2410;
    --bad: #E27070;
    --bad-bg: #2E1414;
    --track: #24304A;
    --focus: #6FC4EE;
    color-scheme: dark;
  }

  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  [hidden] { display: none !important; }
  body {
    background: var(--surface);
    color: var(--ink);
    font-family: "IBM Plex Sans", "Segoe UI", Arial, sans-serif;
    line-height: 1.5;
    padding: 2.5rem 1.25rem 4rem;
  }

  .page {
    max-width: 1180px;
    margin: 0 auto;
  }

  .zurueck {
    display: inline-block;
    margin-bottom: 1.5rem;
    font-family: "IBM Plex Sans", "Segoe UI", Arial, sans-serif;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
  }
  .zurueck:hover { text-decoration: underline; }

  header.hero {
    margin-bottom: 2.25rem;
  }
  .eyebrow {
    font-family: "IBM Plex Mono", monospace;
    font-size: 0.72rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--teal);
    margin: 0 0 0.6rem;
  }
  h1 {
    font-family: "Fraunces", Georgia, serif;
    font-weight: 700;
    font-size: clamp(1.9rem, 4vw, 2.6rem);
    margin: 0 0 0.5rem;
    text-wrap: balance;
    color: var(--ink);
  }
  .lede {
    color: var(--ink-soft);
    max-width: 62ch;
    font-size: 1.02rem;
    margin: 0;
  }

  .input-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    align-items: start;
    margin-bottom: 1.5rem;
  }

  .card {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: 14px;
    padding: 1.5rem;
  }

  .card h2 {
    font-family: "Fraunces", Georgia, serif;
    font-size: 1.15rem;
    font-weight: 600;
    margin: 0 0 0.3rem;
  }
  .card .hint {
    color: var(--ink-soft);
    font-size: 0.85rem;
    margin: 0 0 1.3rem;
  }

  .field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-bottom: 1.35rem;
  }
  .field:last-child { margin-bottom: 0; }
  .field label {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--ink);
  }
  .field .sub {
    font-size: 0.78rem;
    color: var(--ink-soft);
    font-weight: 400;
  }

  .stepper {
    display: grid;
    grid-template-columns: 2.5rem 1fr 2.5rem;
    align-items: stretch;
    border: 1px solid var(--card-border);
    border-radius: 10px;
    overflow: hidden;
    background: var(--surface);
  }
  .stepper button {
    border: none;
    background: transparent;
    color: var(--primary);
    font-family: "IBM Plex Mono", monospace;
    font-size: 1.25rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .stepper button:hover { background: var(--track); }
  .stepper button:focus-visible, input:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: -2px;
  }
  .stepper input {
    border: none;
    background: transparent;
    text-align: center;
    font-family: "IBM Plex Mono", monospace;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--ink);
    -webkit-text-fill-color: var(--ink);
    opacity: 1;
    padding: 0.55rem 0.25rem;
    width: 100%;
    font-variant-numeric: tabular-nums;
  }
  .stepper input::-webkit-outer-spin-button,
  .stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
  .stepper input[type=number] { -moz-appearance: textfield; }

  .total-line {
    margin-top: 1.4rem;
    padding-top: 1.1rem;
    border-top: 1px dashed var(--card-border);
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 0.9rem;
    color: var(--ink-soft);
  }
  .total-line strong {
    font-family: "IBM Plex Mono", monospace;
    color: var(--ink);
    font-size: 1.05rem;
    font-variant-numeric: tabular-nums;
  }

  .assumption {
    margin-top: 1.1rem;
    font-size: 0.78rem;
    color: var(--ink-soft);
    line-height: 1.45;
  }

  .checkbox-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--ink);
    cursor: pointer;
  }
  .checkbox-row input[type="checkbox"] {
    width: 1.15rem;
    height: 1.15rem;
    accent-color: var(--primary);
    cursor: pointer;
  }

  .cost-display {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
  }
  .cost-value {
    font-family: "IBM Plex Mono", monospace;
    font-weight: 600;
    font-size: 1.05rem;
    color: var(--ink);
    font-variant-numeric: tabular-nums;
  }
  .btn-edit, .btn-confirm, .btn-cancel {
    font-family: "IBM Plex Sans", "Segoe UI", Arial, sans-serif;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.3rem 0.45rem;
    border-radius: 8px;
    border: 1px solid var(--card-border);
    background: var(--surface);
    color: var(--primary);
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
  }
  .btn-edit:hover, .btn-cancel:hover { background: var(--track); }
  .btn-confirm {
    background: var(--primary);
    color: var(--primary-ink);
    border-color: var(--primary);
  }
  .btn-confirm:hover { opacity: 0.9; }
  .btn-edit:focus-visible, .btn-confirm:focus-visible, .btn-cancel:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }

  .cost-edit-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
  }
  .cost-edit-row input {
    flex: 1;
    min-width: 0;
    border: 1px solid var(--card-border);
    border-radius: 8px;
    background: var(--surface);
    background-color: var(--surface);
    font-family: "IBM Plex Mono", monospace;
    font-size: 1rem;
    font-weight: 600;
    color: var(--ink);
    -webkit-text-fill-color: var(--ink);
    opacity: 1;
    padding: 0.5rem 0.6rem;
    font-variant-numeric: tabular-nums;
  }
  .cost-edit-row input::-webkit-outer-spin-button,
  .cost-edit-row input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
  .cost-edit-row input[type=number] { -moz-appearance: textfield; }
  .cost-edit-row input:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: -2px;
  }

  .field-note {
    font-size: 0.76rem;
    color: var(--warn);
    margin: 0.4rem 0 0;
    min-height: 1em;
    line-height: 1.4;
  }

  .results { display: flex; flex-direction: column; gap: 1.5rem; }

  .status-row {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    flex-wrap: wrap;
  }
  .pill {
    font-family: "IBM Plex Mono", monospace;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    text-transform: uppercase;
  }
  .pill.good { background: var(--good-bg); color: var(--good); }
  .pill.warn { background: var(--warn-bg); color: var(--warn); }
  .pill.bad { background: var(--bad-bg); color: var(--bad); }

  .stat-strip {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
  }
  @media (max-width: 560px) {
    .stat-strip { grid-template-columns: 1fr; }
  }
  .stat {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: 14px;
    padding: 1.1rem 1.2rem;
  }
  .stat .label {
    font-size: 0.78rem;
    color: var(--ink-soft);
    margin-bottom: 0.4rem;
  }
  .stat .value {
    font-family: "IBM Plex Mono", monospace;
    font-weight: 600;
    font-size: 1.55rem;
    color: var(--ink);
    font-variant-numeric: tabular-nums;
  }
  .stat.accent .value { color: var(--primary); }

  .breakdown table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
  }
  .breakdown-wrap { overflow-x: auto; }
  .breakdown th, .breakdown td {
    text-align: left;
    padding: 0.55rem 0.4rem;
    border-bottom: 1px solid var(--card-border);
  }
  .breakdown td:last-child, .breakdown th:last-child {
    text-align: right;
    font-family: "IBM Plex Mono", monospace;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }
  .breakdown tr.subtotal td {
    font-weight: 700;
    border-top: 1px solid var(--ink-soft);
    border-bottom: none;
  }
  .breakdown tr.result td {
    font-weight: 700;
    padding-top: 0.75rem;
  }
  .breakdown .muted { color: var(--ink-soft); font-size: 0.78rem; }

  details.method {
    margin-top: 0.4rem;
  }
  details.method summary {
    cursor: pointer;
    font-weight: 600;
    font-size: 0.92rem;
    color: var(--primary);
    list-style: none;
  }
  details.method summary::-webkit-details-marker { display: none; }
  details.method summary::before {
    content: "＋ ";
    font-family: "IBM Plex Mono", monospace;
  }
  details.method[open] summary::before { content: "－ "; }
  details.method .body {
    margin-top: 0.8rem;
    font-size: 0.88rem;
    color: var(--ink-soft);
    line-height: 1.6;
  }
  details.method .body p { margin: 0 0 0.7rem; }
  details.method .body p:last-child { margin-bottom: 0; }
  details.method .body code {
    font-family: "IBM Plex Mono", monospace;
    background: var(--surface);
    border: 1px solid var(--card-border);
    border-radius: 4px;
    padding: 0.05rem 0.3rem;
    color: var(--ink);
  }

  footer.source {
    margin-top: 2.5rem;
    font-size: 0.78rem;
    color: var(--ink-soft);
  }
</style>
</head>
<body>

<div class="page">
  <a class="zurueck" href="index.php">&larr; Zurück zum Kassenwart</a>

  <header class="hero">
    <p class="eyebrow"><?= e(vereinNameNowrap()) ?> — Beitragsordnung Ziff. 2</p>
    <h1>Was tragen unsere Beiträge, bei welcher Mitgliederzahl?</h1>
    <p class="lede">Mitgliederzahlen, Beitragshöhen und alle zugrunde liegenden Kostensätze eintragen — die Rechnung darunter aktualisiert sich live. Ändern sich Verbandsabgaben, Verwaltungskosten oder die Schwimmhallenmiete, einfach hier nachtragen.</p>
  </header>

  <div class="input-row">
    <section class="card" aria-label="Mitgliederzahlen">
      <h2>Mitgliederzahlen</h2>
      <p class="hint">Alle Werte assoziieren erwachsene Mitglieder (ab 17/18&nbsp;Jahre) — siehe Annahme unten.</p>

      <div class="field">
        <label for="voll">Vollmitglieder <span class="sub" id="vollRateHint">— 6&nbsp;€/Monat</span></label>
        <div class="stepper">
          <button type="button" data-target="voll" data-step="-1" aria-label="Vollmitglieder verringern">−</button>
          <input id="voll" type="number" min="0" step="1" value="7" inputmode="numeric" />
          <button type="button" data-target="voll" data-step="1" aria-label="Vollmitglieder erhöhen">+</button>
        </div>
      </div>

      <div class="field">
        <label for="training">Trainingsmitglieder <span class="sub" id="trainingRateHint">— 3&nbsp;€/Monat</span></label>
        <div class="stepper">
          <button type="button" data-target="training" data-step="-1" aria-label="Trainingsmitglieder verringern">−</button>
          <input id="training" type="number" min="0" step="1" value="20" inputmode="numeric" />
          <button type="button" data-target="training" data-step="1" aria-label="Trainingsmitglieder erhöhen">+</button>
        </div>
      </div>

      <div class="field">
        <label for="foerder">Fördermitglieder <span class="sub" id="foerderRateHint">— 3&nbsp;€/Monat</span></label>
        <div class="stepper">
          <button type="button" data-target="foerder" data-step="-1" aria-label="Fördermitglieder verringern">−</button>
          <input id="foerder" type="number" min="0" step="1" value="0" inputmode="numeric" />
          <button type="button" data-target="foerder" data-step="1" aria-label="Fördermitglieder erhöhen">+</button>
        </div>
      </div>

      <div class="total-line">
        <span>Mitglieder gesamt</span>
        <strong id="totalMembers">27</strong>
      </div>

      <p class="assumption">Ehrenmitglieder sind beitragsfrei (§ 4 Abs. 5 Satzung) und fließen hier nicht in die Beitragseinnahmen ein, lösen aber weiterhin Verbandsabgaben aus, die der Verein trägt — für diesen Rechner der Einfachheit halber nicht separat erfasst.</p>
    </section>

    <section class="card" aria-label="Beitragshöhen">
      <h2>Beitragshöhen</h2>
      <p class="hint">Vorausgefüllt mit den beschlossenen Werten — zum Testen einfach anpassen.</p>

      <div class="field">
        <label for="vollBeitrag">Vollmitgliedsbeitrag <span class="sub">€/Monat</span></label>
        <div class="stepper">
          <button type="button" data-target="vollBeitrag" data-step="-0.5" aria-label="Vollmitgliedsbeitrag verringern">−</button>
          <input id="vollBeitrag" type="number" min="0" step="0.5" value="6" inputmode="decimal" />
          <button type="button" data-target="vollBeitrag" data-step="0.5" aria-label="Vollmitgliedsbeitrag erhöhen">+</button>
        </div>
        <p class="field-note" id="deutlichNote"></p>
      </div>

      <div class="field">
        <label for="trainingBeitrag">Trainingsmitgliedsbeitrag <span class="sub">€/Monat</span></label>
        <div class="stepper">
          <button type="button" data-target="trainingBeitrag" data-step="-0.5" aria-label="Trainingsmitgliedsbeitrag verringern">−</button>
          <input id="trainingBeitrag" type="number" min="0" step="0.5" value="3" inputmode="decimal" />
          <button type="button" data-target="trainingBeitrag" data-step="0.5" aria-label="Trainingsmitgliedsbeitrag erhöhen">+</button>
        </div>
        <p class="field-note" id="floorNote"></p>
      </div>

      <div class="field">
        <label for="foerderBeitrag">Fördermitgliedsbeitrag <span class="sub">€/Monat</span></label>
        <div class="stepper">
          <button type="button" data-target="foerderBeitrag" data-step="-0.5" aria-label="Fördermitgliedsbeitrag verringern">−</button>
          <input id="foerderBeitrag" type="number" min="0" step="0.5" value="3" inputmode="decimal" />
          <button type="button" data-target="foerderBeitrag" data-step="0.5" aria-label="Fördermitgliedsbeitrag erhöhen">+</button>
        </div>
      </div>
    </section>

    <section class="card" aria-label="Verbandskosten und Verwaltung">
      <h2>Verbandskosten &amp; Verwaltung</h2>
      <p class="hint">Recherchierte Sätze pro Mitglied und Jahr — zum Anpassen erst „Ändern" klicken, damit nichts versehentlich verstellt wird.</p>

      <div class="field">
        <label for="stvRate">STV-Mitgliedsbeitrag <span class="sub">€/Mitglied/Jahr</span></label>
        <div class="cost-display" data-field="stvRate">
          <span class="cost-value" id="stvRate-value">11 €</span>
          <button type="button" class="btn-edit" data-target="stvRate" aria-label="STV-Mitgliedsbeitrag ändern">Ändern</button>
        </div>
        <div class="cost-edit-row" data-field="stvRate" hidden>
          <input id="stvRate" type="number" min="0" step="0.5" value="11" inputmode="decimal" />
          <button type="button" class="btn-confirm" data-target="stvRate">Übernehmen</button>
          <button type="button" class="btn-cancel" data-target="stvRate">Abbrechen</button>
        </div>
      </div>

      <div class="field">
        <label for="refRate">STV-Kampfrichtergebühr <span class="sub">€/Mitglied/Jahr</span></label>
        <div class="cost-display" data-field="refRate">
          <span class="cost-value" id="refRate-value">1 €</span>
          <button type="button" class="btn-edit" data-target="refRate" aria-label="STV-Kampfrichtergebühr ändern">Ändern</button>
        </div>
        <div class="cost-edit-row" data-field="refRate" hidden>
          <input id="refRate" type="number" min="0" step="0.5" value="1" inputmode="decimal" />
          <button type="button" class="btn-confirm" data-target="refRate">Übernehmen</button>
          <button type="button" class="btn-cancel" data-target="refRate">Abbrechen</button>
        </div>
      </div>

      <div class="field">
        <label for="lsbRate">LSB-Mitgliedsbeitrag <span class="sub">€/Mitglied/Jahr</span></label>
        <div class="cost-display" data-field="lsbRate">
          <span class="cost-value" id="lsbRate-value">7 €</span>
          <button type="button" class="btn-edit" data-target="lsbRate" aria-label="LSB-Mitgliedsbeitrag ändern">Ändern</button>
        </div>
        <div class="cost-edit-row" data-field="lsbRate" hidden>
          <input id="lsbRate" type="number" min="0" step="0.5" value="7" inputmode="decimal" />
          <button type="button" class="btn-confirm" data-target="lsbRate">Übernehmen</button>
          <button type="button" class="btn-cancel" data-target="lsbRate">Abbrechen</button>
        </div>
      </div>

      <div class="field">
        <label for="vereinsbeitrag">STV-Vereinsbeitrag <span class="sub">€/Jahr, Staffelbetrag</span></label>
        <div class="cost-display" data-field="vereinsbeitrag">
          <span class="cost-value" id="vereinsbeitrag-value">40 €</span>
          <button type="button" class="btn-edit" data-target="vereinsbeitrag" aria-label="STV-Vereinsbeitrag ändern">Ändern</button>
        </div>
        <div class="cost-edit-row" data-field="vereinsbeitrag" hidden>
          <input id="vereinsbeitrag" type="number" min="0" step="5" value="40" inputmode="numeric" />
          <button type="button" class="btn-confirm" data-target="vereinsbeitrag">Übernehmen</button>
          <button type="button" class="btn-cancel" data-target="vereinsbeitrag">Abbrechen</button>
        </div>
        <p class="hint" id="vereinsbeitragHint" style="margin: 0.4rem 0 0;"></p>
      </div>

      <div class="field">
        <label for="verwaltungRate">Verwaltungspauschale <span class="sub">€/Mitglied/Jahr</span></label>
        <div class="cost-display" data-field="verwaltungRate">
          <span class="cost-value" id="verwaltungRate-value">15 €</span>
          <button type="button" class="btn-edit" data-target="verwaltungRate" aria-label="Verwaltungspauschale ändern">Ändern</button>
        </div>
        <div class="cost-edit-row" data-field="verwaltungRate" hidden>
          <input id="verwaltungRate" type="number" min="0" step="0.5" value="15" inputmode="decimal" />
          <button type="button" class="btn-confirm" data-target="verwaltungRate">Übernehmen</button>
          <button type="button" class="btn-cancel" data-target="verwaltungRate">Abbrechen</button>
        </div>
      </div>
    </section>

    <section class="card" aria-label="Sportbetrieb">
      <h2>Sportbetrieb</h2>
      <p class="hint">Fixkosten für Trainingszeiten — zum Anpassen erst „Ändern" klicken.</p>
      <label class="checkbox-row">
        <input type="checkbox" id="hallenzeit" checked />
        Schwimmhallenzeit einplanen
      </label>

      <div class="field" style="margin-top: 1.1rem;">
        <label for="hallenTermine">Termine <span class="sub">pro Jahr</span></label>
        <div class="cost-display" data-field="hallenTermine">
          <span class="cost-value" id="hallenTermine-value">45</span>
          <button type="button" class="btn-edit" data-target="hallenTermine" aria-label="Termine ändern">Ändern</button>
        </div>
        <div class="cost-edit-row" data-field="hallenTermine" hidden>
          <input id="hallenTermine" type="number" min="0" step="1" value="45" inputmode="numeric" />
          <button type="button" class="btn-confirm" data-target="hallenTermine">Übernehmen</button>
          <button type="button" class="btn-cancel" data-target="hallenTermine">Abbrechen</button>
        </div>
      </div>

      <div class="field">
        <label for="hallenPreis">Preis <span class="sub">€/Termin</span></label>
        <div class="cost-display" data-field="hallenPreis">
          <span class="cost-value" id="hallenPreis-value">120 €</span>
          <button type="button" class="btn-edit" data-target="hallenPreis" aria-label="Preis pro Termin ändern">Ändern</button>
        </div>
        <div class="cost-edit-row" data-field="hallenPreis" hidden>
          <input id="hallenPreis" type="number" min="0" step="5" value="120" inputmode="numeric" />
          <button type="button" class="btn-confirm" data-target="hallenPreis">Übernehmen</button>
          <button type="button" class="btn-cancel" data-target="hallenPreis">Abbrechen</button>
        </div>
      </div>

      <p class="hint" id="halleTotalHint" style="margin-top: 0.6rem; margin-bottom: 0;"></p>
    </section>
  </div>

  <section class="results" aria-label="Ergebnis">
      <div class="status-row">
        <span class="pill" id="statusPill">—</span>
        <span id="statusText" style="color: var(--ink-soft); font-size: 0.9rem;"></span>
      </div>

      <div class="stat-strip">
        <div class="stat">
          <div class="label">Kosten pro Jahr</div>
          <div class="value" id="statCosts">0 €</div>
        </div>
        <div class="stat accent">
          <div class="label">Einnahmen pro Jahr</div>
          <div class="value" id="statIncome">0 €</div>
        </div>
        <div class="stat">
          <div class="label">Überschuss</div>
          <div class="value" id="statSurplus">0 €</div>
        </div>
      </div>

      <div class="card breakdown">
        <h2>Kostenherleitung</h2>
        <p class="hint">Grundlage: Beitragsordnung Ziff. 2 (STV-/LSB-Abgaben, Verwaltungspauschale)</p>
        <div class="breakdown-wrap">
        <table>
          <thead>
            <tr><th>Position</th><th>Betrag/Jahr</th></tr>
          </thead>
          <tbody>
            <tr><td>STV-Mitgliedsbeitrag <span class="muted" id="rowStvLabel">(11 €/Mitglied)</span></td><td id="rowStv">0 €</td></tr>
            <tr><td>STV-Kampfrichtergebühr <span class="muted" id="rowRefLabel">(1 €/Mitglied)</span></td><td id="rowRef">0 €</td></tr>
            <tr><td>LSB-Mitgliedsbeitrag <span class="muted" id="rowLsbLabel">(7 €/Mitglied)</span></td><td id="rowLsb">0 €</td></tr>
            <tr><td>STV-Vereinsbeitrag <span class="muted" id="rowStaffelLabel">(Staffel)</span></td><td id="rowStaffel">0 €</td></tr>
            <tr><td>Verwaltungspauschale <span class="muted" id="rowVerwaltungLabel">(15 €/Mitglied)</span></td><td id="rowVerwaltung">0 €</td></tr>
            <tr><td>Schwimmhallenzeit <span class="muted" id="rowHalleLabel">(45 × 120 €, falls eingeplant)</span></td><td id="rowHalle">0 €</td></tr>
            <tr class="subtotal"><td>Kosten gesamt</td><td id="rowCostTotal">0 €</td></tr>
            <tr><td>Beitragseinnahmen <span class="muted" id="incomeRatesLabel">(6 € / 3 € / 3 € pro Monat)</span></td><td id="rowIncome">0 €</td></tr>
            <tr class="result"><td>Überschuss</td><td id="rowSurplus">0 €</td></tr>
          </tbody>
        </table>
        </div>

        <details class="method">
          <summary>Wie wird gerechnet?</summary>
          <div class="body">
            <p><strong>Alle Kostensätze sind editierbar:</strong> STV-Mitgliedsbeitrag, STV-Kampfrichtergebühr, LSB-Mitgliedsbeitrag, STV-Vereinsbeitrag, Verwaltungspauschale sowie Termine und Preis der Schwimmhallenzeit lassen sich oben anpassen — praktisch, wenn STV/LSB ihre Sätze ändern oder sich die Hallenmiete verändert. Damit die recherchierten Werte nicht versehentlich verstellt werden, ist dafür bewusst ein zusätzlicher Klick nötig: „Ändern" öffnet das Feld, „Übernehmen" speichert den neuen Wert (oder „Abbrechen"/Esc verwirft ihn). Mitgliederzahlen und Beitragshöhen bleiben wie gewohnt direkt über die −/+ Regler änderbar, da sie zum Durchspielen von Szenarien gedacht sind.</p>
            <p><strong>Untergrenze je Trainingsmitglied:</strong> Summe aus STV-Mitgliedsbeitrag, STV-Kampfrichtergebühr, LSB-Mitgliedsbeitrag und Verwaltungspauschale (alle oben einstellbar), geteilt durch 12. Der Hinweistext unter dem Trainingsmitgliedsbeitrag zeigt den aktuell daraus berechneten Monatswert.</p>
            <p><strong>STV-Vereinsbeitrag</strong> ist beim STV kein Pro-Kopf-Betrag, sondern eine Staffel nach der Gesamtmitgliederzahl (1–25: 25 €, 26–50: 40 €, 51–75: 50 €, 76–99: 65 €, ab 100: 75 €). Der Rechner trägt diesen Betrag nicht automatisch ein, sondern zeigt als Hinweis, welche Staffel bei der aktuellen Mitgliederzahl offiziell gilt — das Feld selbst bleibt frei editierbar.</p>
            <p><strong>Verwaltungspauschale</strong> deckt Kontoführung und Vereinsverwaltungssoftware; der voreingestellte Satz (15 €/Mitglied/Jahr) wurde anhand einer groben Schätzung der jährlichen Vereinsführungskosten (Kategorie 2 der Kostenaufstellung) hergeleitet.</p>
            <p><strong>Schwimmhallenzeit:</strong> als Termine/Jahr × Preis/Termin modelliert, damit sich Szenarien wie „weniger Bahnen" oder „seltener buchen" direkt durchrechnen lassen. Die Checkbox lässt sich zu Vergleichszwecken ganz deaktivieren; die aktuell beschlossenen Beitragshöhen decken diesen Posten noch nicht.</p>
            <p><strong>Warum 6 € / 3 € / 3 € voreingestellt sind:</strong> Von drei geprüften Szenarien wurde das mit der geringsten Überschussquote (~31&nbsp;%) gewählt, weil ein dauerhaft sehr hoher Überschuss dem Grundsatz der zeitnahen Mittelverwendung (§ 55 Abs. 1 Nr. 5 AO) widersprechen kann. Der Vollmitgliedsbeitrag liegt beim Faktor 2 über dem Trainingsmitgliedsbeitrag und erfüllt damit das „deutlich"-Kriterium aus § 7 Abs. 1 der Satzung. Ändert ihr die Beitragshöhen oben, prüft der Rechner beide Kriterien live und weist darauf hin, falls ein neuer Wert die Formel-Untergrenze unterschreitet oder das „deutlich"-Kriterium nicht mehr erfüllt.</p>
          </div>
        </details>
      </div>
    </section>

  <footer class="source">Dient als Diskussionsgrundlage — ersetzt keine rechtliche/steuerliche Beratung.</footer>
</div>

<script>
(function () {
  var ids = ['voll', 'training', 'foerder'];
  var rateIds = ['vollBeitrag', 'trainingBeitrag', 'foerderBeitrag'];
  var costRateIds = ['stvRate', 'refRate', 'lsbRate', 'verwaltungRate'];
  var otherCostIds = ['vereinsbeitrag', 'hallenTermine', 'hallenPreis'];
  var manualCostIds = costRateIds.concat(otherCostIds); // require "Ändern" -> "Übernehmen" instead of live editing
  var floatIds = rateIds.concat(costRateIds, otherCostIds);
  var decimalIds = rateIds.concat(costRateIds); // rounded to 1 decimal on apply, everything else to whole numbers
  var allIds = ids.concat(floatIds);
  var confirmedCosts = {}; // last "Übernehmen"-confirmed value per manualCostIds field

  function loadSaved() {
    try {
      var raw = localStorage.getItem('tf-beitragsrechner');
      if (!raw) return;
      var saved = JSON.parse(raw);
      allIds.forEach(function (id) {
        if (typeof saved[id] === 'number' && saved[id] >= 0) {
          document.getElementById(id).value = saved[id];
        }
      });
      if (typeof saved.hallenzeit === 'boolean') {
        document.getElementById('hallenzeit').checked = saved.hallenzeit;
      }
    } catch (e) { /* ignore */ }
  }

  function persist(values) {
    try {
      values.hallenzeit = document.getElementById('hallenzeit').checked;
      localStorage.setItem('tf-beitragsrechner', JSON.stringify(values));
    } catch (e) { /* ignore */ }
  }

  function fmtEUR(n) {
    var sign = n < 0 ? '-' : '';
    var abs = Math.round(Math.abs(n));
    return sign + abs.toLocaleString('de-DE') + ' €';
  }

  function fmtRate(n) {
    return n.toLocaleString('de-DE', { maximumFractionDigits: 1 }) + ' €';
  }

  function costDisplayText(id, val) {
    if (id === 'hallenTermine') return val.toLocaleString('de-DE');
    return fmtRate(val);
  }

  function roundForField(id, val) {
    return decimalIds.indexOf(id) !== -1 ? Math.round(val * 10) / 10 : Math.round(val);
  }

  function stvStaffel(total) {
    if (total <= 25) return { amount: 25, label: 'Staffel „1–25 Mitglieder"' };
    if (total <= 50) return { amount: 40, label: 'Staffel „26–50 Mitglieder"' };
    if (total <= 75) return { amount: 50, label: 'Staffel „51–75 Mitglieder"' };
    if (total <= 99) return { amount: 65, label: 'Staffel „76–99 Mitglieder"' };
    return { amount: 75, label: 'Staffel „ab 100 Mitglieder"' };
  }

  function readValues() {
    var v = {};
    ids.forEach(function (id) {
      var el = document.getElementById(id);
      var n = parseInt(el.value, 10);
      if (isNaN(n) || n < 0) n = 0;
      v[id] = n;
    });
    rateIds.forEach(function (id) {
      var el = document.getElementById(id);
      var n = parseFloat(el.value);
      if (isNaN(n) || n < 0) n = 0;
      v[id] = n;
    });
    manualCostIds.forEach(function (id) {
      v[id] = confirmedCosts[id];
    });
    return v;
  }

  function recompute() {
    var v = readValues();
    var total = v.voll + v.training + v.foerder;
    document.getElementById('totalMembers').textContent = total.toLocaleString('de-DE');

    document.getElementById('vollRateHint').textContent = '— ' + fmtRate(v.vollBeitrag) + '/Monat';
    document.getElementById('trainingRateHint').textContent = '— ' + fmtRate(v.trainingBeitrag) + '/Monat';
    document.getElementById('foerderRateHint').textContent = '— ' + fmtRate(v.foerderBeitrag) + '/Monat';
    document.getElementById('incomeRatesLabel').textContent =
      '(' + fmtRate(v.vollBeitrag) + ' / ' + fmtRate(v.trainingBeitrag) + ' / ' + fmtRate(v.foerderBeitrag) + ' pro Monat)';

    var floorPerYear = v.stvRate + v.refRate + v.lsbRate + v.verwaltungRate;
    var floorPerMonth = floorPerYear / 12;

    var floorNote = document.getElementById('floorNote');
    if (v.trainingBeitrag * 12 < floorPerYear) {
      floorNote.textContent = 'Liegt unter der Formel-Untergrenze von ' + floorPerMonth.toFixed(2).replace('.', ',') + ' €/Monat (Kostensätze oben zusammengerechnet, je erwachsenem Mitglied).';
    } else {
      floorNote.textContent = '';
    }

    var deutlichNote = document.getElementById('deutlichNote');
    if (v.trainingBeitrag > 0 && v.vollBeitrag <= v.trainingBeitrag) {
      deutlichNote.textContent = 'Liegt nicht über dem Trainingsmitgliedsbeitrag — widerspricht § 7 Abs. 1 der Satzung.';
    } else if (v.trainingBeitrag > 0 && v.vollBeitrag < v.trainingBeitrag * 1.5) {
      deutlichNote.textContent = 'Faktor ' + (v.vollBeitrag / v.trainingBeitrag).toFixed(1).replace('.', ',') + ' — prüft, ob das noch „deutlich" im Sinne von § 7 Abs. 1 der Satzung ist.';
    } else {
      deutlichNote.textContent = '';
    }

    var staffel = stvStaffel(total);
    document.getElementById('vereinsbeitragHint').textContent =
      'STV-Staffel-Empfehlung für ' + total.toLocaleString('de-DE') + ' Mitglieder: ' + fmtEUR(staffel.amount) + ' (' + staffel.label + ')';

    var stv = total * v.stvRate;
    var ref = total * v.refRate;
    var lsb = total * v.lsbRate;
    var vereinsbeitrag = v.vereinsbeitrag;
    var verwaltung = total * v.verwaltungRate;
    var hallenzeitOn = document.getElementById('hallenzeit').checked;
    var halle = hallenzeitOn ? (v.hallenTermine * v.hallenPreis) : 0;
    var costTotal = stv + ref + lsb + vereinsbeitrag + verwaltung + halle;

    var halleTotalHint = document.getElementById('halleTotalHint');
    if (hallenzeitOn) {
      halleTotalHint.innerHTML = v.hallenTermine.toLocaleString('de-DE') + ' Termine × ' + fmtRate(v.hallenPreis) + ' = <strong>' + fmtEUR(halle) + '</strong>/Jahr';
    } else {
      halleTotalHint.textContent = 'Deaktiviert — wird nicht in die Kosten eingerechnet.';
    }

    var income = v.voll * v.vollBeitrag * 12 + v.training * v.trainingBeitrag * 12 + v.foerder * v.foerderBeitrag * 12;
    var surplus = income - costTotal;
    var quote = costTotal > 0 ? (surplus / costTotal) * 100 : null;

    document.getElementById('rowStvLabel').textContent = '(' + fmtRate(v.stvRate) + '/Mitglied)';
    document.getElementById('rowRefLabel').textContent = '(' + fmtRate(v.refRate) + '/Mitglied)';
    document.getElementById('rowLsbLabel').textContent = '(' + fmtRate(v.lsbRate) + '/Mitglied)';
    document.getElementById('rowVerwaltungLabel').textContent = '(' + fmtRate(v.verwaltungRate) + '/Mitglied)';
    document.getElementById('rowHalleLabel').textContent = '(' + v.hallenTermine.toLocaleString('de-DE') + ' × ' + fmtRate(v.hallenPreis) + ', falls eingeplant)';

    manualCostIds.forEach(function (id) {
      document.getElementById(id + '-value').textContent = costDisplayText(id, v[id]);
    });

    document.getElementById('rowStv').textContent = fmtEUR(stv);
    document.getElementById('rowRef').textContent = fmtEUR(ref);
    document.getElementById('rowLsb').textContent = fmtEUR(lsb);
    document.getElementById('rowStaffelLabel').textContent = '(' + staffel.label + ')';
    document.getElementById('rowStaffel').textContent = fmtEUR(vereinsbeitrag);
    document.getElementById('rowVerwaltung').textContent = fmtEUR(verwaltung);
    document.getElementById('rowHalle').textContent = fmtEUR(halle);
    document.getElementById('rowCostTotal').textContent = fmtEUR(costTotal);
    document.getElementById('rowIncome').textContent = fmtEUR(income);
    document.getElementById('rowSurplus').textContent = fmtEUR(surplus);

    document.getElementById('statCosts').textContent = fmtEUR(costTotal);
    document.getElementById('statIncome').textContent = fmtEUR(income);
    document.getElementById('statSurplus').textContent = fmtEUR(surplus);

    var pill = document.getElementById('statusPill');
    var text = document.getElementById('statusText');
    pill.className = 'pill';
    if (total === 0) {
      pill.classList.add('warn');
      pill.textContent = 'Keine Mitglieder';
      text.textContent = 'Mindestens ein Mitglied eintragen, um eine Kalkulation zu sehen.';
    } else if (quote < 0) {
      pill.classList.add('bad');
      pill.textContent = 'Defizit';
      text.textContent = 'Die Beiträge decken die Kosten bei dieser Mitgliederzahl nicht.';
    } else if (quote < 15) {
      pill.classList.add('warn');
      pill.textContent = 'Knapp';
      text.textContent = 'Überschussquote ' + quote.toFixed(0) + ' % — wenig Puffer für Einzelfälle.';
    } else if (quote <= 50) {
      pill.classList.add('good');
      pill.textContent = 'Gesund';
      text.textContent = 'Überschussquote ' + quote.toFixed(0) + ' % — angemessener Puffer, wie bei Szenario A geplant.';
    } else {
      pill.classList.add('warn');
      pill.textContent = 'Hoher Überschuss';
      text.textContent = 'Überschussquote ' + quote.toFixed(0) + ' % — Mittelverwendungsgebot (§ 55 AO) im Blick behalten.';
    }

    persist(v);
  }

  ids.concat(rateIds).forEach(function (id) {
    document.getElementById(id).addEventListener('input', recompute);
  });
  document.getElementById('hallenzeit').addEventListener('change', recompute);
  document.querySelectorAll('.stepper button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.getElementById(btn.dataset.target);
      var isDecimal = decimalIds.indexOf(btn.dataset.target) !== -1;
      var step = parseFloat(btn.dataset.step);
      var val = parseFloat(target.value);
      if (isNaN(val)) val = 0;
      val = Math.max(0, val + step);
      target.value = isDecimal ? Math.round(val * 10) / 10 : Math.round(val);
      recompute();
    });
  });

  function openEdit(id) {
    document.querySelector('.cost-display[data-field="' + id + '"]').hidden = true;
    document.querySelector('.cost-edit-row[data-field="' + id + '"]').hidden = false;
    var input = document.getElementById(id);
    input.value = confirmedCosts[id];
    input.focus();
    input.select();
  }

  function confirmEdit(id) {
    var input = document.getElementById(id);
    var val = parseFloat(input.value);
    if (isNaN(val) || val < 0) val = 0;
    val = roundForField(id, val);
    confirmedCosts[id] = val;
    input.value = val;
    document.querySelector('.cost-edit-row[data-field="' + id + '"]').hidden = true;
    document.querySelector('.cost-display[data-field="' + id + '"]').hidden = false;
    recompute();
  }

  function cancelEdit(id) {
    document.getElementById(id).value = confirmedCosts[id];
    document.querySelector('.cost-edit-row[data-field="' + id + '"]').hidden = true;
    document.querySelector('.cost-display[data-field="' + id + '"]').hidden = false;
  }

  document.querySelectorAll('.btn-edit').forEach(function (btn) {
    btn.addEventListener('click', function () { openEdit(btn.dataset.target); });
  });
  document.querySelectorAll('.btn-confirm').forEach(function (btn) {
    btn.addEventListener('click', function () { confirmEdit(btn.dataset.target); });
  });
  document.querySelectorAll('.btn-cancel').forEach(function (btn) {
    btn.addEventListener('click', function () { cancelEdit(btn.dataset.target); });
  });
  manualCostIds.forEach(function (id) {
    document.getElementById(id).addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); confirmEdit(id); }
      else if (e.key === 'Escape') { e.preventDefault(); cancelEdit(id); }
    });
  });

  loadSaved();
  manualCostIds.forEach(function (id) {
    var n = parseFloat(document.getElementById(id).value);
    confirmedCosts[id] = isNaN(n) || n < 0 ? 0 : n;
  });
  recompute();
})();
</script>

</body>
</html>
