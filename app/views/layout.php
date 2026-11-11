<?php
/** @var string $inhalt @var string $titel @var string $bereich */
$angemeldet = Auth::angemeldet();
$flash = $angemeldet ? Auth::flash() : null;
$navi = [
    'start' => ['/', 'Übersicht', 'M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
    'auftritte' => ['/auftritte', 'Auftritte', 'M8 2v4M16 2v4M3 9h18M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z'],
    'werke' => ['/werke', 'Werke', 'M9 18V5l12-2v13M9 18a3 3 0 11-6 0 3 3 0 016 0zM21 16a3 3 0 11-6 0 3 3 0 016 0z'],
    'abrechnungen' => ['/abrechnungen', 'Abrechnung', 'M18 7.5A7 7 0 1 0 18 16.5M4 10.5h10M4 13.5h10'],
    'einstellungen' => ['/einstellungen', 'Mehr', 'M4 6h16M4 12h16M4 18h16'],
];
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1d3557">
<meta name="basis" content="<?= e(basis_pfad()) ?>">
<?php if ($angemeldet): ?><meta name="csrf" content="<?= e(Auth::csrfToken()) ?>"><?php endif ?>
<title><?= e($titel !== '' ? $titel . ' · ' . APP_NAME : APP_NAME) ?></title>
<link rel="icon" href="<?= e(url('/favicon.ico')) ?>" sizes="32x32">
<link rel="icon" href="<?= e(url('/assets/icon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(url('/assets/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<meta name="apple-mobile-web-app-title" content="<?= APP_NAME ?>">
<link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>?v=<?= @filemtime(APP_ROOT . '/public/assets/app.css') ?: APP_VERSION ?>">
<script src="<?= e(url('/assets/app.js')) ?>?v=<?= @filemtime(APP_ROOT . '/public/assets/app.js') ?: APP_VERSION ?>" defer></script>
</head>
<body class="<?= $angemeldet ? 'mit-navi' : '' ?>">
<?php if ($angemeldet): ?>
<header class="kopf">
  <a class="marke" href="<?= e(url('/')) ?>"><?= APP_NAME ?></a>
  <nav class="navi" aria-label="Hauptnavigation">
    <?php foreach ($navi as $schluessel => [$pfad, $label, $icon]): ?>
      <a href="<?= e(url($pfad)) ?>" class="<?= $bereich === $schluessel ? 'aktiv' : '' ?>"<?= $bereich === $schluessel ? ' aria-current="page"' : '' ?>>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $icon ?>"/></svg>
        <span><?= e($label) ?></span>
      </a>
    <?php endforeach ?>
  </nav>
</header>
<?php endif ?>
<main class="inhalt">
<?php if ($flash): ?><div class="hinweis erfolg" role="status"><?= e($flash) ?></div><?php endif ?>
<?= $inhalt ?>
</main>
</body>
</html>
