<?php
declare(strict_types=1);

final class Auftritte
{
    public const FELDER = [
        'datum', 'uhrzeit', 'venue', 'stadt', 'veranstalter_name', 'veranstalter_kontakt',
        'ensemble', 'anzahl_mitwirkende', 'notizen',
    ];

    public static function finden(int $id): ?array
    {
        return Db::eine('SELECT * FROM auftritt WHERE id = ?', [$id]);
    }

    /** Auftritte mit Anzahl Titel und Gesamtdauer, neueste zuerst. */
    public static function liste(?int $jahr = null): array
    {
        $sql = 'SELECT a.*,
                       (SELECT COUNT(*) FROM setlist_eintrag s WHERE s.auftritt_id = a.id) AS titel_anzahl,
                       (SELECT COALESCE(SUM(dauer_sek), 0) FROM setlist_eintrag s WHERE s.auftritt_id = a.id) AS gesamt_sek
                  FROM auftritt a';
        $parameter = [];
        if ($jahr !== null) {
            $sql .= ' WHERE substr(a.datum, 1, 4) = ?';
            $parameter[] = (string)$jahr;
        }
        return Db::alle($sql . ' ORDER BY a.datum DESC, a.uhrzeit DESC', $parameter);
    }

    /** @return list<int> */
    public static function jahre(): array
    {
        return array_map('intval', array_column(
            Db::alle('SELECT DISTINCT substr(datum, 1, 4) AS jahr FROM auftritt ORDER BY jahr DESC'),
            'jahr'
        ));
    }

    /** Validiert die Stammdaten aus einem Formular. */
    /**
     * Bisher verwendete Veranstalter (Name ohne Groß-/Kleinschreibung zusammengefasst)
     * mit dem zuletzt eingetragenen Kontakt.
     * @return list<array{name:string, kontakt:string}>
     */
    public static function veranstalter(): array
    {
        $liste = [];
        foreach (Db::alle(
            "SELECT veranstalter_name AS name, COALESCE(veranstalter_kontakt, '') AS kontakt FROM auftritt
              WHERE veranstalter_name IS NOT NULL AND trim(veranstalter_name) <> ''
              ORDER BY datum DESC, id DESC"
        ) as $v) {
            $schluessel = mb_strtolower(trim($v['name']));
            if (!isset($liste[$schluessel])) {
                $liste[$schluessel] = ['name' => trim($v['name']), 'kontakt' => trim($v['kontakt'])];
            } elseif ($liste[$schluessel]['kontakt'] === '') {
                $liste[$schluessel]['kontakt'] = trim($v['kontakt']);
            }
        }
        usort($liste, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $liste;
    }

    public static function stammdatenAusEingabe(array $eingabe): array
    {
        $s = static fn(string $k) => trim((string)($eingabe[$k] ?? ''));
        $d = [
            'datum' => datum_oder_null($s('datum'), 'Datum'),
            'uhrzeit' => $s('uhrzeit') === '' ? null : $s('uhrzeit'),
            'venue' => $s('venue'),
            'stadt' => $s('stadt'),
            'veranstalter_name' => $s('veranstalter_name'),
            'veranstalter_kontakt' => $s('veranstalter_kontakt'),
            'ensemble' => $s('ensemble'),
            'anzahl_mitwirkende' => zahl_oder_null($s('anzahl_mitwirkende'), 'Anzahl Mitwirkende'),
            'notizen' => $s('notizen'),
        ];
        if ($d['datum'] === null) {
            throw new Eingabefehler('Bitte ein Datum eingeben.');
        }
        if ($d['uhrzeit'] !== null && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $d['uhrzeit'])) {
            throw new Eingabefehler('Uhrzeit bitte als hh:mm eingeben.');
        }
        foreach ($d as $k => $v) {
            if ($v === '') {
                $d[$k] = null;
            }
        }
        return $d;
    }

    /** Neuen Auftritt anlegen, optional mit Setlist eines anderen Auftritts als Vorlage. */
    public static function anlegen(array $d, ?int $vorlageId = null): int
    {
        return Db::transaktion(function () use ($d, $vorlageId) {
            $spalten = implode(', ', self::FELDER);
            $platzhalter = implode(', ', array_fill(0, count(self::FELDER), '?'));
            Db::ausfuehren(
                "INSERT INTO auftritt ($spalten) VALUES ($platzhalter)",
                array_map(static fn($f) => $d[$f] ?? null, self::FELDER)
            );
            $id = Db::letzteId();
            if ($vorlageId !== null) {
                self::setlistKopieren($vorlageId, $id);
            }
            return $id;
        });
    }

    /**
     * Setlist kopieren. Einträge, deren Mitwirkende der Anzahl des Vorlage-Auftritts
     * entsprachen, übernehmen die Anzahl des neuen Auftritts.
     */
    public static function setlistKopieren(int $vonId, int $nachId): void
    {
        $von = self::finden($vonId);
        $nach = self::finden($nachId);
        if (!$von || !$nach) {
            throw new Eingabefehler('Vorlage-Auftritt nicht gefunden.');
        }
        $start = (int)Db::wert('SELECT COALESCE(MAX(position), 0) FROM setlist_eintrag WHERE auftritt_id = ?', [$nachId]);
        foreach (Db::alle('SELECT * FROM setlist_eintrag WHERE auftritt_id = ? ORDER BY position', [$vonId]) as $i => $e) {
            $mitwirkende = $e['mitwirkende'];
            if ($nach['anzahl_mitwirkende'] !== null
                && ($mitwirkende === null || (int)$mitwirkende === (int)$von['anzahl_mitwirkende'])) {
                $mitwirkende = $nach['anzahl_mitwirkende'];
            }
            Db::ausfuehren(
                'INSERT INTO setlist_eintrag (auftritt_id, werk_id, position, dauer_sek, mitwirkende) VALUES (?, ?, ?, ?, ?)',
                [$nachId, $e['werk_id'], $start + $i + 1, $e['dauer_sek'], $mitwirkende]
            );
        }
    }

    /**
     * Stammdaten aktualisieren. Ändert sich die Anzahl Mitwirkender, werden
     * Setlist-Einträge mit der alten Anzahl (nicht individuell geänderte) mitgezogen.
     */
    public static function aktualisieren(int $id, array $d): void
    {
        $alt = self::finden($id) ?? throw new Eingabefehler('Auftritt nicht gefunden.');
        Db::transaktion(function () use ($id, $d, $alt) {
            $set = implode(', ', array_map(static fn($f) => "$f = ?", self::FELDER));
            Db::ausfuehren(
                "UPDATE auftritt SET $set, geaendert = datetime('now') WHERE id = ?",
                [...array_map(static fn($f) => $d[$f] ?? null, self::FELDER), $id]
            );
            $altAnzahl = $alt['anzahl_mitwirkende'] === null ? null : (int)$alt['anzahl_mitwirkende'];
            if ($d['anzahl_mitwirkende'] !== null && $d['anzahl_mitwirkende'] !== $altAnzahl) {
                Db::ausfuehren(
                    'UPDATE setlist_eintrag SET mitwirkende = ?
                      WHERE auftritt_id = ? AND (mitwirkende IS NULL OR mitwirkende = ?)',
                    [$d['anzahl_mitwirkende'], $id, $alt['anzahl_mitwirkende']]
                );
            }
        });
    }

    public static function statusSetzen(int $id, array $eingabe): void
    {
        $s = static fn(string $k) => trim((string)($eingabe[$k] ?? ''));
        $setlist = $s('setlist_status');
        $abrechnung = $s('abrechnung_status');
        if (!isset(Status::SETLIST[$setlist]) || !isset(Status::ABRECHNUNG[$abrechnung])) {
            throw new Eingabefehler('Ungültiger Status.');
        }
        $eingereicht = datum_oder_null($s('setlist_eingereicht_am'), 'Eingereicht am');
        $reklamiert = datum_oder_null($s('reklamiert_am'), 'Reklamiert am');
        $ausschuettung = datum_oder_null($s('ausschuettungstermin'), 'Ausschüttungstermin');
        $heute = Fristen::heute()->format('Y-m-d');
        if ($setlist === 'selbst_eingereicht' && $eingereicht === null) {
            $eingereicht = $heute;
        }
        if ($abrechnung === 'reklamiert' && $reklamiert === null) {
            $reklamiert = $heute;
        }
        Db::ausfuehren(
            "UPDATE auftritt SET setlist_status = ?, setlist_eingereicht_am = ?, abrechnung_status = ?,
                    reklamiert_am = ?, ausschuettungstermin = ?, geaendert = datetime('now') WHERE id = ?",
            [$setlist, $eingereicht, $abrechnung, $reklamiert, $ausschuettung, $id]
        );
    }

    public static function loeschen(int $id): void
    {
        Db::ausfuehren('DELETE FROM auftritt WHERE id = ?', [$id]);
    }

    // ---------------------------------------------------------------- Setlist

    /** Setlist eines Auftritts inkl. Werkdaten und Komponisten. */
    public static function setlist(int $auftrittId): array
    {
        $eintraege = Db::alle(
            'SELECT s.*, w.titel, w.gema_werknummer, w.iswc, w.eigenes_werk
               FROM setlist_eintrag s JOIN werk w ON w.id = s.werk_id
              WHERE s.auftritt_id = ? ORDER BY s.position',
            [$auftrittId]
        );
        $komponisten = Werke::komponistenFuer(array_column($eintraege, 'werk_id'));
        foreach ($eintraege as &$e) {
            $e['komponisten'] = $komponisten[(int)$e['werk_id']] ?? [];
        }
        return $eintraege;
    }

    public static function eintrag(int $id): ?array
    {
        return Db::eine('SELECT * FROM setlist_eintrag WHERE id = ?', [$id]);
    }

    public static function eintragHinzufuegen(int $auftrittId, int $werkId): int
    {
        $auftritt = self::finden($auftrittId) ?? throw new Eingabefehler('Auftritt nicht gefunden.');
        $werk = Werke::finden($werkId) ?? throw new Eingabefehler('Werk nicht gefunden.');
        $position = (int)Db::wert('SELECT COALESCE(MAX(position), 0) + 1 FROM setlist_eintrag WHERE auftritt_id = ?', [$auftrittId]);
        Db::ausfuehren(
            'INSERT INTO setlist_eintrag (auftritt_id, werk_id, position, dauer_sek, mitwirkende) VALUES (?, ?, ?, ?, ?)',
            [$auftrittId, $werkId, $position, $werk['dauer_sek'], $auftritt['anzahl_mitwirkende']]
        );
        return Db::letzteId();
    }

    /** Ein Feld eines Setlist-Eintrags ändern (dauer, mitwirkende, abgerechnet). */
    public static function eintragAendern(int $id, string $feld, mixed $wert): array
    {
        $eintrag = self::eintrag($id) ?? throw new Eingabefehler('Eintrag nicht gefunden.');
        [$spalte, $neu] = match ($feld) {
            'dauer' => ['dauer_sek', Dauer::parsen((string)$wert)],
            'mitwirkende' => ['mitwirkende', zahl_oder_null(trim((string)$wert), 'Mitwirkende')],
            'abgerechnet' => isset(Status::EINTRAG[(string)$wert])
                ? ['abgerechnet', (string)$wert]
                : throw new Eingabefehler('Ungültiger Status.'),
            default => throw new Eingabefehler('Unbekanntes Feld.'),
        };
        Db::ausfuehren("UPDATE setlist_eintrag SET $spalte = ? WHERE id = ?", [$neu, $id]);
        return self::eintrag($id) + ['auftritt_id' => $eintrag['auftritt_id']];
    }

    public static function eintragEntfernen(int $id): int
    {
        $eintrag = self::eintrag($id) ?? throw new Eingabefehler('Eintrag nicht gefunden.');
        Db::transaktion(function () use ($eintrag) {
            Db::ausfuehren('DELETE FROM setlist_eintrag WHERE id = ?', [$eintrag['id']]);
            Db::ausfuehren(
                'UPDATE setlist_eintrag SET position = position - 1 WHERE auftritt_id = ? AND position > ?',
                [$eintrag['auftritt_id'], $eintrag['position']]
            );
        });
        return (int)$eintrag['auftritt_id'];
    }

    /** @param list<int> $ids vollständige neue Reihenfolge */
    public static function reihenfolge(int $auftrittId, array $ids): void
    {
        $ids = array_map('intval', $ids);
        $vorhanden = array_map('intval', array_column(
            Db::alle('SELECT id FROM setlist_eintrag WHERE auftritt_id = ?', [$auftrittId]),
            'id'
        ));
        $sortiert = $ids;
        sort($sortiert);
        sort($vorhanden);
        if ($sortiert !== $vorhanden) {
            throw new Eingabefehler('Die Setlist wurde zwischenzeitlich geändert. Bitte Seite neu laden.');
        }
        Db::transaktion(function () use ($ids) {
            foreach ($ids as $i => $id) {
                Db::ausfuehren('UPDATE setlist_eintrag SET position = ? WHERE id = ?', [$i + 1, $id]);
            }
        });
    }

    public static function alleAbgerechnet(int $auftrittId, string $wert): void
    {
        if (!isset(Status::EINTRAG[$wert])) {
            throw new Eingabefehler('Ungültiger Status.');
        }
        Db::ausfuehren('UPDATE setlist_eintrag SET abgerechnet = ? WHERE auftritt_id = ?', [$wert, $auftrittId]);
    }
}
