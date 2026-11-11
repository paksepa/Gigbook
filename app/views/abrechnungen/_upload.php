<?php /** @var bool $offen */ ?>
<details class="karte hochladen"<?= !empty($offen) ? ' open' : '' ?>>
  <summary><strong>Abrechnungen hochladen</strong></summary>
  <p class="klein gedimmt">Im GEMA-Portal die Abrechnungen als CSV herunterladen und hier alle auf einmal auswählen
    (oder das ZIP-Archiv). Erkannt werden Summen (<code>SUM_…</code>), Kompaktaufstellung (<code>KMP_…</code>) und die
    Detaildateien der Sparten (<code>U_…</code>, <code>R_…</code>, <code>MOD.S_…</code> usw.). Mehrfaches Hochladen
    schadet nicht: Vorhandene Abrechnungen werden ersetzt, nichts wird doppelt gezählt.</p>
  <form method="post" action="<?= e(url('/abrechnungen/import')) ?>" enctype="multipart/form-data" class="formular">
    <?= csrf_feld() ?>
    <input type="file" name="dateien[]" accept=".csv,.zip,text/csv,application/zip" multiple required>
    <div class="aktionen"><button type="submit" class="knopf primaer">Hochladen</button></div>
  </form>
</details>
