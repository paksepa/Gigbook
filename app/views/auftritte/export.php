<?php
/** @var array $a @var array $setlist @var array $pruefung @var bool $vorlageVorhanden */
$id = (int)$a['id'];
?>
<div class="seitenkopf">
  <div>
    <h1>Export</h1>
    <p class="untertitel"><?= e(datum_de($a['datum'], true)) ?> · <?= e($a['venue'] ?: 'ohne Ort') ?></p>
  </div>
  <a class="knopf" href="<?= e(url("/auftritte/$id")) ?>">Zurück</a>
</div>

<section class="karte">
  <h2>GEMA-Setlist (Excel)</h2>
  <p>Ausgefüllte Vorlage <code>Excel-Upload-Template_Light_de.xlsx</code> zum Hochladen im GEMA-Portal.</p>
  <?php foreach ($pruefung['fehler'] as $f): ?><div class="hinweis fehler"><?= e($f) ?></div><?php endforeach ?>
  <?php if ($pruefung['warnungen']): ?>
    <div class="hinweis warnung">
      <strong>Bitte prüfen:</strong>
      <ul><?php foreach ($pruefung['warnungen'] as $w): ?><li><?= e($w) ?></li><?php endforeach ?></ul>
    </div>
  <?php endif ?>
  <?php if (!$vorlageVorhanden): ?>
    <div class="hinweis fehler">Die GEMA-Vorlage fehlt im Ordner <code>vorlage/</code>.</div>
  <?php elseif (!$pruefung['fehler']): ?>
    <a class="knopf primaer" href="<?= e(url("/auftritte/$id/export/gema")) ?>">GEMA-Excel herunterladen</a>
  <?php endif ?>
</section>

<section class="karte">
  <h2>Angaben fürs GEMA-Portal</h2>
  <p class="klein gedimmt">Die Excel-Vorlage enthält nur die Titelliste. Die Veranstaltungsdaten gibst du im Portal ein:</p>
  <dl class="eckdaten">
    <?php foreach (SetlistDokument::kopfzeilen($a) as [$label, $wert]): ?>
      <div><dt><?= e($label) ?></dt><dd class="kopierbar"><?= e($wert) ?></dd></div>
    <?php endforeach ?>
    <div><dt>Titel</dt><dd><?= e(SetlistController::zusammenfassung($setlist)) ?></dd></div>
  </dl>
</section>

<section class="karte">
  <h2>Setlist für Veranstalter</h2>
  <div class="knopfleiste">
    <a class="knopf" href="<?= e(url("/auftritte/$id/export/pdf")) ?>">PDF</a>
    <a class="knopf" href="<?= e(url("/auftritte/$id/export/csv")) ?>">CSV</a>
  </div>
</section>
