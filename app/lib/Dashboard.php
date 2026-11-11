<?php
declare(strict_types=1);

final class Dashboard
{
    public static function daten(DateTimeImmutable $heute, ?int $statistikJahr = null): array
    {
        $e = Einstellungen::alle();
        $heuteStr = $heute->format('Y-m-d');

        $vergangene = Db::alle(
            'SELECT a.*, (SELECT COUNT(*) FROM setlist_eintrag s WHERE s.auftritt_id = a.id) AS titel_anzahl
               FROM auftritt a WHERE a.datum <= ? ORDER BY a.datum',
            [$heuteStr]
        );

        $setlists = [];
        $abrechnung = [];
        $reklamation = [];
        foreach ($vergangene as $a) {
            if ($frist = Fristen::setlistFrist($a, $heute, $e)) {
                $setlists[] = $a + ['frist' => $frist];
            }
            if ($a['abrechnung_status'] === 'nicht_geprueft') {
                $a['ausschuettung_vorbei'] = $a['ausschuettungstermin'] !== null && $a['ausschuettungstermin'] <= $heuteStr;
                $abrechnung[] = $a;
            }
            $rek = Fristen::reklamationsFrist($a, $heute, $e);
            if ($rek && in_array($rek['stufe'], ['bald', 'abgelaufen'], true)) {
                $reklamation[] = $a + ['frist' => $rek];
            }
        }

        usort($setlists, static fn($x, $y) => $x['frist']['faellig'] <=> $y['frist']['faellig']);
        // Erst die, deren Ausschüttung schon vorbei ist (jetzt prüfbar), dann nach Datum
        usort($abrechnung, static fn($x, $y) => [!$x['ausschuettung_vorbei'], $x['datum']] <=> [!$y['ausschuettung_vorbei'], $y['datum']]);
        usort($reklamation, static fn($x, $y) => $x['frist']['bis'] <=> $y['frist']['bis']);

        $kommende = Db::alle('SELECT * FROM auftritt WHERE datum > ? ORDER BY datum, uhrzeit LIMIT 5', [$heuteStr]);

        return [
            'heute' => $heute,
            'setlists' => $setlists,
            'abrechnung' => $abrechnung,
            'reklamation' => $reklamation,
            'kommende' => $kommende,
            'pro_jahr' => self::proJahr($heuteStr),
            'pro_werk' => self::proWerk($heuteStr, $statistikJahr),
            'statistik_jahr' => $statistikJahr,
        ];
    }

    public static function proJahr(string $bis): array
    {
        return Db::alle(
            "SELECT substr(a.datum, 1, 4) AS jahr,
                    COUNT(DISTINCT a.id) AS auftritte,
                    COUNT(s.id) AS auffuehrungen,
                    COALESCE(SUM(CASE WHEN w.eigenes_werk = 1 THEN 1 ELSE 0 END), 0) AS eigene
               FROM auftritt a
               LEFT JOIN setlist_eintrag s ON s.auftritt_id = a.id
               LEFT JOIN werk w ON w.id = s.werk_id
              WHERE a.datum <= ?
              GROUP BY jahr ORDER BY jahr DESC",
            [$bis]
        );
    }

    public static function proWerk(string $bis, ?int $jahr = null, int $limit = 30): array
    {
        $sql = 'SELECT w.id, w.titel, w.eigenes_werk, COUNT(*) AS anzahl
                  FROM setlist_eintrag s
                  JOIN auftritt a ON a.id = s.auftritt_id
                  JOIN werk w ON w.id = s.werk_id
                 WHERE a.datum <= ?';
        $parameter = [$bis];
        if ($jahr !== null) {
            $sql .= ' AND substr(a.datum, 1, 4) = ?';
            $parameter[] = (string)$jahr;
        }
        $sql .= ' GROUP BY w.id ORDER BY anzahl DESC, w.titel COLLATE NOCASE LIMIT ' . $limit;
        return Db::alle($sql, $parameter);
    }
}
