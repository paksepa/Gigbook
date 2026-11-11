<?php
/** @var int $jahr @var list<int> $jahre @var array $auswertung */
$euro = [Abrechnungen::class, 'euro'];
$max = max([0.01, ...array_column($auswertung['monate'], 'summe')]);
$gesamt = max(0.01, $auswertung['summe']);
?>
<div class="seitenkopf">
  <div>
    <h1>Abrechnungen <?= $jahr ?></h1>
    <p class="untertitel">Jahresübersicht nach Ausschüttungsterminen</p>
  </div>
  <button type="button" class="knopf nicht-drucken" data-drucken>Drucken / PDF</button>
</div>

<?php if (count($jahre) > 1): ?>
  <nav class="chips nicht-drucken" aria-label="Jahr">
    <?php foreach ($jahre as $j): ?>
      <a class="chip<?= $j === $jahr ? ' aktiv' : '' ?>" href="<?= e(url('/abrechnungen/' . $j)) ?>"><?= $j ?></a>
    <?php endforeach ?>
  </nav>
<?php endif ?>

<div class="nicht-drucken"><?= View::teil('abrechnungen/_upload', ['offen' => false]) ?></div>

<?php foreach ($auswertung['warnungen'] as $w): ?><div class="hinweis warnung"><?= e($w) ?></div><?php endforeach ?>

<section class="karte summenkopf">
  <span class="gedimmt">Ausgeschüttet <?= $jahr ?></span>
  <strong class="betrag-gross"><?= e($euro($auswertung['summe'])) ?></strong>
  <span class="klein gedimmt"><?= count($auswertung['monate']) ?> Ausschüttung<?= count($auswertung['monate']) === 1 ? '' : 'en' ?> · <?= count($auswertung['werke']) ?> Werke</span>
</section>

<section class="karte">
  <h2>Nach Monaten</h2>
  <ul class="liste">
    <?php foreach ($auswertung['monate'] as $m): ?>
      <li>
        <a href="<?= e(url("/abrechnungen/$jahr/" . $m['monat'])) ?>">
          <span class="monat-zeile">
            <strong><?= e($m['name']) ?></strong>
            <span class="balken" aria-hidden="true"><span data-breite="<?= round($m['summe'] / $max * 100, 1) ?>"></span></span>
            <small class="gedimmt"><?= e(implode(' · ', array_keys($m['sparten']))) ?></small>
          </span>
          <span class="betrag"><?= e($euro($m['summe'])) ?></span>
        </a>
      </li>
    <?php endforeach ?>
  </ul>
</section>

<section class="karte">
  <h2>Nach Bereich</h2>
  <ul class="liste kompakt">
    <?php foreach ($auswertung['bereiche'] as $b): ?>
      <li class="zeile-betrag">
        <span>
          <strong><?= e($b['name']) ?></strong>
          <small class="gedimmt"><?= e(implode(' · ', array_keys($b['sparten']))) ?></small>
        </span>
        <span class="betrag"><?= e($euro($b['summe'])) ?> <small class="gedimmt"><?= number_format($b['summe'] / $gesamt * 100, 0, ',', '.') ?> %</small></span>
      </li>
    <?php endforeach ?>
  </ul>
</section>

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

