<?php
/** @var int $jahr @var int $monat @var array $monate @var array $auswertung */
$euro = [Abrechnungen::class, 'euro'];
$zeitraum = static function (?string $von, ?string $bis): string {
    if (!$von) {
        return '';
    }
    return $bis && $bis !== $von ? datum_de($von) . ' – ' . datum_de($bis) : datum_de($von);
};
?>
<div class="seitenkopf">
  <div>
    <h1><?= e(Abrechnungen::MONATE[$monat]) ?> <?= $jahr ?></h1>
    <p class="untertitel">Ausschüttung <?= e(implode(', ', array_map('datum_de', $auswertung['termine']))) ?></p>
  </div>
  <a class="knopf nicht-drucken" href="<?= e(url('/abrechnungen/' . $jahr)) ?>">Jahr <?= $jahr ?></a>
</div>

<nav class="chips nicht-drucken" aria-label="Monat">
  <?php foreach ($monate as $m): ?>
    <a class="chip<?= $m['monat'] === $monat ? ' aktiv' : '' ?>" href="<?= e(url("/abrechnungen/$jahr/" . $m['monat'])) ?>"><?= e(mb_substr(Abrechnungen::MONATE[$m['monat']], 0, 3)) ?></a>
  <?php endforeach ?>
</nav>

<?php foreach ($auswertung['warnungen'] as $w): ?><div class="hinweis warnung"><?= e($w) ?></div><?php endforeach ?>

<section class="karte summenkopf">
  <span class="gedimmt">Gesamt</span>
  <strong class="betrag-gross"><?= e($euro($auswertung['summe'])) ?></strong>
  <span class="klein gedimmt"><?= e(implode(' · ', array_map(static fn($b) => $b['name'] . ' ' . $euro($b['summe']), $auswertung['bereiche']))) ?></span>
</section>

<?php foreach ($auswertung['bereiche'] as $b): ?>
  <section class="karte">
    <h2 class="kopf-betrag"><span><?= e($b['name']) ?></span><span><?= e($euro($b['summe'])) ?></span></h2>
    <?php foreach ($b['sparten'] as $s): ?>
      <h3 class="kopf-betrag"><span><?= e($s['sparte']) ?></span><span><?= e($euro((float)$s['betrag'])) ?></span></h3>
      <?php if (!$s['posten']): ?>
        <p class="klein gedimmt">Nur die Summe liegt vor.</p>
      <?php else: ?>
        <ul class="liste kompakt posten">
          <?php foreach ($s['posten'] as $p): ?>
            <li class="zeile-betrag">
              <span>
                <?php if ($p['werk_id']): ?><a href="<?= e(url('/werke/' . $p['werk_id'])) ?>"><strong><?= e($p['titel']) ?></strong></a>
                <?php else: ?><strong><?= e($p['titel']) ?></strong><?php endif ?>
                <small class="gedimmt"><?= e(implode(' · ', array_filter([
                    $zeitraum($p['nutzung_von'], $p['nutzung_bis']),
                    (string)$p['beschreibung'],
                    $p['anteil'] !== null && (float)$p['anteil'] < 100 ? 'Anteil ' . number_format((float)$p['anteil'], 0, ',', '.') . ' %' : '',
                ]))) ?></small>
                <?php if ($p['auftritt']): ?>
                  <small><a href="<?= e(url('/auftritte/' . $p['auftritt']['id'])) ?>">→ Auftritt <?= e(datum_de($p['auftritt']['datum'])) ?><?= $p['auftritt']['venue'] ? ' · ' . e($p['auftritt']['venue']) : '' ?></a></small>
                <?php endif ?>
              </span>
              <span class="betrag"><?= e($euro((float)$p['betrag'])) ?></span>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    <?php endforeach ?>
  </section>
<?php endforeach ?>

<section class="karte">
  <h2>Nach Werk</h2>
  <ul class="liste kompakt">
    <?php foreach ($auswertung['werke'] as $w): ?>
      <li class="zeile-betrag">
        <span>
          <?php if ($w['werk_id']): ?><a href="<?= e(url('/werke/' . $w['werk_id'])) ?>"><strong><?= e($w['titel']) ?></strong></a>
          <?php else: ?><strong><?= e($w['titel']) ?></strong><?php endif ?>
          <small class="gedimmt"><?= e(str_replace(',', ' · ', (string)$w['sparten'])) ?></small>
        </span>
        <span class="betrag"><?= e($euro($w['summe'])) ?></span>
      </li>
    <?php endforeach ?>
  </ul>
</section>

<form method="post" action="<?= e(url("/abrechnungen/$jahr/$monat/loeschen")) ?>" class="gefahrenzone nicht-drucken"
      data-bestaetigen="Alle Abrechnungen für <?= e(Abrechnungen::MONATE[$monat]) ?> <?= $jahr ?> löschen? Du kannst sie danach neu hochladen.">
  <?= csrf_feld() ?>
  <button type="submit" class="knopf gefahr">Diesen Monat löschen</button>
</form>
