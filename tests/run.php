<?php
declare(strict_types=1);

/**
 * Minimaler Test-Runner ohne Abhängigkeiten.
 * Aufruf: php tests/run.php   (lokal unter Windows ggf. mit -d extension=pdo_sqlite)
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$GLOBALS['tests'] = [];
$GLOBALS['fehlgeschlagen'] = 0;

function test(string $name, callable $fn): void
{
    $GLOBALS['tests'][] = [$name, $fn];
}

function gleich(mixed $erwartet, mixed $tatsaechlich, string $hinweis = ''): void
{
    if ($erwartet !== $tatsaechlich) {
        throw new AssertionError(
            ($hinweis ? "$hinweis: " : '') . 'erwartet ' . var_export($erwartet, true) . ', erhalten ' . var_export($tatsaechlich, true)
        );
    }
}

function wahr(bool $bedingung, string $hinweis): void
{
    if (!$bedingung) {
        throw new AssertionError($hinweis);
    }
}

function wirft(string $klasse, callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $klasse) {
            return;
        }
        throw new AssertionError("erwartet $klasse, erhalten " . $e::class . ': ' . $e->getMessage());
    }
    throw new AssertionError("erwartet $klasse, aber nichts geworfen");
}

/** Frische In-Memory-Datenbank für jeden Test. */
function frische_db(): void
{
    Db::verwenden(Db::oeffnen(':memory:'));
    Einstellungen::cacheLeeren();
}

foreach (glob(__DIR__ . '/*Test.php') as $datei) {
    require $datei;
}

$start = microtime(true);
foreach ($GLOBALS['tests'] as [$name, $fn]) {
    frische_db();
    try {
        $fn();
        echo "  ✓ $name\n";
    } catch (Throwable $e) {
        $GLOBALS['fehlgeschlagen']++;
        echo "  ✗ $name\n      " . $e->getMessage() . "\n      " . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}
$anzahl = count($GLOBALS['tests']);
printf("\n%d Tests, %d fehlgeschlagen (%.2fs)\n", $anzahl, $GLOBALS['fehlgeschlagen'], microtime(true) - $start);
exit($GLOBALS['fehlgeschlagen'] > 0 ? 1 : 0);
