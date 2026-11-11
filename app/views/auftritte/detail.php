<?php
/**
 * @var array $a @var array $setlist @var ?array $setlistFrist @var ?array $reklamationFrist
 * @var ?DateTimeImmutable $reklamationBis @var DateTimeImmutable $setlistFaellig
 */
$id = (int)$a['id'];
?>
<div class="seitenkopf">
  <div>
    <h1><?= e($a['venue'] ?: 'Auftritt') ?></h1>
    <p class="untertitel">
      <?= e(datum_de($a['datum'], true)) ?><?= $a['uhrzeit'] ? ', ' . e($a['uhrzeit']) . ' Uhr' : '' ?>
      <?= $a['stadt'] ? ' · ' . e($a['stadt']) : '' ?>
    </p>
  </div>
</div>

<div class="knopfleiste">
  <a class="knopf" href="<?= e(url("/auftritte/$id/bearbeiten")) ?>">Bearbeiten</a>
  <a class="knopf" href="<?= e(url('/auftritte/neu', ['vorlage' => $id])) ?>">Als Vorlage kopieren</a>
  <a class="knopf primaer" href="<?= e(url("/auftritte/$id/export")) ?>">Export</a>
</div>

<?php if ($setlistFrist && $setlistFrist['stufe'] !== 'zukunft'): ?>
  <div class="hinweis <?= $setlistFrist['stufe'] === 'ueberfaellig' ? 'fehler' : 'warnung' ?>">
    Setlist <?= $setlistFrist['stufe'] === 'ueberfaellig' ? 'überfällig' : 'fällig' ?>:
    <?= e(datum_de($setlistFrist['faellig']->format('Y-m-d'))) ?> (<?= e(tage_text($setlistFrist['tage'])) ?>)
  </div>
<?php endif ?>
<?php if ($reklamationFrist && in_array($reklamationFrist['stufe'], ['bald', 'abgelaufen'], true)): ?>
  <div class="hinweis fehler">
    Reklamationsfrist <?= $reklamationFrist['stufe'] === 'abgelaufen' ? 'abgelaufen am' : 'endet am' ?>
    <?= e(datum_de($reklamationFrist['bis']->format('Y-m-d'))) ?> (<?= e(tage_text($reklamationFrist['tage'])) ?>)
  </div>
<?php endif ?>

<dl class="eckdaten karte">
  <?php if ($a['ensemble']): ?><div><dt>Ensemble</dt><dd><?= e($a['ensemble']) ?></dd></div><?php endif ?>
  <?php if ($a['anzahl_mitwirkende']): ?><div><dt>Mitwirkende</dt><dd><?= (int)$a['anzahl_mitwirkende'] ?></dd></div><?php endif ?>
  <?php if ($a['veranstalter_name'] || $a['veranstalter_kontakt']): ?>
    <div><dt>Veranstalter</dt><dd><?= e(implode(' · ', array_filter([$a['veranstalter_name'], $a['veranstalter_kontakt']]))) ?></dd></div>
  <?php endif ?>
  <?php if ($a['notizen']): ?><div class="voll"><dt>Notizen</dt><dd class="umbruch"><?= e($a['notizen']) ?></dd></div><?php endif ?>
</dl>

<section class="karte" id="setlist-bereich" data-auftritt="<?= $id ?>">
  <div class="abschnittskopf">
    <h2>Setlist</h2>
    <span class="gedimmt klein" id="setlist-zusammenfassung"><?= e(SetlistController::zusammenfassung($setlist)) ?></span>
  </div>

  <div class="werk-suche">
    <input type="search" id="werk-suche" placeholder="Werk suchen und antippen …" autocomplete="off" aria-label="Werk suchen" enterkeyhint="search">
    <ul id="werk-treffer" class="treffer" aria-live="polite"></ul>
  </div>

  <div id="setlist"><?= View::teil('auftritte/_setlist', ['setlist' => $setlist, 'auftrittId' => $id]) ?></div>

  <?php if ($setlist): ?>
    <div class="knopfleiste klein">
      <span class="gedimmt">Alle Titel:</span>
      <button type="button" class="knopf klein-knopf" data-alle-abgerechnet="gefunden">gefunden</button>
      <button type="button" class="knopf klein-knopf" data-alle-abgerechnet="fehlt">fehlt</button>
      <button type="button" class="knopf klein-knopf" data-alle-abgerechnet="ungeprueft">zurücksetzen</button>
    </div>
  <?php endif ?>
</section>

<section class="karte" id="status">
  <h2>Status</h2>
  <form method="post" action="<?= e(url("/auftritte/$id/status")) ?>" class="formular" id="status-formular">
    <?= csrf_feld() ?>
    <fieldset>
      <legend>Setlist an die GEMA</legend>
      <label>Status
        <select name="setlist_status" data-zeigt="selbst_eingereicht:eingereicht-feld"><?= optionen(Status::SETLIST, $a['setlist_status']) ?></select>
      </label>
      <label id="eingereicht-feld">Eingereicht am
        <input type="date" name="setlist_eingereicht_am" value="<?= e($a['setlist_eingereicht_am']) ?>">
      </label>
      <p class="klein gedimmt">Einreichungsfrist: <?= e(datum_de($setlistFaellig->format('Y-m-d'))) ?></p>
    </fieldset>
    <fieldset>
      <legend>Abrechnung</legend>
      <label>Ausschüttungstermin <small class="gedimmt">(in dem der Auftritt auftauchen sollte)</small>
        <input type="date" name="ausschuettungstermin" value="<?= e($a['ausschuettungstermin']) ?>">
      </label>
      <label>Status
        <select name="abrechnung_status" data-zeigt="reklamiert,erledigt:reklamiert-feld"><?= optionen(Status::ABRECHNUNG, $a['abrechnung_status']) ?></select>
      </label>
      <label id="reklamiert-feld">Reklamiert am
        <input type="date" name="reklamiert_am" value="<?= e($a['reklamiert_am']) ?>">
      </label>
      <?php if ($reklamationBis): ?>
        <p class="klein gedimmt">Reklamation möglich bis: <?= e(datum_de($reklamationBis->format('Y-m-d'))) ?></p>
      <?php endif ?>
    </fieldset>
    <div class="aktionen"><button type="submit" class="knopf primaer">Status speichern</button></div>
  </form>
</section>

<form method="post" action="<?= e(url("/auftritte/$id/loeschen")) ?>" class="gefahrenzone" data-bestaetigen="Auftritt samt Setlist wirklich löschen?">
  <?= csrf_feld() ?>
  <button type="submit" class="knopf gefahr">Auftritt löschen</button>
</form>
