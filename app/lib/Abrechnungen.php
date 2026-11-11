<?php
declare(strict_types=1);

/**
 * GEMA-Abrechnungen aus dem Online-Portal (CSV, Semikolon, UTF-8 mit BOM) einlesen und auswerten.
 *
 * Erkannt werden drei Dateiarten – am Spaltenkopf, nicht am Dateinamen:
 *   SUM_<Termin>_…csv      Summen je Sparte (Kontrollbetrag)        → Spalte "Satzzahl in Kompaktaufstellung"
 *   KMP_<Termin>_…csv      Kompaktaufstellung: je Werk und Sparte    → Spalte "Berufsgruppe"
 *   <Sparte>_<Termin>_HV…  Detailaufstellung einer Sparte (U, M, R, MOD.S, MT.VR, A …) mit Ort, Sender usw.
 *
 * Posten: Gibt es für eine Abrechnungsnummer eine Detaildatei, gelten deren Zeilen (ausführlicher);
 * sonst die der Kompaktaufstellung. Erneutes Hochladen ersetzt, es wird nichts doppelt gezählt.
 */
final class Abrechnungen
{
    /** Bekannte Sparten (Namen wie in den GEMA-Dateien), falls nur die Kompaktaufstellung vorliegt. */
    private const SPARTEN = [
        'U' => ['Unterhaltungsmusik (U)', 'Live / Wiedergabe'],
        'E' => ['Ernste Musik (E)', 'Live / Wiedergabe'],
        'M' => ['Musikwiedergaben (M)', 'Live / Wiedergabe'],
        'R' => ['Radio (R)', 'Radio'],
        'R VR' => ['Radio Vervielfältigungsrecht (R VR)', 'Radio'],
        'MOD S' => ['Music on Demand Streaming (MOD S)', 'Online'],
        'MOD S VR' => ['Music on Demand Streaming Vervielfältigungsrecht (MOD S VR)', 'Online'],
        'A' => ['Ausland (A)', 'Ausland'],
        'MT VR' => ['Musik auf Trägern Vervielfältigungsrecht (MT VR)', 'Musikträger'],
    ];

    public const MONATE = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    // ---------------------------------------------------------------- Einlesen

    /**
     * Zerlegt eine Datei. Unbekannte Dateien liefern typ = null.
     * @return array{typ:?string, zeilen:list<array<string,string>>}
     */
    public static function lesen(string $inhalt): array
    {
        $inhalt = preg_replace('/^\xEF\xBB\xBF/', '', $inhalt);
        if (!mb_check_encoding($inhalt, 'UTF-8')) {
            $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
        }
        $h = fopen('php://memory', 'r+');
        fwrite($h, $inhalt);
        rewind($h);
        $kopf = fgetcsv($h, null, ';', '"', '');
        $zeilen = [];
        if (is_array($kopf)) {
            $kopf = array_map('trim', $kopf);
            while (($z = fgetcsv($h, null, ';', '"', '')) !== false) {
                if ($z === [null] || count(array_filter($z, static fn($v) => trim((string)$v) !== '')) === 0) {
                    continue;
                }
                $zeilen[] = array_combine($kopf, array_map(static fn($v) => trim((string)$v), array_pad(array_slice($z, 0, count($kopf)), count($kopf), '')));
            }
        }
        fclose($h);
        $spalten = is_array($kopf) ? $kopf : [];
        $typ = match (true) {
            in_array('Satzzahl in Kompaktaufstellung', $spalten, true) => 'summe',
            in_array('Berufsgruppe', $spalten, true) && in_array('Fassungsnummer', $spalten, true) => 'kompakt',
            in_array('Werkfassungsnummer', $spalten, true) && in_array('Verteilungssparte', $spalten, true) => 'detail',
            default => null,
        };
        return ['typ' => $typ, 'zeilen' => $zeilen];
    }

    /**
     * Mehrere Dateien übernehmen (eine Transaktion). ZIP-Archive werden entpackt.
     * @param array<string,string> $dateien Dateiname → Inhalt
     * @return array{dateien:int, abrechnungen:int, posten:int, unbekannt:list<string>}
     */
    public static function importieren(array $dateien): array
    {
        $gelesen = ['summe' => [], 'kompakt' => [], 'detail' => []];
        $unbekannt = [];
        foreach (self::entpacken($dateien) as $name => $inhalt) {
            $d = self::lesen($inhalt);
            if ($d['typ'] === null || !$d['zeilen']) {
                $unbekannt[] = $name;
                continue;
            }
            $gelesen[$d['typ']][] = $d['zeilen'];
        }
        if (!array_filter($gelesen)) {
            throw new Eingabefehler('Keine GEMA-Abrechnung erkannt. Erwartet werden die CSV-Dateien aus dem GEMA-Portal (SUM_…, KMP_…, U_…, R_… usw.).');
        }

        return Db::transaktion(static function () use ($gelesen, $unbekannt) {
            $nummern = [];
            // 1. Detaildateien: ersetzen alle Posten ihrer Abrechnung
            $detail = [];
            foreach ($gelesen['detail'] as $zeilen) {
                foreach ($zeilen as $z) {
                    $detail[$z['Abrechnungsnummer']][] = $z;
                }
            }
            foreach ($detail as $nr => $zeilen) {
                $nr = (string)$nr;   // numerische Schlüssel werden in PHP zu int
                $kuerzel = self::kuerzel($zeilen[0]['Verteilungssparte']);
                self::abrechnungSichern($nr, self::datum($zeilen[0]['Ausschüttungstermin']), $kuerzel,
                    $zeilen[0]['Verteilungssparte'], $zeilen[0]['Spartengruppe'] ?? '', $zeilen[0]['Verteilart'] ?? '');
                Db::ausfuehren('DELETE FROM abrechnung_posten WHERE abrechnungsnummer = ?', [$nr]);
                foreach ($zeilen as $z) {
                    self::postenEinfuegen($nr, 'detail', self::detailPosten($z));
                }
                $nummern[$nr] = true;
            }
            // 2. Kompaktaufstellungen: nur dort, wo keine Detaildaten vorliegen
            $kompakt = [];
            foreach ($gelesen['kompakt'] as $zeilen) {
                foreach ($zeilen as $z) {
                    $kompakt[$z['Abrechnungsnummer']][] = $z;
                }
            }
            foreach ($kompakt as $nr => $zeilen) {
                $nr = (string)$nr;
                $kuerzel = trim($zeilen[0]['Spartenkürzel'], '"');
                [$sparte, $bereich] = self::SPARTEN[$kuerzel] ?? [$kuerzel, 'Sonstiges'];
                self::abrechnungSichern($nr, self::datum($zeilen[0]['Ausschüttungstermin']), $kuerzel, $sparte, $bereich,
                    self::verteilart($zeilen[0]['Verteilart'] ?? ''), false);
                $nummern[$nr] = true;
                if (Db::wert("SELECT COUNT(*) FROM abrechnung_posten WHERE abrechnungsnummer = ? AND quelle = 'detail'", [$nr])) {
                    continue;
                }
                Db::ausfuehren('DELETE FROM abrechnung_posten WHERE abrechnungsnummer = ?', [$nr]);
                foreach ($zeilen as $z) {
                    self::postenEinfuegen($nr, 'kompakt', self::kompaktPosten($z));
                }
            }
            // 3. Summen: Kontrollbetrag; legt die Abrechnung an, falls nur die Summe vorliegt
            foreach ($gelesen['summe'] as $zeilen) {
                foreach ($zeilen as $z) {
                    $nr = $z['Abrechnungsnummer'];
                    self::abrechnungSichern($nr, self::datum($z['Ausschüttungstermin']), trim($z['Spartenkürzel'], '"'),
                        $z['Verteilungssparte'], $z['Nutzungsbereich'], self::verteilart($z['Verteilart'] ?? ''), false);
                    Db::ausfuehren('UPDATE abrechnung SET betrag_laut_summe = ? WHERE abrechnungsnummer = ?', [self::zahl($z['Betrag gebucht']), $nr]);
                    $nummern[$nr] = true;
                }
            }
            // Beträge aus den Posten bzw. der Summe ableiten
            $nummern = array_map('strval', array_keys($nummern));
            foreach ($nummern as $nr) {
                Db::ausfuehren(
                    'UPDATE abrechnung SET betrag = COALESCE(
                        (SELECT SUM(betrag) FROM abrechnung_posten WHERE abrechnungsnummer = ?), betrag_laut_summe, 0)
                     WHERE abrechnungsnummer = ?',
                    [$nr, $nr]
                );
            }
            $posten = (int)Db::wert('SELECT COUNT(*) FROM abrechnung_posten WHERE abrechnungsnummer IN ('
                . implode(',', array_fill(0, count($nummern), '?')) . ')', $nummern);
            return [
                'dateien' => array_sum(array_map('count', $gelesen)),
                'abrechnungen' => count($nummern),
                'posten' => $posten,
                'unbekannt' => $unbekannt,
            ];
        });
    }

    /** Löscht alle Abrechnungen eines Monats (z. B. um sie neu hochzuladen). */
    public static function monatLoeschen(int $jahr, int $monat): int
    {
        return Db::ausfuehren('DELETE FROM abrechnung WHERE substr(termin, 1, 7) = ?', [sprintf('%04d-%02d', $jahr, $monat)]);
    }

    // ---------------------------------------------------------------- Auswertung

    /** @return list<int> Jahre mit Ausschüttungen, neueste zuerst */
    public static function jahre(): array
    {
        return array_map('intval', array_column(Db::alle('SELECT DISTINCT substr(termin, 1, 4) AS j FROM abrechnung ORDER BY j DESC'), 'j'));
    }

    /**
     * Jahresübersicht.
     * @return array{summe:float, monate:list<array>, bereiche:list<array>, werke:list<array>, warnungen:list<string>}
     */
    public static function jahr(int $jahr): array
    {
        $abr = Db::alle("SELECT * FROM abrechnung WHERE substr(termin, 1, 4) = ? ORDER BY termin, bereich, kuerzel", [(string)$jahr]);
        $monate = [];
        foreach ($abr as $a) {
            $m = (int)substr($a['termin'], 5, 2);
            $monate[$m] ??= ['monat' => $m, 'name' => self::MONATE[$m], 'summe' => 0.0, 'sparten' => []];
            $monate[$m]['summe'] += (float)$a['betrag'];
            $monate[$m]['sparten'][$a['kuerzel']] = ($monate[$m]['sparten'][$a['kuerzel']] ?? 0) + (float)$a['betrag'];
        }
        return [
            'summe' => array_sum(array_map(static fn($a) => (float)$a['betrag'], $abr)),
            'monate' => array_values($monate),
            'bereiche' => self::nachBereich($abr),
            'werke' => self::nachWerk("substr(a.termin, 1, 4) = ?", [(string)$jahr]),
            'warnungen' => self::warnungen($abr),
        ];
    }

    /**
     * Alle Ausschüttungen eines Monats, gegliedert nach Bereich → Sparte → Posten.
     * @return array{summe:float, bereiche:list<array>, werke:list<array>, warnungen:list<string>}
     */
    public static function monat(int $jahr, int $monat): array
    {
        $schluessel = sprintf('%04d-%02d', $jahr, $monat);
        $abr = Db::alle("SELECT * FROM abrechnung WHERE substr(termin, 1, 7) = ? ORDER BY bereich, kuerzel", [$schluessel]);
        $posten = [];
        foreach (Db::alle(
            "SELECT p.*, w.id AS werk_id FROM abrechnung_posten p
               JOIN abrechnung a ON a.abrechnungsnummer = p.abrechnungsnummer
               LEFT JOIN werk w ON w.id = (SELECT id FROM werk WHERE gema_werknummer = p.werknummer ORDER BY id LIMIT 1)
              WHERE substr(a.termin, 1, 7) = ?
              ORDER BY p.betrag DESC, p.titel",
            [$schluessel]
        ) as $p) {
            $posten[$p['abrechnungsnummer']][] = $p;
        }
        // Live-Posten mit passendem Auftritt (gleiches Datum) verknüpfen
        $auftritte = [];
        foreach (Db::alle('SELECT id, datum, venue, stadt FROM auftritt') as $a) {
            $auftritte[$a['datum']][] = $a;
        }
        $bereiche = [];
        foreach ($abr as $a) {
            $b = $a['bereich'];
            $bereiche[$b] ??= ['name' => $b, 'summe' => 0.0, 'sparten' => []];
            $bereiche[$b]['summe'] += (float)$a['betrag'];
            $liste = $posten[$a['abrechnungsnummer']] ?? [];
            foreach ($liste as &$p) {
                $p['auftritt'] = ($p['nutzung_von'] && $p['nutzung_von'] === $p['nutzung_bis']) ? ($auftritte[$p['nutzung_von']][0] ?? null) : null;
            }
            unset($p);
            $bereiche[$b]['sparten'][] = $a + ['posten' => $liste];
        }
        usort($bereiche, static fn($x, $y) => $y['summe'] <=> $x['summe']);
        return [
            'summe' => array_sum(array_map(static fn($a) => (float)$a['betrag'], $abr)),
            'termine' => array_values(array_unique(array_column($abr, 'termin'))),
            'bereiche' => $bereiche,
            'werke' => self::nachWerk("substr(a.termin, 1, 7) = ?", [$schluessel]),
            'warnungen' => self::warnungen($abr),
        ];
    }

    /** @return list<array{monat:int, summe:float}> für die Jahresauswahl */
    public static function monateMitDaten(int $jahr): array
    {
        return array_map(static fn($z) => ['monat' => (int)$z['m'], 'summe' => (float)$z['s']], Db::alle(
            "SELECT substr(termin, 6, 2) AS m, SUM(betrag) AS s FROM abrechnung WHERE substr(termin, 1, 4) = ? GROUP BY m ORDER BY m",
            [(string)$jahr]
        ));
    }

    /** "1.234,56 €"; Kleinstbeträge als "< 0,01 €" */
    public static function euro(float $betrag): string
    {
        if ($betrag > 0 && $betrag < 0.005) {
            return '< 0,01 €';
        }
        return number_format($betrag, 2, ',', '.') . ' €';
    }

    private static function nachBereich(array $abr): array
    {
        $b = [];
        foreach ($abr as $a) {
            $b[$a['bereich']] ??= ['name' => $a['bereich'], 'summe' => 0.0, 'sparten' => []];
            $b[$a['bereich']]['summe'] += (float)$a['betrag'];
            $b[$a['bereich']]['sparten'][$a['sparte']] = ($b[$a['bereich']]['sparten'][$a['sparte']] ?? 0) + (float)$a['betrag'];
        }
        usort($b, static fn($x, $y) => $y['summe'] <=> $x['summe']);
        return $b;
    }

    /** Summe je Werk (Werknummer), mit Verknüpfung zum Werk in Gigbook. */
    private static function nachWerk(string $bedingung, array $parameter): array
    {
        return array_map(static fn($z) => $z + ['summe' => (float)$z['summe']], Db::alle(
            "SELECT p.werknummer, MAX(p.titel) AS titel, SUM(p.betrag) AS summe, COUNT(*) AS posten,
                    GROUP_CONCAT(DISTINCT a.kuerzel) AS sparten,
                    (SELECT id FROM werk WHERE gema_werknummer = p.werknummer ORDER BY id LIMIT 1) AS werk_id
               FROM abrechnung_posten p JOIN abrechnung a ON a.abrechnungsnummer = p.abrechnungsnummer
              WHERE $bedingung
              GROUP BY COALESCE(p.werknummer, p.titel)
              ORDER BY summe DESC, titel",
            $parameter
        ));
    }

    /** @return list<string> */
    private static function warnungen(array $abr): array
    {
        $w = [];
        foreach ($abr as $a) {
            $postenAnzahl = (int)Db::wert('SELECT COUNT(*) FROM abrechnung_posten WHERE abrechnungsnummer = ?', [$a['abrechnungsnummer']]);
            if ($a['betrag_laut_summe'] !== null && $postenAnzahl > 0 && abs((float)$a['betrag_laut_summe'] - (float)$a['betrag']) >= 0.01) {
                $w[] = sprintf('%s (%s): Einzelposten ergeben %s, laut Summendatei %s.', $a['sparte'], datum_de($a['termin']),
                    self::euro((float)$a['betrag']), self::euro((float)$a['betrag_laut_summe']));
            }
            if ($postenAnzahl === 0) {
                $w[] = sprintf('%s (%s): nur die Summe liegt vor – für die Einzelposten die KMP- oder Detaildatei hochladen.', $a['sparte'], datum_de($a['termin']));
            }
        }
        return $w;
    }

    // ---------------------------------------------------------------- Hilfen

    private static function abrechnungSichern(string $nr, string $termin, string $kuerzel, string $sparte, string $bereich, string $verteilart, bool $namenUeberschreiben = true): void
    {
        if ($nr === '') {
            throw new Eingabefehler('Eine Zeile ohne Abrechnungsnummer – ist das eine GEMA-Abrechnung?');
        }
        $bereich = $bereich !== '' ? $bereich : (self::SPARTEN[$kuerzel][1] ?? 'Sonstiges');
        if (Db::wert('SELECT 1 FROM abrechnung WHERE abrechnungsnummer = ?', [$nr])) {
            if ($namenUeberschreiben) {
                Db::ausfuehren('UPDATE abrechnung SET termin = ?, kuerzel = ?, sparte = ?, bereich = ?, verteilart = ? WHERE abrechnungsnummer = ?',
                    [$termin, $kuerzel, $sparte, $bereich, $verteilart ?: null, $nr]);
            }
            return;
        }
        Db::ausfuehren('INSERT INTO abrechnung (abrechnungsnummer, termin, kuerzel, sparte, bereich, verteilart, betrag) VALUES (?, ?, ?, ?, ?, ?, 0)',
            [$nr, $termin, $kuerzel, $sparte, $bereich, $verteilart ?: null]);
    }

    private static function postenEinfuegen(string $nr, string $quelle, array $p): void
    {
        Db::ausfuehren(
            'INSERT INTO abrechnung_posten (abrechnungsnummer, quelle, werknummer, titel, betrag, anteil, anzahl, nutzung_von, nutzung_bis, beschreibung)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$nr, $quelle, $p['werknummer'], $p['titel'], $p['betrag'], $p['anteil'], $p['anzahl'], $p['von'], $p['bis'], $p['beschreibung']]
        );
    }

    private static function detailPosten(array $z): array
    {
        $von = self::datumOderNull($z['Nutzungsdatum von'] ?? '');
        $bis = self::datumOderNull($z['Nutzungsdatum bis'] ?? '') ?? $von;
        $teile = [];
        if (isset($z['Veranstaltungsort Name'])) {                       // Live (U, E, M …)
            $ort = trim(($z['Veranstaltungsort Name'] ?? '') . ', ' . ($z['Ort'] ?? ''), ', ');
            $teile = [$ort, $z['Interpret'] ?? ''];
            $anzahl = $z['Anzahl Aufführungen'] ?? '';
        } elseif (isset($z['Sender'])) {                                 // Radio / TV
            $dauer = (int)($z['Sendedauer Sekunden'] ?? 0);
            $teile = [$z['Sender'], $z['Uhrzeit'] ?? '', $dauer ? Dauer::format($dauer) . ' Min.' : '', $z['Titel der Sendung'] ?? ''];
            $anzahl = $z['Anzahl Ausstrahlungen'] ?? '';
        } elseif (isset($z['Produkttitel'])) {                           // Tonträger
            $teile = [trim(($z['Produktart'] ?? '') . ' „' . $z['Produkttitel'] . '“'), $z['Lizenznehmer'] ?? '',
                ($z['Menge/Anzahl'] ?? '') !== '' ? $z['Menge/Anzahl'] . ' Stück' : ''];
            $anzahl = $z['Menge/Anzahl'] ?? '';
        } elseif (isset($z['Gesellschaftsname abrechnende Gesellschaft'])) { // Ausland
            $teile = [trim($z['Gesellschaftsname abrechnende Gesellschaft'] . (($z['Verkaufsland'] ?? '') !== '' ? ' (' . $z['Verkaufsland'] . ')' : '')),
                $z['Lizenznehmer'] ?? ''];
            $anzahl = $z['Menge/Anzahl'] ?? '';
        } else {                                                         // Online / Streaming u. a.
            $dienst = ($z['Identifikator'] ?? '') !== '' ? $z['Identifikator'] : ($z['Lizenznehmer'] ?? '');
            $menge = $z['Menge/Anzahl'] ?? '';
            $teile = [$dienst, $z['Verkaufsland'] ?? '', $menge !== '' ? $menge . ' Abrufe' : '', $z['Titel des audiovisuellen Werkes'] ?? ''];
            $anzahl = $menge;
        }
        if (($z['Rück-/Nachverrechnung'] ?? '') !== '') {
            $teile[] = 'Rück-/Nachverrechnung: ' . $z['Rück-/Nachverrechnung'];
        }
        return [
            'werknummer' => self::werknummer($z['Werkfassungsnummer'] ?? ''),
            'titel' => ($z['Werkfassungstitel'] ?? '') !== '' ? $z['Werkfassungstitel'] : '(ohne Titel)',
            'betrag' => self::zahl($z['Betrag gebucht'] ?? ''),
            'anteil' => ($z['Anteil Prozent'] ?? '') !== '' ? self::zahl($z['Anteil Prozent']) : null,
            'anzahl' => $anzahl !== '' ? self::zahl($anzahl) : null,
            'von' => $von,
            'bis' => $bis,
            'beschreibung' => self::beschreibung($teile),
        ];
    }

    private static function kompaktPosten(array $z): array
    {
        $z += array_fill_keys(['Anzahl Aufführungen', 'Anzahl Ausstrahlungen', 'Menge/Anzahl', 'Nutzungsjahr', 'Quartal von', 'Quartal bis',
            'Werknummer', 'Fassungsnummer', 'Werkfassungstitel', 'Betrag gebucht', 'Anteil Prozent'], '');
        $anzahl = $z['Anzahl Aufführungen'] !== '' ? $z['Anzahl Aufführungen'] : ($z['Anzahl Ausstrahlungen'] !== '' ? $z['Anzahl Ausstrahlungen'] : ($z['Menge/Anzahl'] ?? ''));
        $zeitraum = $z['Nutzungsjahr'] !== ''
            ? 'Nutzung ' . ($z['Quartal von'] !== '' ? ($z['Quartal von'] === $z['Quartal bis'] || $z['Quartal bis'] === '' ? 'Q' . $z['Quartal von'] : 'Q' . $z['Quartal von'] . '–' . $z['Quartal bis']) . '/' : '') . $z['Nutzungsjahr']
            : '';
        $werknummer = $z['Werknummer'] !== '' ? $z['Werknummer'] . '-' . str_pad(trim($z['Fassungsnummer'], '"'), 3, '0', STR_PAD_LEFT) : null;
        return [
            'werknummer' => $werknummer,
            'titel' => $z['Werkfassungstitel'] !== '' ? $z['Werkfassungstitel'] : '(ohne Titel)',
            'betrag' => self::zahl($z['Betrag gebucht']),
            'anteil' => $z['Anteil Prozent'] !== '' ? self::zahl($z['Anteil Prozent']) : null,
            'anzahl' => $anzahl !== '' ? self::zahl($anzahl) : null,
            'von' => null,
            'bis' => null,
            'beschreibung' => self::beschreibung([$z['Sender'] ?? '', $z['Lizenznehmer'] ?? '', $z['Verkaufsland'] ?? '', $zeitraum]),
        ];
    }

    private static function beschreibung(array $teile): ?string
    {
        $teile = array_values(array_unique(array_filter(array_map('trim', $teile), static fn($t) => $t !== '' && $t !== '-')));
        return $teile ? implode(' · ', $teile) : null;
    }

    /** "Unterhaltungsmusik (U)" → "U" */
    private static function kuerzel(string $sparte): string
    {
        if (preg_match('/\(([^()]+)\)\s*$/u', $sparte, $m)) {
            return trim($m[1]);
        }
        foreach (self::SPARTEN as $k => [$name]) {
            if ($name === $sparte) {
                return $k;
            }
        }
        return $sparte;
    }

    private static function verteilart(string $v): string
    {
        return match ($v) {
            'HV' => 'Hauptverteilung',
            'NV' => 'Nachverteilung',
            default => $v,
        };
    }

    private static function werknummer(string $nr): ?string
    {
        $nr = trim($nr, " \"");
        return $nr === '' ? null : $nr;
    }

    /** "0,4461213710" → 0.446…; "1.234,5" → 1234.5 */
    private static function zahl(string $wert): float
    {
        $wert = trim($wert, " \"");
        return $wert === '' ? 0.0 : (float)str_replace(',', '.', str_replace('.', '', $wert));
    }

    private static function datum(string $wert): string
    {
        return self::datumOderNull($wert) ?? throw new Eingabefehler("Ungültiges Datum „{$wert}“ in der Abrechnung.");
    }

    private static function datumOderNull(string $wert): ?string
    {
        return preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', trim($wert), $m) ? "$m[3]-$m[2]-$m[1]" : null;
    }

    /** @param array<string,string> $dateien @return array<string,string> */
    private static function entpacken(array $dateien): array
    {
        $ergebnis = [];
        foreach ($dateien as $name => $inhalt) {
            if (!str_starts_with($inhalt, "PK\x03\x04")) {
                $ergebnis[$name] = $inhalt;
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'abr');
            file_put_contents($tmp, $inhalt);
            $zip = new ZipArchive();
            try {
                if ($zip->open($tmp, ZipArchive::RDONLY) !== true) {
                    throw new Eingabefehler("„{$name}“ ist kein gültiges ZIP-Archiv.");
                }
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $eintrag = (string)$zip->getNameIndex($i);
                    if (str_ends_with(strtolower($eintrag), '.csv') && !str_contains($eintrag, '__MACOSX')) {
                        $ergebnis[$name . '/' . basename($eintrag)] = (string)$zip->getFromIndex($i);
                    }
                }
                $zip->close();
            } finally {
                @unlink($tmp);
            }
        }
        return $ergebnis;
    }
}
