<?php /** @var ?string $fehler @var bool $konfiguriert */ ?>
<div class="login">
  <h1><?= APP_NAME ?></h1>
  <?php if (!$konfiguriert): ?>
    <div class="hinweis warnung">
      <p><strong>Noch kein Passwort eingerichtet.</strong></p>
      <p>Erzeuge einen Passwort-Hash mit <code>php tools/passwort-hash.php</code> und trage ihn in
      <code>config.php</code> (Feld <code>password_hash</code>) oder in die Umgebungsvariable
      <code>APP_PASSWORD_HASH</code> ein. Details stehen im README.</p>
    </div>
  <?php else: ?>
    <form method="post" action="<?= e(url('/login')) ?>" class="karte">
      <?php if ($fehler): ?><div class="hinweis fehler" role="alert"><?= e($fehler) ?></div><?php endif ?>
      <label>Passwort
        <input type="password" name="passwort" autocomplete="current-password" required autofocus>
      </label>
      <button type="submit" class="knopf primaer breit">Anmelden</button>
    </form>
  <?php endif ?>
</div>
