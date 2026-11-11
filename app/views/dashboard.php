<?php
/**
 * @var array $setlists @var array $abrechnung @var array $reklamation @var array $kommende
 * @var array $pro_jahr @var array $pro_werk @var ?int $statistik_jahr @var array $einstellungen
 */
$ueberfaellig = count(array_filter($setlists, static fn($a) => $a['frist']['stufe'] === 'ueberfaellig'));
$pruefbar = count(array_filter($abrechnung, static fn($a) => $a['ausschuettung_vorbei']));
$maxWerk = $pro_werk ? max(array_column($pro_werk, 'anzahl')) : 1;
?>
<div class="seitenkopf">
  <h1>Übersicht</h1>
  <a class="knopf primaer" href="<?= e(url('/auftritte/neu')) ?>">+ Auftritt</a>
</div>

<div class="kacheln">
  <a class="kachel <?= $ueberfaellig ? 'rot' : '' ?>" href="#setlists">
    <b><?= count($setlists) ?></b><span>Setlists offen</span>
    <?php if ($ueberfaellig): ?><small><?= $ueberfaellig ?> überfällig</small><?php endif ?>
  </a>
  <a class="kachel <?= $pruefbar ? 'gelb' : '' ?>" href="#abrechnung">
    <b><?= count($abrechnung) ?></b><span>Abrechnung ungeprüft</span>
    <?php if ($pruefbar): ?><small><?= $pruefbar ?> jetzt prüfbar</small><?php endif ?>
  </a>
  <a class="kachel <?= $reklamation ? 'rot' : '' ?>" href="#reklamation">
    <b><?= count($reklamation) ?></b><span>Reklamationsfristen</span>
  </a>
</div>

<section class="karte" id="setlists">
  <h2>Setlists einreichen</h2>
  <p class="klein gedimmt">Frist: <?= $einstellungen['frist_setlist_tage'] ?> Tage nach dem Auftritt.</p>
  <?php if (!$setlists): ?>
    <p class="leer">Alles eingereicht. 🎉</p>
  <?php else: ?>
    <ul class="liste">
      <?php foreach ($setlists as $a): $f = $a['frist']; ?>
        <li>
          <a href="<?= e(url('/auftritte/' . $a['id'])) ?>">
            <?= View::teil('_auftritt_kurz', ['a' => $a]) ?>
            <span class="marke-frist <?= $f['stufe'] ?>">
              <?= $f['stufe'] === 'ueberfaellig' ? 'überfällig ' . e(tage_text($f['tage'])) : 'fällig ' . e(tage_text($f['tage'])) ?>
              <small><?= e(datum_de($f['faellig']->format('Y-m-d'))) ?></small>
            </span>
          </a>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</section>

<section class="karte" id="abrechnung">
  <h2>Abrechnung prüfen</h2>
  <p class="klein gedimmt">Vergangene Auftritte mit Status „Nicht geprüft“. Trage den Ausschüttungstermin ein, damit die Reklamationsfrist berechnet wird.</p>
  <?php if (!$abrechnung): ?>
    <p class="leer">Nichts offen.</p>
  <?php else: ?>
    <ul class="liste">
      <?php foreach ($abrechnung as $a): ?>
        <li>
          <a href="<?= e(url('/auftritte/' . $a['id'])) ?>#status">
            <?= View::teil('_auftritt_kurz', ['a' => $a]) ?>
            <?php if ($a['ausschuettungstermin'] === null): ?>
              <span class="marke-frist offen">Ausschüttung?</span>
            <?php elseif ($a['ausschuettung_vorbei']): ?>
              <span class="marke-frist bald">prüfen<small>Ausschüttung <?= e(datum_de($a['ausschuettungstermin'])) ?></small></span>
            <?php else: ?>
              <span class="marke-frist zukunft">ab <?= e(datum_de($a['ausschuettungstermin'])) ?></span>
            <?php endif ?>
          </a>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</section>

<section class="karte" id="reklamation">
  <h2>Reklamationsfristen</h2>
  <p class="klein gedimmt"><?= $einstellungen['frist_reklamation_monate'] ?> Monate nach Ausschüttung · angezeigt ab <?= $einstellungen['vorlauf_reklamation_tage'] ?> Tagen vorher, für „Nicht geprüft“ und „Fehlt“.</p>
  <?php if (!$reklamation): ?>
    <p class="leer">Keine Frist in Sicht.</p>
  <?php else: ?>
    <ul class="liste">
      <?php foreach ($reklamation as $a): $f = $a['frist']; ?>
        <li>
          <a href="<?= e(url('/auftritte/' . $a['id'])) ?>#status">
            <?= View::teil('_auftritt_kurz', ['a' => $a]) ?>
            <span class="marke-frist <?= $f['stufe'] === 'abgelaufen' ? 'ueberfaellig' : 'bald' ?>">
              <?= $f['stufe'] === 'abgelaufen' ? 'abgelaufen' : 'endet ' . e(tage_text($f['tage'])) ?>
              <small><?= e(datum_de($f['bis']->format('Y-m-d'))) ?> · <?= e(Status::ABRECHNUNG[$a['abrechnung_status']]) ?></small>
            </span>
          </a>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</section>

<?php if ($kommende): ?>
<section class="karte">
  <h2>Nächste Auftritte</h2>
  <ul class="liste">
    <?php foreach ($kommende as $a): ?>
      <li><a href="<?= e(url('/auftritte/' . $a['id'])) ?>"><?= View::teil('_auftritt_kurz', ['a' => $a]) ?></a></li>
    <?php endforeach ?>
  </ul>
</section>
<?php endif ?>

<section class="karte" id="statistik">
  <h2>Statistik</h2>
  <?php if (!$pro_jahr): ?>
    <p class="leer">Noch keine vergangenen Auftritte.</p>
  <?php else: ?>
    <h3>Pro Jahr</h3>
    <table class="tabelle">
      <thead><tr><th>Jahr</th><th class="zahl">Auftritte</th><th class="zahl">Aufführungen</th><th class="zahl">davon eigene</th></tr></thead>
      <tbody>
        <?php foreach ($pro_jahr as $j): ?>
          <tr>
            <td><a href="<?= e(url('/', ['jahr' => $j['jahr']])) ?>#statistik"><?= e($j['jahr']) ?></a></td>
            <td class="zahl"><?= (int)$j['auftritte'] ?></td>
            <td class="zahl"><?= (int)$j['auffuehrungen'] ?></td>
            <td class="zahl"><?= (int)$j['eigene'] ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>

    <h3>Aufführungen pro Werk <?= $statistik_jahr ? e("($statistik_jahr)") : '(gesamt)' ?></h3>
    <?php if ($statistik_jahr): ?><p class="klein"><a href="<?= e(url('/')) ?>#statistik">Alle Jahre anzeigen</a></p><?php endif ?>
    <?php if (!$pro_werk): ?>
      <p class="leer">Keine Aufführungen.</p>
    <?php else: ?>
      <ul class="balken">
        <?php foreach ($pro_werk as $w): ?>
          <li>
            <a href="<?= e(url('/werke/' . $w['id'])) ?>"><?= e($w['titel']) ?><?= $w['eigenes_werk'] ? ' <span class="abzeichen">eigen</span>' : '' ?></a>
            <progress max="<?= (int)$maxWerk ?>" value="<?= (int)$w['anzahl'] ?>"></progress>
            <b><?= (int)$w['anzahl'] ?></b>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  <?php endif ?>
</section>
