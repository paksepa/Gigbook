<?php
declare(strict_types=1);

final class AuthController
{
    public static function formular(): void
    {
        if (Auth::angemeldet()) {
            weiterleiten('/');
        }
        View::seite('login', ['fehler' => null, 'konfiguriert' => Auth::passwortKonfiguriert()], 'Anmelden');
    }

    public static function anmelden(): void
    {
        $fehler = Auth::anmelden((string)($_POST['passwort'] ?? ''), Auth::clientIp());
        if ($fehler === null) {
            weiterleiten('/');
        }
        http_response_code(str_starts_with($fehler, 'Zu viele') ? 429 : 401);
        View::seite('login', ['fehler' => $fehler, 'konfiguriert' => Auth::passwortKonfiguriert()], 'Anmelden');
    }

    public static function abmelden(): void
    {
        Auth::abmelden();
        weiterleiten('/login');
    }
}
