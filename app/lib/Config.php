<?php
declare(strict_types=1);

/**
 * Konfiguration: Umgebungsvariablen haben Vorrang vor config.php.
 * config.php liegt außerhalb des öffentlichen Verzeichnisses (siehe config.example.php).
 */
final class Config
{
    private static array $werte = [];

    public static function laden(array $ueberschreiben = []): void
    {
        $datei = APP_ROOT . '/config.php';
        $c = is_file($datei) ? (require $datei) : [];
        if (!is_array($c)) {
            $c = [];
        }

        $proxies = self::env('APP_TRUSTED_PROXIES');
        self::$werte = array_merge([
            'password_hash' => self::env('APP_PASSWORD_HASH') ?? (string)($c['password_hash'] ?? ''),
            'data_dir' => self::env('APP_DATA_DIR') ?? (string)($c['data_dir'] ?? APP_ROOT . '/data'),
            'vorlage' => self::env('APP_GEMA_VORLAGE')
                ?? (string)($c['gema_vorlage'] ?? APP_ROOT . '/vorlage/Excel-Upload-Template_Light_de.xlsx'),
            'session_days' => max(1, (int)(self::env('APP_SESSION_DAYS') ?? ($c['session_days'] ?? 30))),
            'trusted_proxies' => $proxies !== null
                ? array_values(array_filter(array_map('trim', explode(',', $proxies))))
                : array_values((array)($c['trusted_proxies'] ?? [])),
            'werksuche' => filter_var(self::env('APP_WERKSUCHE') ?? ($c['werksuche'] ?? false), FILTER_VALIDATE_BOOL),
        ], $ueberschreiben);
    }

    public static function get(string $schluessel): mixed
    {
        return self::$werte[$schluessel] ?? null;
    }

    private static function env(string $name): ?string
    {
        foreach ([getenv($name), $_SERVER[$name] ?? false, $_SERVER['REDIRECT_' . $name] ?? false] as $wert) {
            if (is_string($wert) && $wert !== '') {
                return $wert;
            }
        }
        return null;
    }
}
