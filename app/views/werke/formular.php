<?php
/** @var array $werk @var ?string $fehler @var int $verwendungen @var string $zurueck @var bool $werksuche @var array $alleKategorien */
$id = $werk['id'] ?? null;
$komponisten = $werk['komponisten'] ?? [];
if (!$komponisten) {
    $komponisten = [['nachname' => '', 'vorname' => '']];
}
$dauer = $werk['dauer_eingabe'] ?? Dauer::format(isset($werk['dauer_sek']) && $werk['dauer_sek'] !== null ? (int)$werk['dauer_sek'] : null);
?>
<div class="seitenkopf">
  <h1><?= $id ? 'Werk bearbeiten' : 'Neues Werk' ?></h1>
  <a class="knopf" href="<?= e(url($zurueck ?: '/werke')) ?>">Zurück</a>
</div>

<form method="post" action="<?= e(url($id ? "/werke/$id" : '/werke')) ?>" class="karte formular" id="werk-formular">
  <?= csrf_feld() ?>
  <input type="hidden" name="zurueck" value="<?= e($zurueck) ?>">
  <?php if ($fehler): ?><div class="hinweis fehler" role="alert"><?= e($fehler) ?></div><?php endif ?>

  <label>Titel *
    <input type="text" name="titel" value="<?= e($werk['titel'] ?? '') ?>" required maxlength="300" autocomplete="off">
  </label>

  <fieldset class="komponisten">
    <legend>Komponist(en) <small>· Nachname Pflicht</small></legend>
    <div id="komponisten-liste">
      <?php foreach ($komponisten as $i => $k): ?>
        <div class="komponist-zeile">
          <input type="text" name="komponisten[<?= $i ?>][nachname]" value="<?= e($k['nachname'] ?? '') ?>" placeholder="Nachname" aria-label="Nachname" autocomplete="off">
          <input type="text" name="komponisten[<?= $i ?>][vorname]" value="<?= e($k['vorname'] ?? '') ?>" placeholder="Vorname" aria-label="Vorname" autocomplete="off">
          <button type="button" class="knopf klein-knopf" data-komponist-entfernen aria-label="Komponist entfernen">✕</button>
        </div>
      <?php endforeach ?>
    </div>
    <template id="komponist-vorlage">
      <div class="komponist-zeile">
        <input type="text" name="komponisten[__I__][nachname]" placeholder="Nachname" aria-label="Nachname" autocomplete="off">
        <input type="text" name="komponisten[__I__][vorname]" placeholder="Vorname" aria-label="Vorname" autocomplete="off">
        <button type="button" class="knopf klein-knopf" data-komponist-entfernen aria-label="Komponist entfernen">✕</button>
      </div>
    </template>
    <button type="button" class="knopf" id="komponist-hinzufuegen">+ Komponist</button>
    <p class="klein gedimmt">Im GEMA-Export passen bis zu 5 Komponisten in die Vorlage.</p>
  </fieldset>

  <div class="zwei-spalten">
    <label>GEMA-Werknummer
      <input type="text" name="gema_werknummer" value="<?= e($werk['gema_werknummer'] ?? '') ?>" inputmode="numeric" autocomplete="off" maxlength="40">
    </label>
    <label>Dauer (mm:ss)
      <input type="text" name="dauer" value="<?= e($dauer) ?>" inputmode="decimal" placeholder="7:30" autocomplete="off" maxlength="8">
    </label>
  </div>

  <label>ISWC <small class="gedimmt">optional</small>
    <input type="text" name="iswc" value="<?= e($werk['iswc'] ?? '') ?>" placeholder="T-123.456.789-0" autocomplete="off" maxlength="20">
  </label>

  <label>Besetzung <small class="gedimmt">laut GEMA-Anmeldung</small>
    <input type="text" name="besetzung" value="<?= e($werk['besetzung'] ?? '') ?>" placeholder="z. B. Saxophon, Kontrabass, Schlagzeug" autocomplete="off" maxlength="500">
  </label>

  <?php $gewaehlt = array_column($werk['kategorien'] ?? [], 'id'); ?>
  <fieldset class="kategorien-wahl">
    <legend>Kategorien <small>· z. B. Bands</small></legend>
    <?php if ($alleKategorien): ?>
      <div class="chips">
        <?php foreach ($alleKategorien as $k): ?>
          <label class="chip-wahl"><input type="checkbox" name="kategorien[]" value="<?= $k['id'] ?>" <?= in_array($k['id'], $gewaehlt, true) ? 'checked' : '' ?>><span><?= e($k['name']) ?></span></label>
        <?php endforeach ?>
      </div>
    <?php endif ?>
    <label>Neue Kategorie
      <input type="text" name="neue_kategorie" value="<?= e($werk['neue_kategorie'] ?? '') ?>" placeholder="Name, z. B. Bandname" maxlength="80" autocomplete="off">
    </label>
  </fieldset>

  <label class="schalter">
    <input type="checkbox" name="eigenes_werk" value="1" <?= !empty($werk['eigenes_werk']) ? 'checked' : '' ?>> Eigenes Werk
  </label>

  <label>Notizen
    <textarea name="notizen" rows="3"><?= e($werk['notizen'] ?? '') ?></textarea>
  </label>

  <?php if ($werksuche): ?>
    <details class="werksuche" id="gema-suche" data-titel-feld="titel">
      <summary>In der GEMA-Repertoiresuche nachschlagen</summary>
      <p class="klein gedimmt">Sucht nach dem Titel oben (optional mit Komponist). Übernimmt Werknummer und Komponisten per Antippen. Funktioniert nur, solange die öffentliche GEMA-Suche erreichbar ist.</p>
      <button type="button" class="knopf" id="gema-suche-start">Suchen</button>
      <div id="gema-suche-ergebnis" aria-live="polite"></div>
    </details>
  <?php endif ?>

  <div class="aktionen">
    <button type="submit" class="knopf primaer">Speichern</button>
  </div>
</form>

<?php if ($id): ?>
  <form method="post" action="<?= e(url("/werke/$id/loeschen")) ?>" class="gefahrenzone" data-bestaetigen="Werk wirklich löschen?">
    <?= csrf_feld() ?>
    <?php if ($verwendungen > 0): ?>
      <p class="klein gedimmt">Steht in <?= $verwendungen ?> Setlist-Eintr<?= $verwendungen === 1 ? 'ag' : 'ägen' ?> und kann daher nicht gelöscht werden.</p>
    <?php else: ?>
      <button type="submit" class="knopf gefahr">Werk löschen</button>
    <?php endif ?>
  </form>
<?php endif ?>
