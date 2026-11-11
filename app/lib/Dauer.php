<?php
declare(strict_types=1);

/** Spieldauern: Eingabe als "7:30", "7.30", "7,30", "730" oder "7" (Minuten). */
final class Dauer
{
    public static function parsen(?string $eingabe): ?int
    {
        $s = trim((string)$eingabe);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{1,3})[:.,](\d{1,2})$/', $s, $m)) {
            [$min, $sek] = [(int)$m[1], (int)$m[2]];
        } elseif (preg_match('/^\d{3,5}$/', $s)) {
            [$min, $sek] = [intdiv((int)$s, 100), (int)$s % 100];
        } elseif (preg_match('/^\d{1,2}$/', $s)) {
            [$min, $sek] = [(int)$s, 0];
        } else {
            throw new Eingabefehler('Dauer bitte als Minuten:Sekunden eingeben, z. B. 7:30.');
        }
        if ($sek > 59) {
            throw new Eingabefehler('Dauer: Sekunden müssen zwischen 0 und 59 liegen.');
        }
        return $min * 60 + $sek;
    }

    /** "7:30" bzw. für die GEMA-Vorlage "07:30" (MM:SS). */
    public static function format(?int $sekunden, bool $gema = false): string
    {
        if ($sekunden === null) {
            return '';
        }
        $min = intdiv($sekunden, 60);
        $sek = $sekunden % 60;
        return sprintf($gema ? '%02d:%02d' : '%d:%02d', $min, $sek);
    }

    /** Gesamtdauer lesbar: "1:12 Std." bzw. "45 Min." */
    public static function gesamt(int $sekunden): string
    {
        $minuten = (int)round($sekunden / 60);
        if ($minuten < 60) {
            return "$minuten Min.";
        }
        return sprintf('%d:%02d Std.', intdiv($minuten, 60), $minuten % 60);
    }
}
