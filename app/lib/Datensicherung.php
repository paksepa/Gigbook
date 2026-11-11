<?php
declare(strict_types=1);

/** SQLite-Backup sowie JSON-Export/-Import aller Nutzdaten. */
final class Datensicherung
{
    public const FORMAT = 'gigbook';
    public const VERSION = 1;

    private const WERK_FELDER = ['id', 'titel', 'gema_werknummer', 'iswc', 'dauer_sek', 'besetzung', 'eigenes_werk', 'notizen', 'erstellt', 'geaendert'];
    private const AUFTRITT_FELDER = [
        'id', 'datum', 'uhrzeit', 'venue', 'stadt', 'veranstalter_name', 'veranstalter_kontakt', 'ensemble',
        'anzahl_mitwirkende', 'notizen', 'setlist_status', 'setlist_eingereicht_am', 'abrechnung_status',
        'reklamiert_am', 'ausschuettungstermin', 'erstellt', 'geaendert',
    ];
    private const EINTRAG_FELDER = ['werk_id', 'position', 'dauer_sek', 'mitwirkende', 'abgerechnet'];

    /** Konsistente Kopie der Datenbank (auch während die App läuft). */
    public static function datenbankKopie(string $ziel): void
    {
        if (is_file($ziel)) {
            unlink($ziel);
        }
        Db::pdo()->exec('VACUUM INTO ' . Db::pdo()->quote($ziel));
    }

    public static function exportieren(): array
    {
        $werke = Db::alle('SELECT * FROM werk ORDER BY id');
        $komponisten = Werke::komponistenFuer(array_column($werke, 'id'));
        $kategorienJeWerk = Kategorien::fuerWerke(array_column($werke, 'id'));
        $auftritte = Db::alle('SELECT * FROM auftritt ORDER BY datum, id');
        $eintraege = [];
        foreach (Db::alle('SELECT * FROM setlist_eintrag ORDER BY auftritt_id, position') as $e) {
            $eintraege[(int)$e['auftritt_id']][] = self::auswahl($e, self::EINTRAG_FELDER);
        }
        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exportiert' => date('c'),
            'einstellungen' => Einstellungen::alle(),
            'kategorien' => array_map(static fn($k) => ['id' => (int)$k['id'], 'name' => $k['name']],
                Db::alle('SELECT id, name FROM kategorie ORDER BY id')),
            'abrechnungen' => array_map(static fn($a) => $a + ['posten' => Db::alle(
                'SELECT quelle, werknummer, titel, betrag, anteil, anzahl, nutzung_von, nutzung_bis, beschreibung
                   FROM abrechnung_posten WHERE abrechnungsnummer = ? ORDER BY id', [$a['abrechnungsnummer']])],
                Db::alle('SELECT abrechnungsnummer, termin, kuerzel, sparte, bereich, verteilart, betrag, betrag_laut_summe FROM abrechnung ORDER BY termin, abrechnungsnummer')),
            'werke' => array_map(static fn($w) => self::auswahl($w, self::WERK_FELDER)
                + ['komponisten' => $komponisten[(int)$w['id']] ?? []]
                + ['kategorien' => array_column($kategorienJeWerk[(int)$w['id']] ?? [], 'id')], $werke),
            'auftritte' => array_map(static fn($a) => self::auswahl($a, self::AUFTRITT_FELDER)
                + ['setlist' => $eintraege[(int)$a['id']] ?? []], $auftritte),
        ];
    }

    /**
     * Ersetzt ALLE Werke, Auftritte und Einstellungen durch den Inhalt der JSON-Datei.
     * Läuft in einer Transaktion: bei einem Fehler bleibt alles unverändert.
     * @return array{werke:int, auftritte:int}
     */
    public static function importieren(array $d): array
    {
        // 'gigbuch' = Exporte aus der Zeit vor der Umbenennung in Gigbook
        if (!in_array($d['format'] ?? null, [self::FORMAT, 'gigbuch'], true) || !is_int($d['version'] ?? null)) {
            throw new Eingabefehler('Das ist keine Gigbook-Exportdatei.');
        }
        if ($d['version'] > self::VERSION) {
            throw new Eingabefehler('Die Datei stammt aus einer neueren Version der App.');
        }
        if (!is_array($d['werke'] ?? null) || !is_array($d['auftritte'] ?? null)) {
            throw new Eingabefehler('Die Datei ist unvollständig.');
        }

        try {
            return self::importAusfuehren($d);
        } catch (PDOException $e) {
            throw new Eingabefehler('Die Datei enthält ungültige oder widersprüchliche Daten (' . $e->getMessage() . ').');
        }
    }

    private static function importAusfuehren(array $d): array
    {
        return Db::transaktion(function () use ($d) {
            Db::ausfuehren('DELETE FROM setlist_eintrag');
            Db::ausfuehren('DELETE FROM auftritt');
            Db::ausfuehren('DELETE FROM abrechnung');
            Db::ausfuehren('DELETE FROM werk_kategorie');
            Db::ausfuehren('DELETE FROM kategorie');
            Db::ausfuehren('DELETE FROM werk_komponist');
            Db::ausfuehren('DELETE FROM werk');

            foreach ((array)($d['kategorien'] ?? []) as $i => $k) {
                if (!is_array($k) || !is_int($k['id'] ?? null) || trim((string)($k['name'] ?? '')) === '') {
                    throw new Eingabefehler('Kategorie Nr. ' . ($i + 1) . ' ist ungültig.');
                }
                Db::ausfuehren('INSERT INTO kategorie (id, name) VALUES (?, ?)', [$k['id'], trim((string)$k['name'])]);
            }

            foreach ($d['werke'] as $i => $w) {
                if (!is_array($w) || !is_int($w['id'] ?? null) || trim((string)($w['titel'] ?? '')) === '') {
                    throw new Eingabefehler('Werk Nr. ' . ($i + 1) . ' ist ungültig.');
                }
                $werk = self::auswahl($w, self::WERK_FELDER, ['erstellt', 'geaendert']);
                $werk['eigenes_werk'] = !empty($werk['eigenes_werk']) ? 1 : 0;
                $werk['dauer_sek'] = is_numeric($werk['dauer_sek']) ? (int)$werk['dauer_sek'] : null;
                self::einfuegen('werk', $werk);
                foreach (array_values((array)($w['komponisten'] ?? [])) as $pos => $k) {
                    if (trim((string)($k['nachname'] ?? '')) === '') {
                        continue;
                    }
                    Db::ausfuehren(
                        'INSERT INTO werk_komponist (werk_id, position, nachname, vorname) VALUES (?, ?, ?, ?)',
                        [$w['id'], $pos + 1, trim((string)$k['nachname']), ($k['vorname'] ?? null) ?: null]
                    );
                }
                foreach (array_unique(array_filter((array)($w['kategorien'] ?? []), 'is_int')) as $kid) {
                    Db::ausfuehren('INSERT INTO werk_kategorie (werk_id, kategorie_id) VALUES (?, ?)', [$w['id'], $kid]);
                }
            }

            foreach ($d['auftritte'] as $i => $a) {
                if (!is_array($a) || !is_int($a['id'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($a['datum'] ?? ''))) {
                    throw new Eingabefehler('Auftritt Nr. ' . ($i + 1) . ' ist ungültig.');
                }
                $zeile = self::auswahl($a, self::AUFTRITT_FELDER, ['erstellt', 'geaendert']);
                $zeile['setlist_status'] = isset(Status::SETLIST[$zeile['setlist_status'] ?? '']) ? $zeile['setlist_status'] : 'offen';
                $zeile['abrechnung_status'] = isset(Status::ABRECHNUNG[$zeile['abrechnung_status'] ?? '']) ? $zeile['abrechnung_status'] : 'nicht_geprueft';
                self::einfuegen('auftritt', $zeile);
                foreach (array_values((array)($a['setlist'] ?? [])) as $pos => $e) {
                    $eintrag = self::auswahl((array)$e, self::EINTRAG_FELDER);
                    $eintrag['auftritt_id'] = $a['id'];
                    $eintrag['position'] = $pos + 1;
                    $eintrag['abgerechnet'] = isset(Status::EINTRAG[$eintrag['abgerechnet'] ?? '']) ? $eintrag['abgerechnet'] : 'ungeprueft';
                    self::einfuegen('setlist_eintrag', $eintrag);
                }
            }

            foreach ((array)($d['abrechnungen'] ?? []) as $i => $a) {
                if (!is_array($a) || trim((string)($a['abrechnungsnummer'] ?? '')) === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($a['termin'] ?? ''))) {
                    throw new Eingabefehler('Abrechnung Nr. ' . ($i + 1) . ' ist ungültig.');
                }
                $posten = (array)($a['posten'] ?? []);
                $zeile = self::auswahl($a, ['abrechnungsnummer', 'termin', 'kuerzel', 'sparte', 'bereich', 'verteilart', 'betrag', 'betrag_laut_summe']);
                $zeile['abrechnungsnummer'] = (string)$zeile['abrechnungsnummer'];
                self::einfuegen('abrechnung', $zeile);
                foreach ($posten as $p) {
                    $p = self::auswahl((array)$p, ['quelle', 'werknummer', 'titel', 'betrag', 'anteil', 'anzahl', 'nutzung_von', 'nutzung_bis', 'beschreibung']);
                    self::einfuegen('abrechnung_posten', ['abrechnungsnummer' => $zeile['abrechnungsnummer']] + $p);
                }
            }

            if (is_array($d['einstellungen'] ?? null)) {
                Einstellungen::setzen(array_intersect_key($d['einstellungen'], Einstellungen::STANDARD));
            }
            return ['werke' => count($d['werke']), 'auftritte' => count($d['auftritte'])];
        });
    }

    /** Sicherheitskopie im Datenverzeichnis, z. B. vor einem Import. Behält die letzten 10. */
    public static function sicherheitskopie(string $anlass): string
    {
        $ordner = rtrim((string)Config::get('data_dir'), '/\\') . '/sicherungen';
        if (!is_dir($ordner)) {
            mkdir($ordner, 0770, true);
        }
        $ziel = $ordner . '/' . $anlass . '_' . date('Y-m-d_His') . '.sqlite';
        self::datenbankKopie($ziel);
        $dateien = glob($ordner . '/*.sqlite') ?: [];
        sort($dateien);
        foreach (array_slice($dateien, 0, max(0, count($dateien) - 10)) as $alt) {
            @unlink($alt);
        }
        return $ziel;
    }

    private static function auswahl(array $quelle, array $felder, array $optional = []): array
    {
        $ziel = [];
        foreach ($felder as $f) {
            if (array_key_exists($f, $quelle)) {
                $ziel[$f] = $quelle[$f];
            } elseif (!in_array($f, $optional, true)) {
                $ziel[$f] = null;
            }
        }
        return $ziel;
    }

    private static function einfuegen(string $tabelle, array $zeile): void
    {
        $spalten = implode(', ', array_keys($zeile));
        $platzhalter = implode(', ', array_fill(0, count($zeile), '?'));
        Db::ausfuehren("INSERT INTO $tabelle ($spalten) VALUES ($platzhalter)", array_values($zeile));
    }
}
