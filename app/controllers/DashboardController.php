<?php
declare(strict_types=1);

final class DashboardController
{
    public static function index(): void
    {
        $jahr = isset($_GET['jahr']) && preg_match('/^\d{4}$/', (string)$_GET['jahr']) ? (int)$_GET['jahr'] : null;
        View::seite('dashboard', Dashboard::daten(Fristen::heute(), $jahr) + [
            'einstellungen' => Einstellungen::alle(),
        ], 'Übersicht', 'start');
    }
}
