<?php
declare(strict_types=1);

final class Werke
{
    public static function finden(int $id): ?array
    {
        $werk = Db::eine('SELECT * FROM werk WHERE id = ?', [$id]);
        if ($werk) {
            $werk['komponisten'] = self::komponistenFuer([$id])[$id] ?? [];
            $werk['kategorien'] = Kategorien::fuerWerke([$id])[$id] ?? [];
        }
        return $werk;
    }

    /** @return array<int, list<array{nachname:string, vorname:?string}>> */
    public static function komponistenFuer(array $werkIds): array
    {
        $werkIds = array_values(array_unique(array_map('intval', $werkIds)));
        if (!$werkIds) {
            return [];
        }
        $platzhalter = implode(',', array_fill(0, count($werkIds), '?'));
        $ergebnis = [];
        foreach (Db::alle(
            "SELECT werk_id, nachname, vorname FROM werk_komponist
             WHERE werk_id IN ($platzhalter) ORDER BY werk_id, position",
            $werkIds
        ) as $k) {
            $ergebnis[(int)$k['werk_id']][] = ['nachname' => $k['nachname'], 'vorname' => $k['vorname']];
        }
        return $ergebnis;
    }

    /** "Duke Ellington, Billy Strayhorn" */
    public static function komponistenText(array $komponisten): string
    {
        return implode(', ', array_map(
            static fn($k) => trim(($k['vorname'] ?? '') . ' ' . $k['nachname']),
            $komponisten
        ));
    }

    /**
     * Alle Werke (optional gefiltert), mit Komponisten, Kategorien und Anzahl Aufführungen.
     * Gefiltert wird in PHP, damit Umlaute korrekt ohne Groß-/Kleinschreibung gefunden werden.
     * @param ?int $kategorie Kategorie-ID; 0 = Werke ohne Kategorie; null = alle
     */
    public static function liste(string $suche = '', bool $nurEigene = false, int $limit = 0, ?int $kategorie = null): array
    {
        $werke = Db::alle(
            'SELECT w.*,
                    (SELECT COUNT(*) FROM setlist_eintrag s
                       JOIN auftritt a ON a.id = s.auftritt_id
                      WHERE s.werk_id = w.id AND a.datum <= ?) AS auffuehrungen
               FROM werk w ' . ($nurEigene ? 'WHERE w.eigenes_werk = 1 ' : '') .
            'ORDER BY w.titel COLLATE NOCASE',
            [Fristen::heute()->format('Y-m-d')]
        );
        $komponisten = self::komponistenFuer(array_column($werke, 'id'));
        $kategorien = Kategorien::fuerWerke(array_column($werke, 'id'));
        foreach ($werke as &$w) {
            $w['komponisten'] = $komponisten[(int)$w['id']] ?? [];
            $w['kategorien'] = $kategorien[(int)$w['id']] ?? [];
        }
        unset($w);
        if ($kategorie !== null) {
            $werke = array_values(array_filter($werke, static fn($w) => $kategorie === 0
                ? !$w['kategorien']
                : in_array($kategorie, array_column($w['kategorien'], 'id'), true)));
        }

        $suche = mb_strtolower(trim($suche));
        if ($suche !== '') {
            $treffer = [];
            foreach ($werke as $w) {
                $titel = mb_strtolower($w['titel']);
                $text = $titel . ' ' . mb_strtolower(self::komponistenText($w['komponisten']))
                    . ' ' . mb_strtolower((string)$w['gema_werknummer'])
                    . ' ' . mb_strtolower((string)($w['besetzung'] ?? ''))
                    . ' ' . mb_strtolower(implode(' ', array_column($w['kategorien'], 'name')));
                if (str_contains($text, $suche)) {
                    // Titel, die mit dem Suchbegriff beginnen, zuerst
                    $w['_rang'] = str_starts_with($titel, $suche) ? 0 : (str_contains($titel, $suche) ? 1 : 2);
                    $treffer[] = $w;
                }
            }
            usort($treffer, static fn($a, $b) => [$a['_rang'], mb_strtolower($a['titel'])] <=> [$b['_rang'], mb_strtolower($b['titel'])]);
            $werke = $treffer;
        }
        return $limit > 0 ? array_slice($werke, 0, $limit) : $werke;
    }

    /**
     * Teilt eine Werkliste in Abschnitte je Kategorie (alphabetisch), am Ende "Ohne Kategorie".
     * Ein Werk in mehreren Kategorien erscheint in jedem seiner Abschnitte. Leere Abschnitte entfallen.
     * @param list<array{id:int, name:string}> $kategorien
     * @return list<array{id:?int, name:string, werke:list<array>}>
     */
    public static function gruppiert(array $werke, array $kategorien): array
    {
        $gruppen = [];
        foreach ($kategorien as $k) {
            $gruppen[$k['id']] = ['id' => $k['id'], 'name' => $k['name'], 'werke' => []];
        }
        $ohne = ['id' => null, 'name' => 'Ohne Kategorie', 'werke' => []];
        foreach ($werke as $w) {
            if (!$w['kategorien']) {
                $ohne['werke'][] = $w;
            }
            foreach ($w['kategorien'] as $k) {
                if (isset($gruppen[$k['id']])) {
                    $gruppen[$k['id']]['werke'][] = $w;
                }
            }
        }
        $gruppen[] = $ohne;
        return array_values(array_filter($gruppen, static fn($g) => $g['werke']));
    }

    /**
     * Werk anlegen oder aktualisieren.
     * Kategorien werden nur geändert, wenn 'kategorien' (Liste von IDs) übergeben wird.
     * @param array{titel:string, gema_werknummer?:?string, iswc?:?string, dauer_sek?:?int, besetzung?:?string, kategorien?:list<int>,
     *              eigenes_werk?:bool, notizen?:?string, komponisten?:list<array{nachname:string,vorname:?string}>} $d
     */
    public static function speichern(?int $id, array $d): int
    {
        $titel = trim($d['titel'] ?? '');
        if ($titel === '') {
            throw new Eingabefehler('Bitte einen Titel eingeben.');
        }
        $werte = [
            $titel,
            self::leerZuNull($d['gema_werknummer'] ?? null),
            self::leerZuNull($d['iswc'] ?? null),
            $d['dauer_sek'] ?? null,
            self::leerZuNull($d['besetzung'] ?? null),
            !empty($d['eigenes_werk']) ? 1 : 0,
            self::leerZuNull($d['notizen'] ?? null),
        ];
        $komponisten = [];
        foreach ($d['komponisten'] ?? [] as $k) {
            $nachname = trim((string)($k['nachname'] ?? ''));
            $vorname = trim((string)($k['vorname'] ?? ''));
            if ($nachname === '' && $vorname === '') {
                continue;
            }
            if ($nachname === '') {
                throw new Eingabefehler("Komponist „{$vorname}“: Nachname fehlt (Pflichtfeld bei der GEMA).");
            }
            $komponisten[] = [$nachname, $vorname === '' ? null : $vorname];
        }

        $kategorien = array_key_exists('kategorien', $d) ? (array)$d['kategorien'] : null;

        return Db::transaktion(function () use ($id, $werte, $komponisten, $kategorien) {
            if ($id === null) {
                Db::ausfuehren(
                    'INSERT INTO werk (titel, gema_werknummer, iswc, dauer_sek, besetzung, eigenes_werk, notizen)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    $werte
                );
                $id = Db::letzteId();
            } else {
                $n = Db::ausfuehren(
                    "UPDATE werk SET titel = ?, gema_werknummer = ?, iswc = ?, dauer_sek = ?, besetzung = ?, eigenes_werk = ?,
                            notizen = ?, geaendert = datetime('now') WHERE id = ?",
                    [...$werte, $id]
                );
                if ($n === 0) {
                    throw new Eingabefehler('Werk nicht gefunden.');
                }
                Db::ausfuehren('DELETE FROM werk_komponist WHERE werk_id = ?', [$id]);
            }
            foreach ($komponisten as $pos => [$nachname, $vorname]) {
                Db::ausfuehren(
                    'INSERT INTO werk_komponist (werk_id, position, nachname, vorname) VALUES (?, ?, ?, ?)',
                    [$id, $pos + 1, $nachname, $vorname]
                );
            }
            if ($kategorien !== null) {
                Kategorien::setzen($id, $kategorien);
            }
            return $id;
        });
    }

    /** Anzahl Setlist-Einträge, die das Werk verwenden. */
    public static function verwendungen(int $id): int
    {
        return (int)Db::wert('SELECT COUNT(*) FROM setlist_eintrag WHERE werk_id = ?', [$id]);
    }

    public static function loeschen(int $id): void
    {
        if (self::verwendungen($id) > 0) {
            throw new Eingabefehler('Das Werk steht in Setlists und kann nicht gelöscht werden.');
        }
        Db::ausfuehren('DELETE FROM werk WHERE id = ?', [$id]);
    }

    private static function leerZuNull(?string $wert): ?string
    {
        $wert = trim((string)$wert);
        return $wert === '' ? null : $wert;
    }
}
