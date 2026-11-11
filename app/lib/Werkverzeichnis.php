<?php
declare(strict_types=1);

/**
 * Import des eigenen Werkverzeichnisses aus dem GEMA-Portal
 * (Online-Portal → Werke → Export als Excel, Datei "Werke_<Datum>_<Mitgliedsnr>.xlsx").
 *
 * Gelesen werden die Blätter
 *   "Werkinformationen"  Werknummer, Titel, Dauer (HH:MM:SS), Besetzung
 *   "Identifikatoren"    ISWC
 *   "Beteiligte"         Name ("NACHNAME, VORNAME"), Rolle – eine Zeile je Beteiligtem
 * Spalten werden über die Überschriften gefunden, nicht über feste Buchstaben.
 *
 * Übernahme (nichts wird gelöscht):
 *   - gleiche Werknummer             → Werk wird ergänzt
 *   - sonst gleicher Titel ohne Nr.  → Werknummer wird nachgetragen
 *   - sonst                          → neues Werk (als "eigenes Werk" markiert)
 * Bei vorhandenen Werken bleiben Titel, Notizen und schon gepflegte Komponisten/Dauern erhalten;
 * Werknummer, ISWC und Besetzung kommen von der GEMA.
 */
final class Werkverzeichnis
{
    /** Rollen, die in der Setlist als Komponist zählen. */
    private const KOMPONISTEN_ROLLEN = ['komponist', 'bearbeiter'];

    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @return list<array{gema_werknummer:string, titel:string, iswc:?string, dauer_sek:?int, besetzung:?string,
     *                    komponisten:list<array{nachname:string, vorname:?string}>}>
     */
    public static function lesen(string $datei): array
    {
        $zip = new ZipArchive();
        if ($zip->open($datei, ZipArchive::RDONLY) !== true) {
            throw new Eingabefehler('Die Datei ist keine Excel-Datei (.xlsx).');
        }
        try {
            $blaetter = self::blaetter($zip);
            $strings = self::gemeinsameTexte($zip);
            if (!isset($blaetter['Werkinformationen'])) {
                throw new Eingabefehler('Das Blatt „Werkinformationen“ fehlt. Bitte den Werke-Export aus dem GEMA-Portal verwenden.');
            }
            $info = self::tabelle($zip, $blaetter['Werkinformationen'], $strings, ['Werknummer', 'Titel']);
            $ids = isset($blaetter['Identifikatoren'])
                ? self::tabelle($zip, $blaetter['Identifikatoren'], $strings, ['Werknummer']) : [];
            $beteiligte = isset($blaetter['Beteiligte'])
                ? self::tabelle($zip, $blaetter['Beteiligte'], $strings, ['Werknummer', 'Name']) : [];
        } finally {
            $zip->close();
        }

        $iswc = [];
        foreach ($ids as $z) {
            $iswc[$z['Werknummer']] = self::wert($z['ISWC'] ?? '');
        }
        $komponisten = [];
        foreach ($beteiligte as $z) {
            $rolle = mb_strtolower((string)($z['Rolle'] ?? 'Komponist'));
            $istKomponist = false;
            foreach (self::KOMPONISTEN_ROLLEN as $r) {
                $istKomponist = $istKomponist || str_contains($rolle, $r);
            }
            $name = self::name($z['Name']);
            if ($istKomponist && $name && !in_array($name, $komponisten[$z['Werknummer']] ?? [], true)) {
                $komponisten[$z['Werknummer']][] = $name;
            }
        }

        $werke = [];
        foreach ($info as $z) {
            $nr = $z['Werknummer'];
            if (isset($werke[$nr])) {
                continue;
            }
            $werke[$nr] = [
                'gema_werknummer' => $nr,
                'titel' => trim($z['Titel']),
                'iswc' => $iswc[$nr] ?? self::wert($z['ISWC'] ?? ''),
                'dauer_sek' => self::dauer($z['Dauer'] ?? ''),
                'besetzung' => self::besetzung($z['Besetzung'] ?? ''),
                'komponisten' => $komponisten[$nr] ?? [],
            ];
        }
        if (!$werke) {
            throw new Eingabefehler('In der Datei wurden keine Werke gefunden.');
        }
        return array_values($werke);
    }

    /**
     * Werke übernehmen (eine Transaktion).
     * @return array{neu:int, aktualisiert:int, unveraendert:int}
     */
    public static function uebernehmen(array $werke): array
    {
        return Db::transaktion(static function () use ($werke) {
            $zaehler = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0];
            foreach ($werke as $g) {
                $vorhanden = self::passendesWerk($g);
                if ($vorhanden === null) {
                    Werke::speichern(null, $g + ['eigenes_werk' => true]);
                    $zaehler['neu']++;
                    continue;
                }
                $neu = [
                    'titel' => $vorhanden['titel'],
                    'gema_werknummer' => $g['gema_werknummer'],
                    'iswc' => $g['iswc'] ?? $vorhanden['iswc'],
                    'dauer_sek' => $vorhanden['dauer_sek'] ?? $g['dauer_sek'],
                    'besetzung' => $g['besetzung'] ?? $vorhanden['besetzung'],
                    'eigenes_werk' => true,
                    'notizen' => $vorhanden['notizen'],
                    'komponisten' => $vorhanden['komponisten'] ?: $g['komponisten'],
                ];
                $geaendert = $neu['gema_werknummer'] !== $vorhanden['gema_werknummer']
                    || $neu['iswc'] !== $vorhanden['iswc']
                    || $neu['besetzung'] !== $vorhanden['besetzung']
                    || $neu['dauer_sek'] !== ($vorhanden['dauer_sek'] === null ? null : (int)$vorhanden['dauer_sek'])
                    || !$vorhanden['eigenes_werk']
                    || $neu['komponisten'] !== $vorhanden['komponisten'];
                if ($geaendert) {
                    Werke::speichern((int)$vorhanden['id'], $neu);
                    $zaehler['aktualisiert']++;
                } else {
                    $zaehler['unveraendert']++;
                }
            }
            return $zaehler;
        });
    }

    private static function passendesWerk(array $g): ?array
    {
        $id = Db::wert('SELECT id FROM werk WHERE gema_werknummer = ? ORDER BY id LIMIT 1', [$g['gema_werknummer']]);
        if ($id === null) {
            $titel = self::vergleichstitel($g['titel']);
            foreach (Db::alle("SELECT id, titel FROM werk WHERE gema_werknummer IS NULL OR gema_werknummer = '' ORDER BY id") as $w) {
                if (self::vergleichstitel($w['titel']) === $titel) {
                    $id = $w['id'];
                    break;
                }
            }
        }
        if ($id === null) {
            return null;
        }
        $w = Werke::finden((int)$id);
        $w['dauer_sek'] = $w['dauer_sek'] === null ? null : (int)$w['dauer_sek'];
        return $w;
    }

    private static function vergleichstitel(string $titel): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($titel)));
    }

    /** "SCHOENEGG, STEFAN KARL" → Schoenegg / Stefan Karl */
    private static function name(string $roh): ?array
    {
        $roh = trim($roh);
        if ($roh === '' || $roh === '-') {
            return null;
        }
        [$nachname, $vorname] = array_map('trim', explode(',', $roh, 2)) + [1 => ''];
        $schreibweise = static fn(string $s) => $s === mb_strtoupper($s) ? mb_convert_case(mb_strtolower($s), MB_CASE_TITLE) : $s;
        return ['nachname' => $schreibweise($nachname), 'vorname' => $vorname === '' ? null : $schreibweise($vorname)];
    }

    /**
     * "KONTRABASS (Anzahl Instrumente 1, Anzahl Spieler/-innen 1, Anzahl Stimmen 0) | VIOLINE (Anzahl Instrumente 2, …)"
     * → "Kontrabass, 2× Violine"; Sammelbegriffe ohne Klammer ("KLEINE BESETZUNG") bleiben stehen.
     */
    private static function besetzung(string $roh): ?string
    {
        $teile = [];
        foreach (preg_split('/\s*\|\s*/u', trim($roh)) ?: [] as $teil) {
            if (!preg_match('/^([^(]+?)\s*(?:\((.*)\))?$/u', $teil, $m) || self::wert($m[1]) === null) {
                continue;
            }
            $name = self::schreibweise(trim($m[1]));
            $anzahl = preg_match('/Anzahl Instrumente\s+(\d+)/u', $m[2] ?? '', $a) ? (int)$a[1] : 1;
            if ($anzahl === 0 && preg_match('/Anzahl Stimmen\s+(\d+)/u', $m[2] ?? '', $st)) {
                $anzahl = (int)$st[1];   // Gesangsstimmen
            }
            $teile[] = ($anzahl > 1 ? $anzahl . '× ' : '') . $name;
        }
        return $teile ? implode(', ', $teile) : null;
    }

    /** GEMA schreibt in Großbuchstaben: "VIOLA DA GAMBA" → "Viola da Gamba" */
    private static function schreibweise(string $s): string
    {
        if ($s !== mb_strtoupper($s)) {
            return $s;
        }
        $s = mb_convert_case(mb_strtolower($s), MB_CASE_TITLE);
        return preg_replace_callback('/(?<=\s)(Da|Di|De|Del|Della|Und|Für|Mit|Ohne|Oder|Ad|Lib\.?)(?=\s|$)/u',
            static fn($m) => mb_strtolower($m[1]), $s);
    }

    /** "00:05:10" oder "05:10" → Sekunden */
    private static function dauer(string $wert): ?int
    {
        if (!preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', trim($wert), $m)) {
            return null;
        }
        $sek = (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
        return $sek > 0 ? $sek : null;
    }

    private static function wert(string $wert): ?string
    {
        $wert = trim($wert);
        return $wert === '' || $wert === '-' ? null : $wert;
    }

    /** @return array<string,string> Blattname → Pfad im Archiv */
    private static function blaetter(ZipArchive $zip): array
    {
        $wb = self::xml($zip, 'xl/workbook.xml');
        $rels = self::xml($zip, 'xl/_rels/workbook.xml.rels');
        if (!$wb || !$rels) {
            throw new Eingabefehler('Die Excel-Datei ist beschädigt.');
        }
        $ziele = [];
        foreach ($rels->getElementsByTagName('Relationship') as $r) {
            $ziel = $r->getAttribute('Target');
            $ziele[$r->getAttribute('Id')] = str_starts_with($ziel, '/') ? ltrim($ziel, '/') : 'xl/' . $ziel;
        }
        $blaetter = [];
        foreach ($wb->getElementsByTagNameNS(self::NS, 'sheet') as $s) {
            $rid = $s->getAttributeNS(self::NS_REL, 'id');
            if (isset($ziele[$rid])) {
                $blaetter[trim($s->getAttribute('name'))] = $ziele[$rid];
            }
        }
        return $blaetter;
    }

    /** @return list<string> */
    private static function gemeinsameTexte(ZipArchive $zip): array
    {
        $doc = self::xml($zip, 'xl/sharedStrings.xml');
        if (!$doc) {
            return [];
        }
        $texte = [];
        foreach ($doc->getElementsByTagNameNS(self::NS, 'si') as $si) {
            $t = '';
            foreach ($si->getElementsByTagNameNS(self::NS, 't') as $teil) {
                $t .= $teil->textContent;
            }
            $texte[] = $t;
        }
        return $texte;
    }

    /**
     * Liest ein Blatt als Liste von Zeilen [Überschrift => Wert]. Die Überschriftenzeile ist die erste
     * der ersten 5 Zeilen, die alle $pflicht-Spalten enthält. Zeilen ohne Werknummer werden übersprungen.
     * @return list<array<string,string>>
     */
    private static function tabelle(ZipArchive $zip, string $pfad, array $strings, array $pflicht): array
    {
        $doc = self::xml($zip, $pfad);
        if (!$doc) {
            throw new Eingabefehler('Die Excel-Datei ist beschädigt.');
        }
        $kopf = null;
        $zeilen = [];
        $nr = 0;
        foreach ($doc->getElementsByTagNameNS(self::NS, 'row') as $row) {
            $nr++;
            $werte = [];
            foreach ($row->getElementsByTagNameNS(self::NS, 'c') as $c) {
                $spalte = preg_replace('/\d+/', '', $c->getAttribute('r'));
                $werte[$spalte] = self::zellwert($c, $strings);
            }
            if ($kopf === null) {
                $kandidat = [];
                foreach ($werte as $spalte => $w) {
                    $w = trim($w);
                    if ($w !== '' && !isset($kandidat[$w])) {
                        $kandidat[$w] = $spalte;   // bei doppelten Überschriften zählt die erste
                    }
                }
                if (!array_diff($pflicht, array_keys($kandidat))) {
                    $kopf = $kandidat;
                } elseif ($nr >= 5) {
                    throw new Eingabefehler('Unbekannter Aufbau der Excel-Datei (Spalten ' . implode(', ', $pflicht) . ' nicht gefunden).');
                }
                continue;
            }
            $zeile = [];
            foreach ($kopf as $name => $spalte) {
                $zeile[$name] = trim($werte[$spalte] ?? '');
            }
            if (($zeile['Werknummer'] ?? '') !== '') {
                $zeilen[] = $zeile;
            }
        }
        if ($kopf === null) {
            throw new Eingabefehler('Ein Blatt der Excel-Datei ist leer.');
        }
        return $zeilen;
    }

    private static function zellwert(DOMElement $c, array $strings): string
    {
        $typ = $c->getAttribute('t');
        if ($typ === 'inlineStr') {
            return $c->textContent;
        }
        $v = $c->getElementsByTagNameNS(self::NS, 'v')->item(0)?->textContent ?? '';
        return $typ === 's' ? ($strings[(int)$v] ?? '') : $v;
    }

    private static function xml(ZipArchive $zip, string $pfad): ?DOMDocument
    {
        $inhalt = $zip->getFromName($pfad);
        if ($inhalt === false) {
            return null;
        }
        $doc = new DOMDocument();
        return $doc->loadXML($inhalt, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE) ? $doc : null;
    }
}
