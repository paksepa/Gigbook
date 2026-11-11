<?php
/** Eine Zeile mit Auftrittsdatum und Ort. @var array $a */
?>
<span class="auftritt-kurz">
  <strong><?= e(datum_de($a['datum'], true)) ?></strong>
  <span><?= e(implode(', ', array_filter([$a['venue'], $a['stadt']])) ?: 'ohne Ort') ?></span>
  <?php if (!empty($a['ensemble'])): ?><small><?= e($a['ensemble']) ?></small><?php endif ?>
</span>
