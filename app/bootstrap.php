<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
const APP_NAME = 'Gigbook';
const APP_VERSION = '1.0.0';

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');

spl_autoload_register(static function (string $klasse): void {
    foreach (['lib', 'controllers'] as $ordner) {
        $datei = __DIR__ . "/$ordner/$klasse.php";
        if (is_file($datei)) {
            require $datei;
            return;
        }
    }
});

require __DIR__ . '/helpers.php';

Config::laden();
