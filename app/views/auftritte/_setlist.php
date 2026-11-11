<?php
/** Setlist-Liste (wird auch per JSON nachgeladen). @var array $setlist @var int $auftrittId */
?>
<?php if (!$setlist): ?>
  <p class="leer">Noch keine Titel. Oben suchen und antippen.</p>
<?php else: ?>
<ol class="setlist" data-sortierbar data-auftritt="<?= $auftrittId ?>">
  <?php foreach ($setlist as $i => $e): ?>
    <li data-id="<?= (int)$e['id'] ?>" class="abgerechnet-<?= e($e['abgerechnet']) ?>">
      <span class="griff" aria-label="Ziehen zum Sortieren" title="Ziehen zum Sortieren">⋮⋮</span>
      <span class="nr"><?= $i + 1 ?></span>
      <div class="eintrag">
        <div class="eintrag-kopf">
          <a class="titel" href="<?= e(url('/werke/' . $e['werk_id'], ['zurueck' => '/auftritte/' . $auftrittId])) ?>"><?= e($e['titel']) ?></a>
          <button type="button" class="knopf klein-knopf" data-entfernen aria-label="Titel entfernen">✕</button>
        </div>
        <small class="<?= $e['komponisten'] ? 'gedimmt' : 'warn-text' ?>"><?= e(Werke::komponistenText($e['komponisten']) ?: 'Komponist fehlt') ?></small>
        <div class="eintrag-felder">
          <label>Dauer
            <input type="text" inputmode="decimal" value="<?= e(Dauer::format($e['dauer_sek'] === null ? null : (int)$e['dauer_sek'])) ?>"
                   data-feld="dauer" placeholder="mm:ss" maxlength="8" autocomplete="off">
          </label>
          <label>Mitw.
            <input type="number" inputmode="numeric" min="1" max="99999" value="<?= e($e['mitwirkende']) ?>" data-feld="mitwirkende">
          </label>
          <label>Abrechnung
            <select data-feld="abgerechnet"><?= optionen(Status::EINTRAG, $e['abgerechnet']) ?></select>
          </label>
        </div>
      </div>
    </li>
  <?php endforeach ?>
</ol>
<?php endif ?>
