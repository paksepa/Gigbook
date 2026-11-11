<?php /** @var int $status @var string $meldung */ ?>
<div class="karte">
  <h1><?= $status === 404 ? 'Nicht gefunden' : ($status === 422 ? 'Eingabe prüfen' : 'Fehler') ?></h1>
  <p><?= e($meldung) ?></p>
  <p><a class="knopf" href="<?= e(url('/')) ?>">Zur Übersicht</a></p>
</div>
