<?php
declare(strict_types=1);

final class View
{
    /** Vollständige Seite mit Layout ausgeben. */
    public static function seite(string $vorlage, array $daten = [], string $titel = '', string $bereich = ''): void
    {
        $inhalt = self::teil($vorlage, $daten);
        echo self::teil('layout', ['inhalt' => $inhalt, 'titel' => $titel, 'bereich' => $bereich]);
    }

    /** Einzelnes Template rendern und als String zurückgeben. */
    public static function teil(string $vorlage, array $daten = []): string
    {
        $datei = APP_ROOT . '/app/views/' . $vorlage . '.php';
        extract($daten, EXTR_SKIP);
        ob_start();
        try {
            require $datei;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }
}
