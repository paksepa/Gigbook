<?php /** @var array $werte @var ?string $fehler @var array $anzahl @var bool $werksuche @var array $kategorien */ ?>
<div class="seitenkopf"><h1>Einstellungen</h1></div>

<?php if ($fehler): ?><div class="hinweis fehler" role="alert"><?= e($fehler) ?></div><?php endif ?>

<section class="karte">
  <h2>Fristen</h2>
  <p class="klein gedimmt">Falls die GEMA ihre Fristen ändert, hier anpassen. Alle Berechnungen nutzen sofort die neuen Werte.</p>
  <form method="post" action="<?= e(url('/einstellungen')) ?>" class="formular">
    <?= csrf_feld() ?>
    <?php foreach (Einstellungen::FELDER as $schluessel => [$label, $hinweis, $min, $max]): ?>
      <label><?= e($label) ?>
        <input type="number" name="<?= e($schluessel) ?>" value="<?= e($werte[$schluessel]) ?>" min="<?= $min ?>" max="<?= $max ?>" required inputmode="numeric">
        <small class="gedimmt"><?= e($hinweis) ?></small>
      </label>
    <?php endforeach ?>
    <div class="aktionen"><button type="submit" class="knopf primaer">Speichern</button></div>
  </form>
</section>

<section class="karte" id="kategorien">
  <h2>Kategorien</h2>
  <?php if (!$kategorien): ?>
    <p class="klein gedimmt">Noch keine Kategorien. Du legst sie beim Bearbeiten eines Werks an oder in der Werkliste über „Einsortieren …“.</p>
  <?php else: ?>
    <p class="klein gedimmt">Beim Löschen einer Kategorie bleiben die Werke erhalten.</p>
    <ul class="kategorie-verwaltung">
      <?php foreach ($kategorien as $k): ?>
        <li>
          <details>
            <summary>
              <span class="kategorie-titel"><?= e($k['name']) ?></span>
              <span class="klein gedimmt"><?= $k['anzahl'] ?> Werk<?= $k['anzahl'] === 1 ? '' : 'e' ?></span>
            </summary>
            <form method="post" action="<?= e(url('/kategorien/' . $k['id'])) ?>" class="kategorie-name">
              <?= csrf_feld() ?>
              <input type="text" name="name" value="<?= e($k['name']) ?>" maxlength="80" required aria-label="Name der Kategorie">
              <button type="submit" class="knopf primaer">Umbenennen</button>
            </form>
            <div class="kategorie-aktionen">
              <a class="knopf" href="<?= e(url('/werke', ['kat' => (string)$k['id']])) ?>">Werke ansehen</a>
              <form method="post" action="<?= e(url('/kategorien/' . $k['id'] . '/loeschen')) ?>"
                    data-bestaetigen="Kategorie „<?= e($k['name']) ?>“ löschen? Die Werke bleiben erhalten.">
                <?= csrf_feld() ?><button type="submit" class="knopf gefahr">Löschen</button>
              </form>
            </div>
          </details>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</section>

<section class="karte" id="werkverzeichnis">
  <h2>GEMA-Werkverzeichnis</h2>
  <p class="klein gedimmt">Excel-Export deiner Werke aus dem GEMA-Online-Portal (<code>Werke_….xlsx</code>) übernehmen.
    Neue Werke werden angelegt und als „eigen“ markiert. Bei vorhandenen Werken werden Werknummer, ISWC und Besetzung ergänzt.
    Komponisten und Dauer kommen nur dazu, wo sie noch fehlen. Es wird nichts gelöscht.</p>
  <form method="post" action="<?= e(url('/einstellungen/werkverzeichnis')) ?>" enctype="multipart/form-data" class="formular">
    <?= csrf_feld() ?>
    <input type="file" name="datei" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
    <div class="aktionen"><button type="submit" class="knopf primaer">Werkverzeichnis übernehmen</button></div>
  </form>
</section>

<section class="karte">
  <h2>Datensicherung</h2>
  <p class="klein gedimmt">Bestand: <?= $anzahl['werke'] ?> Werke, <?= $anzahl['auftritte'] ?> Auftritte.</p>
  <div class="knopfleiste">
    <a class="knopf" href="<?= e(url('/einstellungen/backup')) ?>">Datenbank herunterladen (.sqlite)</a>
    <a class="knopf" href="<?= e(url('/einstellungen/export')) ?>">JSON-Export</a>
  </div>

  <h3>JSON-Import</h3>
  <form method="post" action="<?= e(url('/einstellungen/import')) ?>" enctype="multipart/form-data" class="formular"
        data-bestaetigen="Wirklich ALLE vorhandenen Werke und Auftritte durch den Inhalt der Datei ersetzen?">
    <?= csrf_feld() ?>
    <input type="file" name="datei" accept="application/json,.json" required>
    <label class="schalter"><input type="checkbox" name="bestaetigt" value="1" required> Alle vorhandenen Daten werden ersetzt</label>
    <p class="klein gedimmt">Vor dem Import wird automatisch eine Sicherheitskopie in <code>data/sicherungen/</code> abgelegt.</p>
    <div class="aktionen"><button type="submit" class="knopf gefahr">Importieren</button></div>
  </form>
</section>

<section class="karte">
  <h2>Sitzung</h2>
  <div class="knopfleiste">
    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_feld() ?><button class="knopf" type="submit">Abmelden</button></form>
    <form method="post" action="<?= e(url('/einstellungen/abmelden-alle')) ?>" data-bestaetigen="Auf allen Geräten abmelden?">
      <?= csrf_feld() ?><button class="knopf" type="submit">Auf allen Geräten abmelden</button>
    </form>
  </div>
</section>

<p class="klein gedimmt zentriert"><?= APP_NAME ?> <?= APP_VERSION ?> · Datenbank-Schema <?= Schema::version() ?> · GEMA-Werksuche <?= $werksuche ? 'aktiv' : 'deaktiviert' ?></p>
