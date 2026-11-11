<?php
/** @var array $a @var ?int $vorlageId @var array $vorlagen @var ?string $fehler @var array $ensembles @var array $staedte @var array $veranstalter */
$id = $a['id'] ?? null;
$w = static fn(string $f) => $a[$f] ?? '';
?>
<div class="seitenkopf">
  <h1><?= $id ? 'Auftritt bearbeiten' : 'Neuer Auftritt' ?></h1>
  <a class="knopf" href="<?= e(url($id ? "/auftritte/$id" : '/auftritte')) ?>">Zurück</a>
</div>

<form method="post" action="<?= e(url($id ? "/auftritte/$id" : '/auftritte')) ?>" class="karte formular">
  <?= csrf_feld() ?>
  <?php if ($fehler): ?><div class="hinweis fehler" role="alert"><?= e($fehler) ?></div><?php endif ?>

  <div class="zwei-spalten">
    <label>Datum *
      <input type="date" name="datum" value="<?= e($w('datum')) ?>" required>
    </label>
    <label>Uhrzeit
      <input type="time" name="uhrzeit" value="<?= e($w('uhrzeit')) ?>">
    </label>
  </div>

  <label>Ort / Venue
    <input type="text" name="venue" value="<?= e($w('venue')) ?>" maxlength="200" autocomplete="off">
  </label>
  <label>Stadt
    <input type="text" name="stadt" value="<?= e($w('stadt')) ?>" list="staedte" maxlength="100" autocomplete="off">
  </label>
  <datalist id="staedte"><?php foreach ($staedte as $s): ?><option value="<?= e($s) ?>"><?php endforeach ?></datalist>

  <div class="zwei-spalten">
    <label>Ensemble / Band
      <input type="text" name="ensemble" value="<?= e($w('ensemble')) ?>" list="ensembles" maxlength="200" autocomplete="off">
    </label>
    <label>Mitwirkende
      <input type="number" name="anzahl_mitwirkende" value="<?= e($w('anzahl_mitwirkende')) ?>" min="1" max="99999" inputmode="numeric">
    </label>
  </div>
  <datalist id="ensembles"><?php foreach ($ensembles as $s): ?><option value="<?= e($s) ?>"><?php endforeach ?></datalist>

  <div class="zwei-spalten">
    <label>Veranstalter
      <input type="text" name="veranstalter_name" value="<?= e($w('veranstalter_name')) ?>" list="veranstalter" maxlength="200" autocomplete="off"
             data-kontakt-ziel="veranstalter_kontakt">
    </label>
    <label>Kontakt
      <input type="text" name="veranstalter_kontakt" value="<?= e($w('veranstalter_kontakt')) ?>" maxlength="200" placeholder="E-Mail / Telefon" autocomplete="off">
    </label>
  </div>
  <datalist id="veranstalter"><?php foreach ($veranstalter as $v): ?><option value="<?= e($v['name']) ?>" data-kontakt="<?= e($v['kontakt']) ?>"><?php endforeach ?></datalist>

  <?php if (!$id): ?>
    <label>Setlist übernehmen von
      <select name="vorlage_id">
        <option value="">– leere Setlist –</option>
        <?php foreach ($vorlagen as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= (int)$v['id'] === $vorlageId ? 'selected' : '' ?>>
            <?= e(datum_de($v['datum']) . ' · ' . ($v['venue'] ?: 'ohne Ort') . ' (' . $v['titel_anzahl'] . ' Titel)') ?>
          </option>
        <?php endforeach ?>
      </select>
    </label>
  <?php endif ?>

  <label>Notizen
    <textarea name="notizen" rows="3"><?= e($w('notizen')) ?></textarea>
  </label>

  <?php if ($id): ?><p class="klein gedimmt">Ändert sich die Anzahl Mitwirkender, werden alle Setlist-Einträge mit der bisherigen Anzahl angepasst.</p><?php endif ?>

  <div class="aktionen">
    <button type="submit" class="knopf primaer"><?= $id ? 'Speichern' : 'Anlegen & Setlist bearbeiten' ?></button>
  </div>
</form>
