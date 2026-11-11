<?php /** @var array $auftritte @var array $jahre @var ?int $jahr @var string $filter */
$filterNamen = ['' => 'Alle', 'kommend' => 'Kommende', 'setlist' => 'Setlist offen', 'abrechnung' => 'Abrechnung offen'];
?>
<div class="seitenkopf">
  <h1>Auftritte</h1>
  <a class="knopf primaer" href="<?= e(url('/auftritte/neu')) ?>">+ Auftritt</a>
</div>

<nav class="reiter" aria-label="Filter">
  <?php foreach ($filterNamen as $wert => $name): ?>
    <a href="<?= e(url('/auftritte', ['filter' => $wert, 'jahr' => $jahr])) ?>" class="<?= $filter === $wert ? 'aktiv' : '' ?>"><?= e($name) ?></a>
  <?php endforeach ?>
</nav>
<?php if (count($jahre) > 1): ?>
<nav class="reiter klein" aria-label="Jahr">
  <a href="<?= e(url('/auftritte', ['filter' => $filter])) ?>" class="<?= $jahr === null ? 'aktiv' : '' ?>">Alle Jahre</a>
  <?php foreach ($jahre as $j): ?>
    <a href="<?= e(url('/auftritte', ['filter' => $filter, 'jahr' => $j])) ?>" class="<?= $jahr === $j ? 'aktiv' : '' ?>"><?= $j ?></a>
  <?php endforeach ?>
</nav>
<?php endif ?>

<?php if (!$auftritte): ?>
  <p class="leer">Keine Auftritte.</p>
<?php else: ?>
  <ul class="liste karte">
    <?php foreach ($auftritte as $a): $sf = $a['setlist_frist']; $rf = $a['reklamation_frist']; ?>
      <li>
        <a href="<?= e(url('/auftritte/' . $a['id'])) ?>">
          <?= View::teil('_auftritt_kurz', ['a' => $a]) ?>
          <span class="rechts">
            <?php if ($sf && $sf['stufe'] !== 'zukunft'): ?>
              <span class="abzeichen <?= $sf['stufe'] === 'ueberfaellig' ? 'rot' : 'gelb' ?>">Setlist <?= $sf['stufe'] === 'ueberfaellig' ? 'überfällig' : 'offen' ?></span>
            <?php endif ?>
            <?php if ($rf && in_array($rf['stufe'], ['bald', 'abgelaufen'], true)): ?>
              <span class="abzeichen rot">Reklamation <?= $rf['stufe'] === 'abgelaufen' ? 'abgelaufen' : 'bald' ?></span>
            <?php elseif (in_array($a['abrechnung_status'], ['fehlt', 'reklamiert'], true)): ?>
              <span class="abzeichen gelb"><?= e(Status::ABRECHNUNG[$a['abrechnung_status']]) ?></span>
            <?php elseif (in_array($a['abrechnung_status'], ['gefunden', 'erledigt'], true)): ?>
              <span class="abzeichen gruen">abgerechnet</span>
            <?php endif ?>
            <small class="gedimmt"><?= (int)$a['titel_anzahl'] ?> Titel</small>
          </span>
        </a>
      </li>
    <?php endforeach ?>
  </ul>
<?php endif ?>
