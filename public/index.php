<?php
declare(strict_types=1);

// Lokaler Entwicklungsserver (php -S): statische Dateien direkt ausliefern
if (PHP_SAPI === 'cli-server') {
    $datei = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($datei !== __FILE__ && is_file($datei)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

App::ausfuehren();
