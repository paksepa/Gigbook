<?php
/** @var array $werke @var string $suche @var bool $nurEigene @var ?int $kategorie @var array $kategorien @var bool $auswahl */
$filter = ['q' => $suche, 'eigene' => $nurEigene ? '1' : null, 'kat' => $kategorie === null ? null : (string)$kategorie];
$aktiveKategorie = null;
foreach ($kategorien as $k) {
    if ($k['id'] === $kategorie) {
        $aktiveKategorie = $k;
    }
}
?>
<div class="seitenkopf">
  <h1>Werke</h1>
  <a class="knopf primaer" href="<?= e(url('/werke/neu', ['kat' => $kategorie ?: null])) ?>">+ Werk</a>
</div>

<form method="get" action="<?= e(url('/werke')) ?>" class="suchleiste">
  <input type="search" name="q" value="<?= e($suche) ?>" placeholder="Titel, Komponist, Werknummer, Instrument, Kategorie" aria-label="Suche">
  <?php if ($kategorie !== null): ?><input type="hidden" name="kat" value="<?= $kategorie ?>"><?php endif ?>
  <?php if ($auswahl): ?><input type="hidden" name="auswahl" value="1"><?php endif ?>
  <label class="schalter"><input type="checkbox" name="eigene" value="1" <?= $nurEigene ? 'checked' : '' ?> data-auto-submit> nur eigene</label>
  <button type="submit" class="knopf">Suchen</button>
</form>

<?php if ($kategorien): ?>
  <nav class="chips" aria-label="Kategorien">
    <a class="chip<?= $kategorie === null ? ' aktiv' : '' ?>" href="<?= e(url('/werke', ['kat' => null] + $filter + ['auswahl' => $auswahl ? '1' : null])) ?>">Alle</a>
    <?php foreach ($kategorien as $k): ?>
      <a class="chip<?= $kategorie === $k['id'] ? ' aktiv' : '' ?>" href="<?= e(url('/werke', ['kat' => (string)$k['id']] + $filter + ['auswahl' => $auswahl ? '1' : null])) ?>"><?= e($k['name']) ?> <small><?= $k['anzahl'] ?></small></a>
    <?php endforeach ?>
    <a class="chip<?= $kategorie === 0 ? ' aktiv' : '' ?>" href="<?= e(url('/werke', ['kat' => '0'] + $filter + ['auswahl' => $auswahl ? '1' : null])) ?>">ohne Kategorie</a>
  </nav>
<?php endif ?>

<?php if (!$werke): ?>
  <p class="leer"><?= $suche !== '' || $nurEigene || $kategorie !== null ? 'Keine Treffer.' : 'Noch keine Werke angelegt.' ?></p>
  <?php if ($suche === '' && !$nurEigene && $kategorie === null): ?>
    <p class="zentriert"><a class="knopf" href="<?= e(url('/einstellungen#werkverzeichnis')) ?>">GEMA-Werkverzeichnis importieren</a></p>
  <?php endif ?>
<?php else: ?>
  <div class="listenkopf">
    <p class="klein gedimmt"><?= count($werke) ?> Werk<?= count($werke) === 1 ? '' : 'e' ?><?= $aktiveKategorie ? ' in „' . e($aktiveKategorie['name']) . '“' : ($kategorie === 0 ? ' ohne Kategorie' : '') ?></p>
    <?php if ($auswahl): ?>
      <a class="knopf klein-knopf" href="<?= e(url('/werke', $filter)) ?>">Fertig</a>
    <?php else: ?>
      <a class="knopf klein-knopf" href="<?= e(url('/werke', $filter + ['auswahl' => '1'])) ?>">Einsortieren …</a>
    <?php endif ?>
  </div>

  <?php if ($auswahl): ?>
    <form method="post" action="<?= e(url('/werke/kategorien')) ?>" id="einsortieren">
      <?= csrf_feld() ?>
      <input type="hidden" name="q" value="<?= e($suche) ?>">
      <input type="hidden" name="eigene" value="<?= $nurEigene ? '1' : '' ?>">
      <input type="hidden" name="kat" value="<?= $kategorie === null ? '' : $kategorie ?>">
      <label class="schalter alle-waehlen"><input type="checkbox" data-alle-waehlen="werke[]"> Alle <?= count($werke) ?> auswählen</label>
  <?php endif ?>

  <?php
  // Ohne Kategorien: eine einfache Liste; sonst ein Abschnitt je Kategorie
  $gruppen = $kategorien ? Werke::gruppiert($werke, $kategorien) : [];
  if ($kategorie !== null) {
      // gefiltert: nur den gewählten Abschnitt zeigen, nicht die übrigen Kategorien dieser Werke
      $gruppen = array_values(array_filter($gruppen, static fn($g) => $g['id'] === ($kategorie ?: null)));
  }
  $abschnitte = $gruppen ?: [['id' => null, 'name' => '', 'werke' => $werke]];
  ?>
  <?php foreach ($abschnitte as $g): ?>
    <?php if ($gruppen): ?>
      <details class="gruppe"<?= $suche !== '' || $kategorie !== null ? ' open' : '' ?>>
        <summary><h2><?= e($g['name']) ?></h2><span class="gedimmt klein"><?= count($g['werke']) ?></span></summary>
    <?php endif ?>
    <ul class="liste karte<?= $auswahl ? ' auswahl' : '' ?>">
      <?php foreach ($g['werke'] as $w): ?>
        <li>
            <?php if ($auswahl): ?>
              <label class="zeile">
                <input type="checkbox" name="werke[]" value="<?= (int)$w['id'] ?>">
            <?php else: ?>
              <a href="<?= e(url('/werke/' . $w['id'])) ?>">
            <?php endif ?>
              <span class="werk-kurz">
                <strong><?= e($w['titel']) ?></strong>
                <small><?= e(Werke::komponistenText($w['komponisten']) ?: '– kein Komponist –') ?></small>
                <?php if ($w['besetzung']): ?><small class="besetzung"><?= e($w['besetzung']) ?></small><?php endif ?>
                <?php $weitere = array_filter($w['kategorien'], static fn($k) => $k['id'] !== $g['id']); ?>
                <?php if ($weitere && $gruppen): ?>
                  <span class="kategorien"><small>auch in</small><?php foreach ($weitere as $k): ?><span class="abzeichen kategorie"><?= e($k['name']) ?></span><?php endforeach ?></span>
                <?php endif ?>
              </span>
              <span class="rechts">
                <?php if ($w['eigenes_werk']): ?><span class="abzeichen">eigen</span><?php endif ?>
                <?php if (!$w['gema_werknummer']): ?><span class="abzeichen grau" title="Keine GEMA-Werknummer">ohne Nr.</span><?php endif ?>
                <?php if (!$w['komponisten']): ?><span class="abzeichen rot">Komponist fehlt</span><?php endif ?>
                <small class="gedimmt"><?= (int)$w['auffuehrungen'] ?>×</small>
              </span>
            <?php if ($auswahl): ?></label><?php else: ?></a><?php endif ?>
          </li>
      <?php endforeach ?>
    </ul>
    <?php if ($gruppen): ?></details><?php endif ?>
  <?php endforeach ?>

  <?php if ($auswahl): ?>
      <div class="auswahlleiste">
        <p class="klein"><strong data-auswahl-anzahl>0</strong> ausgewählt</p>
        <div class="auswahlleiste-felder">
          <select name="kategorie_id" aria-label="Kategorie" data-zeigt="neu:neue-kategorie-feld">
            <option value="neu" selected>+ Neue Kategorie …</option>
            <?php foreach ($kategorien as $k): ?>
              <option value="<?= $k['id'] ?>"><?= e($k['name']) ?></option>
            <?php endforeach ?>
          </select>
          <input type="text" name="neue_kategorie" id="neue-kategorie-feld" placeholder="Name, z. B. Bandname" maxlength="80" autocomplete="off" aria-label="Neue Kategorie">
        </div>
        <div class="knopfleiste">
          <button type="submit" name="aktion" value="hinzufuegen" class="knopf primaer">Hinzufügen</button>
          <?php if ($kategorien): ?><button type="submit" name="aktion" value="entfernen" class="knopf">Entfernen</button><?php endif ?>
        </div>
      </div>
    </form>
  <?php endif ?>
<?php endif ?>
