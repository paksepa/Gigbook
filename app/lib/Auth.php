<?php
declare(strict_types=1);

/**
 * Login für genau einen Nutzer.
 * - Passwort-Hash (password_hash) aus APP_PASSWORD_HASH bzw. config.php
 * - Sitzung: zufälliges Token im HttpOnly-Cookie, in der DB nur als SHA-256 gespeichert
 * - Rate-Limit: Fehlversuche pro IP und global in einem Zeitfenster
 */
final class Auth
{
    public const COOKIE = 'gigbook_sitzung';
    public const FENSTER_SEK = 900;      // 15 Minuten
    public const MAX_PRO_IP = 5;
    public const MAX_GESAMT = 30;

    private static ?array $sitzung = null;
    private static bool $geladen = false;

    public static function passwortKonfiguriert(): bool
    {
        $hash = (string)Config::get('password_hash');
        return $hash !== '' && (password_get_info($hash)['algo'] ?? null) !== null;
    }

    public static function sitzung(): ?array
    {
        if (self::$geladen) {
            return self::$sitzung;
        }
        self::$geladen = true;
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $sitzung = Db::eine(
            'SELECT * FROM sitzung WHERE token_hash = ? AND laeuft_ab > ?',
            [hash('sha256', $token), time()]
        );
        if ($sitzung) {
            // Gleitende Verlängerung, wenn weniger als die Hälfte der Laufzeit übrig ist
            $laufzeit = self::laufzeit();
            if ($sitzung['laeuft_ab'] - time() < $laufzeit / 2) {
                $neu = time() + $laufzeit;
                Db::ausfuehren('UPDATE sitzung SET laeuft_ab = ? WHERE token_hash = ?', [$neu, $sitzung['token_hash']]);
                self::cookieSetzen($token, $neu);
            }
        }
        return self::$sitzung = $sitzung;
    }

    public static function angemeldet(): bool
    {
        return self::sitzung() !== null;
    }

    public static function csrfToken(): string
    {
        return (string)(self::sitzung()['csrf'] ?? '');
    }

    public static function csrfPruefen(mixed $token): bool
    {
        $erwartet = self::csrfToken();
        return $erwartet !== '' && is_string($token) && hash_equals($erwartet, $token);
    }

    /**
     * Prüft das Passwort mit Rate-Limit.
     * @return string|null Fehlermeldung oder null bei Erfolg
     */
    public static function anmelden(string $passwort, string $ip, ?int $jetzt = null): ?string
    {
        if (!self::passwortKonfiguriert()) {
            return 'Es ist noch kein Passwort eingerichtet (siehe README).';
        }
        $jetzt ??= time();
        $grenze = $jetzt - self::FENSTER_SEK;
        Db::ausfuehren('DELETE FROM login_versuch WHERE zeit < ?', [$jetzt - 86400]);

        $proIp = (int)Db::wert('SELECT COUNT(*) FROM login_versuch WHERE ip = ? AND zeit > ?', [$ip, $grenze]);
        if ($proIp >= self::MAX_PRO_IP) {
            $aeltester = (int)Db::wert('SELECT MIN(zeit) FROM login_versuch WHERE ip = ? AND zeit > ?', [$ip, $grenze]);
            return self::sperrMeldung($aeltester + self::FENSTER_SEK - $jetzt);
        }
        $gesamt = (int)Db::wert('SELECT COUNT(*) FROM login_versuch WHERE zeit > ?', [$grenze]);
        if ($gesamt >= self::MAX_GESAMT) {
            $aeltester = (int)Db::wert('SELECT MIN(zeit) FROM login_versuch WHERE zeit > ?', [$grenze]);
            return self::sperrMeldung($aeltester + self::FENSTER_SEK - $jetzt);
        }

        if (!password_verify($passwort, (string)Config::get('password_hash'))) {
            Db::ausfuehren('INSERT INTO login_versuch (ip, zeit) VALUES (?, ?)', [$ip, $jetzt]);
            return 'Passwort falsch.';
        }

        Db::ausfuehren('DELETE FROM login_versuch WHERE ip = ?', [$ip]);
        self::sitzungErzeugen($jetzt);
        return null;
    }

    public static function abmelden(): void
    {
        $sitzung = self::sitzung();
        if ($sitzung) {
            Db::ausfuehren('DELETE FROM sitzung WHERE token_hash = ?', [$sitzung['token_hash']]);
        }
        self::cookieSetzen('', time() - 3600);
        self::$sitzung = null;
    }

    /** Meldet alle Geräte ab. */
    public static function alleAbmelden(): void
    {
        Db::ausfuehren('DELETE FROM sitzung');
        self::cookieSetzen('', time() - 3600);
        self::$sitzung = null;
    }

    public static function flash(?string $text = null): ?string
    {
        $sitzung = self::sitzung();
        if (!$sitzung) {
            return null;
        }
        if ($text !== null) {
            Db::ausfuehren('UPDATE sitzung SET flash = ? WHERE token_hash = ?', [$text, $sitzung['token_hash']]);
            return null;
        }
        $vorhanden = $sitzung['flash'] ?? null;
        if ($vorhanden !== null) {
            Db::ausfuehren('UPDATE sitzung SET flash = NULL WHERE token_hash = ?', [$sitzung['token_hash']]);
            self::$sitzung['flash'] = null;
        }
        return $vorhanden;
    }

    public static function clientIp(): string
    {
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $weitergeleitet = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($weitergeleitet !== '' && in_array($remote, Config::get('trusted_proxies'), true)) {
            $teile = array_map('trim', explode(',', $weitergeleitet));
            $ip = end($teile);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return $remote;
    }

    private static function sitzungErzeugen(int $jetzt): void
    {
        $token = bin2hex(random_bytes(32));
        $ablauf = $jetzt + self::laufzeit();
        Db::ausfuehren('DELETE FROM sitzung WHERE laeuft_ab < ?', [$jetzt]);
        Db::ausfuehren(
            'INSERT INTO sitzung (token_hash, csrf, erstellt, laeuft_ab) VALUES (?, ?, ?, ?)',
            [hash('sha256', $token), bin2hex(random_bytes(32)), $jetzt, $ablauf]
        );
        self::cookieSetzen($token, $ablauf);
        self::$geladen = false;
        $_COOKIE[self::COOKIE] = $token;
    }

    private static function laufzeit(): int
    {
        return (int)Config::get('session_days') * 86400;
    }

    private static function cookieSetzen(string $wert, int $ablauf): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, $wert, [
            'expires' => $ablauf,
            'path' => basis_pfad() === '' ? '/' : basis_pfad() . '/',
            'secure' => ist_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function sperrMeldung(int $sekunden): string
    {
        $minuten = max(1, (int)ceil($sekunden / 60));
        return "Zu viele Fehlversuche. Bitte in $minuten " . ($minuten === 1 ? 'Minute' : 'Minuten') . ' erneut versuchen.';
    }
}
