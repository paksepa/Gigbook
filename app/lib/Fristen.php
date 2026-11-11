<?php
declare(strict_types=1);

/**
 * Fristberechnung. Alle Daten als reine Kalendertage (UTC, 00:00), damit
 * Sommerzeitwechsel keine Rolle spielen.
 */
final class Fristen
{
    public static function datum(string $ymd): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new DateTimeZone('UTC'));
        if (!$d || $d->format('Y-m-d') !== $ymd) {
            throw new InvalidArgumentException("Ungültiges Datum: $ymd");
        }
        return $d;
    }

    /** Heutiges Datum in Deutschland. */
    public static function heute(): DateTimeImmutable
    {
        return self::datum((new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d'));
    }

    /**
     * Kalendermonate addieren. Gibt es den Tag im Zielmonat nicht,
     * wird auf das Monatsende gekürzt (31.05. + 9 Monate = 28./29.02.).
     */
    public static function plusMonate(DateTimeImmutable $d, int $monate): DateTimeImmutable
    {
        $index = (int)$d->format('Y') * 12 + (int)$d->format('n') - 1 + $monate;
        $jahr = intdiv($index, 12);
        $monat = $index % 12 + 1;
        $letzterTag = (int)$d->setDate($jahr, $monat, 1)->format('t');
        return $d->setDate($jahr, $monat, min((int)$d->format('j'), $letzterTag));
    }

    public static function setlistFaelligAm(string $auftrittsdatum, int $tage): DateTimeImmutable
    {
        return self::datum($auftrittsdatum)->modify("+$tage days");
    }

    public static function reklamationBis(string $ausschuettungstermin, int $monate): DateTimeImmutable
    {
        return self::plusMonate(self::datum($ausschuettungstermin), $monate);
    }

    /** Tage von $heute bis $ziel (negativ = vorbei). */
    public static function tageBis(DateTimeImmutable $ziel, DateTimeImmutable $heute): int
    {
        return (int)$heute->diff($ziel)->format('%r%a');
    }

    /**
     * Stand der Setlist-Einreichungsfrist eines Auftritts.
     * null = keine Frist relevant (Setlist erledigt/nicht nötig).
     * stufe: zukunft | offen | bald | ueberfaellig
     *
     * @return array{faellig: DateTimeImmutable, tage: int, stufe: string}|null
     */
    public static function setlistFrist(array $auftritt, DateTimeImmutable $heute, array $einstellungen): ?array
    {
        if (($auftritt['setlist_status'] ?? 'offen') !== 'offen') {
            return null;
        }
        $faellig = self::setlistFaelligAm($auftritt['datum'], $einstellungen['frist_setlist_tage']);
        $tage = self::tageBis($faellig, $heute);
        $stufe = match (true) {
            self::datum($auftritt['datum']) > $heute => 'zukunft',
            $tage < 0 => 'ueberfaellig',
            $tage <= $einstellungen['vorlauf_setlist_tage'] => 'bald',
            default => 'offen',
        };
        return ['faellig' => $faellig, 'tage' => $tage, 'stufe' => $stufe];
    }

    /**
     * Stand der Reklamationsfrist. Relevant, solange die Abrechnung
     * nicht geprüft ist oder der Auftritt fehlt.
     * stufe: zukunft (Ausschüttung noch nicht erfolgt) | laeuft | bald | abgelaufen
     *
     * @return array{bis: DateTimeImmutable, tage: int, stufe: string}|null
     */
    public static function reklamationsFrist(array $auftritt, DateTimeImmutable $heute, array $einstellungen): ?array
    {
        if (empty($auftritt['ausschuettungstermin'])) {
            return null;
        }
        if (!in_array($auftritt['abrechnung_status'] ?? '', ['nicht_geprueft', 'fehlt'], true)) {
            return null;
        }
        $bis = self::reklamationBis($auftritt['ausschuettungstermin'], $einstellungen['frist_reklamation_monate']);
        $tage = self::tageBis($bis, $heute);
        $stufe = match (true) {
            self::datum($auftritt['ausschuettungstermin']) > $heute => 'zukunft',
            $tage < 0 => 'abgelaufen',
            $tage <= $einstellungen['vorlauf_reklamation_tage'] => 'bald',
            default => 'laeuft',
        };
        return ['bis' => $bis, 'tage' => $tage, 'stufe' => $stufe];
    }
}
