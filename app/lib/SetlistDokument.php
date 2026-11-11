<?php
declare(strict_types=1);

/** Einfache Setlist für Veranstalter als PDF oder CSV. */
final class SetlistDokument
{
    public static function pdf(array $a, array $setlist): string
    {
        $pdf = new EinfachesPdf('Setlist ' . datum_de($a['datum']));
        $rand = 40;
        $rechts = EinfachesPdf::BREITE - $rand;
        $y = 56;

        $pdf->text($rand, $y, 'Setlist' . ($a['ensemble'] ? ' – ' . $a['ensemble'] : ''), 16, true);
        $y += 22;
        foreach (self::kopfzeilen($a) as [$label, $wert]) {
            $pdf->text($rand, $y, $label, 9, true);
            $pdf->text($rand + 90, $y, EinfachesPdf::kuerzen($wert, $rechts - $rand - 90, 9), 9);
            $y += 13;
        }
        $y += 12;

        // Spalten: Nr | Titel | Komponist(en) | GEMA-Werknr. | Dauer
        $sp = ['nr' => $rand, 'titel' => $rand + 24, 'komp' => $rand + 230, 'werknr' => $rand + 410, 'dauer' => $rechts];
        $kopf = function () use ($pdf, $sp, $rand, $rechts, &$y) {
            $pdf->text($sp['nr'], $y, 'Nr.', 8, true);
            $pdf->text($sp['titel'], $y, 'Titel', 8, true);
            $pdf->text($sp['komp'], $y, 'Komponist(en)', 8, true);
            $pdf->text($sp['werknr'], $y, 'GEMA-Werknr.', 8, true);
            $pdf->text($sp['dauer'], $y, 'Dauer', 8, true, 'rechts');
            $y += 5;
            $pdf->linie($rand, $y, $rechts, $y, 0.3);
            $y += 12;
        };
        $kopf();

        $summe = 0;
        foreach ($setlist as $i => $e) {
            if ($y > EinfachesPdf::HOEHE - 60) {
                $pdf->neueSeite();
                $y = 56;
                $kopf();
            }
            $summe += (int)$e['dauer_sek'];
            $pdf->text($sp['nr'], $y, (string)($i + 1), 9);
            $pdf->text($sp['titel'], $y, EinfachesPdf::kuerzen($e['titel'], 200, 9), 9);
            $pdf->text($sp['komp'], $y, EinfachesPdf::kuerzen(Werke::komponistenText($e['komponisten']), 172, 9), 9);
            $pdf->text($sp['werknr'], $y, EinfachesPdf::kuerzen((string)$e['gema_werknummer'], 70, 9), 9);
            $pdf->text($sp['dauer'], $y, Dauer::format($e['dauer_sek'] === null ? null : (int)$e['dauer_sek']), 9, false, 'rechts');
            $y += 6;
            $pdf->linie($rand, $y, $rechts, $y, 0.85);
            $y += 12;
        }
        $y += 4;
        $pdf->text($rand, $y, count($setlist) . ' Titel', 9, true);
        if ($summe > 0) {
            $pdf->text($rechts, $y, 'Gesamt ' . Dauer::gesamt($summe), 9, true, 'rechts');
        }
        return $pdf->ausgabe();
    }

    public static function csv(array $a, array $setlist): string
    {
        $f = fopen('php://temp', 'w+');
        fwrite($f, "\xEF\xBB\xBF"); // BOM, damit Excel Umlaute erkennt
        fputcsv($f, ['Nr.', 'Titel', 'Komponist(en)', 'GEMA-Werknummer', 'ISWC', 'Dauer', 'Mitwirkende'], ';', '"', '');
        foreach ($setlist as $i => $e) {
            fputcsv($f, [
                $i + 1,
                $e['titel'],
                Werke::komponistenText($e['komponisten']),
                $e['gema_werknummer'],
                $e['iswc'],
                Dauer::format($e['dauer_sek'] === null ? null : (int)$e['dauer_sek']),
                $e['mitwirkende'],
            ], ';', '"', '');
        }
        rewind($f);
        $csv = (string)stream_get_contents($f);
        fclose($f);
        return $csv;
    }

    /** @return list<array{0:string,1:string}> */
    public static function kopfzeilen(array $a): array
    {
        $zeilen = [
            ['Datum', datum_de($a['datum'], true) . ($a['uhrzeit'] ? ', ' . $a['uhrzeit'] . ' Uhr' : '')],
            ['Ort', trim(implode(', ', array_filter([$a['venue'], $a['stadt']])))],
            ['Ensemble', (string)$a['ensemble']],
            ['Mitwirkende', (string)$a['anzahl_mitwirkende']],
            ['Veranstalter', trim(implode(' · ', array_filter([$a['veranstalter_name'], $a['veranstalter_kontakt']])))],
        ];
        return array_values(array_filter($zeilen, static fn($z) => $z[1] !== ''));
    }

    public static function dateiname(array $a, string $praefix, string $endung): string
    {
        return $praefix . '_' . $a['datum'] . ($a['venue'] ? '_' . slug($a['venue']) : '') . '.' . $endung;
    }
}
