<?php
declare(strict_types=1);

/** Konfigurierbare Fristen (in der Datenbank gespeichert, in der App änderbar). */
final class Einstellungen
{
    public const STANDARD = [
        'frist_setlist_tage' => 42,
        'frist_reklamation_monate' => 9,
        'vorlauf_setlist_tage' => 14,
        'vorlauf_reklamation_tage' => 30,
    ];

    /** Bezeichnung, Hinweis, Minimum, Maximum */
    public const FELDER = [
        'frist_setlist_tage' => ['Einreichungsfrist Setlist (Tage nach Auftritt)', '42 Tage = 6 Wochen', 1, 730],
        'frist_reklamation_monate' => ['Reklamationsfrist (Monate nach Ausschüttung)', 'Standard: 9 Monate', 1, 120],
        'vorlauf_setlist_tage' => ['Setlist als „bald fällig“ markieren (Tage vorher)', 'Hervorhebung im Dashboard', 0, 365],
        'vorlauf_reklamation_tage' => ['Reklamationsfrist anzeigen ab (Tage vorher)', 'Hervorhebung im Dashboard', 0, 365],
    ];

    private static ?array $cache = null;

    /** @return array<string,int> */
    public static function alle(): array
    {
        if (self::$cache === null) {
            $werte = self::STANDARD;
            foreach (Db::alle('SELECT schluessel, wert FROM einstellung') as $zeile) {
                if (array_key_exists($zeile['schluessel'], $werte)) {
                    $werte[$zeile['schluessel']] = (int)$zeile['wert'];
                }
            }
            self::$cache = $werte;
        }
        return self::$cache;
    }

    public static function get(string $schluessel): int
    {
        return self::alle()[$schluessel];
    }

    /** @param array<string,mixed> $werte */
    public static function setzen(array $werte): void
    {
        foreach (self::FELDER as $schluessel => [$label, , $min, $max]) {
            if (!array_key_exists($schluessel, $werte)) {
                continue;
            }
            $wert = trim((string)$werte[$schluessel]);
            if (!preg_match('/^\d+$/', $wert) || (int)$wert < $min || (int)$wert > $max) {
                throw new Eingabefehler("$label: bitte eine Zahl zwischen $min und $max eingeben.");
            }
        }
        Db::transaktion(function () use ($werte) {
            foreach (self::FELDER as $schluessel => $_) {
                if (array_key_exists($schluessel, $werte)) {
                    Db::ausfuehren(
                        'INSERT INTO einstellung (schluessel, wert) VALUES (?, ?)
                         ON CONFLICT(schluessel) DO UPDATE SET wert = excluded.wert',
                        [$schluessel, (string)(int)trim((string)$werte[$schluessel])]
                    );
                }
            }
        });
        self::$cache = null;
    }

    public static function cacheLeeren(): void
    {
        self::$cache = null;
    }
}
