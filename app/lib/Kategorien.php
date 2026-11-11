<?php
declare(strict_types=1);

/** Frei benennbare Kategorien für Werke (z. B. Bands). Ein Werk kann in mehreren Kategorien sein. */
final class Kategorien
{
    /** @return list<array{id:int, name:string, anzahl:int}> alphabetisch */
    public static function alle(): array
    {
        $liste = array_map(static fn($k) => ['id' => (int)$k['id'], 'name' => $k['name'], 'anzahl' => (int)$k['anzahl']], Db::alle(
            'SELECT k.id, k.name, (SELECT COUNT(*) FROM werk_kategorie wk WHERE wk.kategorie_id = k.id) AS anzahl
               FROM kategorie k'
        ));
        usort($liste, static fn($a, $b) => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));
        return $liste;
    }

    public static function finden(int $id): ?array
    {
        return Db::eine('SELECT id, name FROM kategorie WHERE id = ?', [$id]);
    }

    /** @return array<int, list<array{id:int, name:string}>> werk_id → Kategorien (alphabetisch) */
    public static function fuerWerke(array $werkIds): array
    {
        $werkIds = array_values(array_unique(array_map('intval', $werkIds)));
        if (!$werkIds) {
            return [];
        }
        $platzhalter = implode(',', array_fill(0, count($werkIds), '?'));
        $ergebnis = [];
        foreach (Db::alle(
            "SELECT wk.werk_id, k.id, k.name FROM werk_kategorie wk JOIN kategorie k ON k.id = wk.kategorie_id
              WHERE wk.werk_id IN ($platzhalter)",
            $werkIds
        ) as $z) {
            $ergebnis[(int)$z['werk_id']][] = ['id' => (int)$z['id'], 'name' => $z['name']];
        }
        foreach ($ergebnis as &$liste) {
            usort($liste, static fn($a, $b) => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));
        }
        return $ergebnis;
    }

    /** Legt die Kategorie an oder liefert die vorhandene mit gleichem Namen (ohne Groß-/Kleinschreibung). */
    public static function anlegen(string $name): int
    {
        $name = self::name($name);
        foreach (Db::alle('SELECT id, name FROM kategorie') as $k) {
            if (mb_strtolower($k['name']) === mb_strtolower($name)) {
                return (int)$k['id'];
            }
        }
        Db::ausfuehren('INSERT INTO kategorie (name) VALUES (?)', [$name]);
        return Db::letzteId();
    }

    public static function umbenennen(int $id, string $name): void
    {
        $name = self::name($name);
        foreach (Db::alle('SELECT id, name FROM kategorie WHERE id <> ?', [$id]) as $k) {
            if (mb_strtolower($k['name']) === mb_strtolower($name)) {
                throw new Eingabefehler("Eine Kategorie „{$k['name']}“ gibt es schon.");
            }
        }
        if (Db::ausfuehren('UPDATE kategorie SET name = ? WHERE id = ?', [$name, $id]) === 0 && !self::finden($id)) {
            throw new Eingabefehler('Kategorie nicht gefunden.');
        }
    }

    /** Löscht nur die Kategorie, die Werke bleiben erhalten. */
    public static function loeschen(int $id): void
    {
        Db::ausfuehren('DELETE FROM kategorie WHERE id = ?', [$id]);
    }

    /** Setzt die Kategorien eines Werks (ersetzt die bisherigen). */
    public static function setzen(int $werkId, array $kategorieIds): void
    {
        Db::transaktion(static function () use ($werkId, $kategorieIds) {
            Db::ausfuehren('DELETE FROM werk_kategorie WHERE werk_id = ?', [$werkId]);
            foreach (self::gueltigeIds($kategorieIds) as $kid) {
                Db::ausfuehren('INSERT INTO werk_kategorie (werk_id, kategorie_id) VALUES (?, ?)', [$werkId, $kid]);
            }
        });
    }

    /**
     * Mehrere Werke einer Kategorie hinzufügen oder daraus entfernen.
     * @return int Anzahl tatsächlich geänderter Zuordnungen
     */
    public static function zuordnen(array $werkIds, int $kategorieId, bool $hinzufuegen): int
    {
        if (!self::finden($kategorieId)) {
            throw new Eingabefehler('Kategorie nicht gefunden.');
        }
        $werkIds = array_values(array_unique(array_filter(array_map('intval', $werkIds), static fn($i) => $i > 0)));
        if (!$werkIds) {
            throw new Eingabefehler('Bitte mindestens ein Werk auswählen.');
        }
        return Db::transaktion(static function () use ($werkIds, $kategorieId, $hinzufuegen) {
            $n = 0;
            foreach ($werkIds as $wid) {
                $n += $hinzufuegen
                    ? Db::ausfuehren('INSERT OR IGNORE INTO werk_kategorie (werk_id, kategorie_id)
                                      SELECT id, ? FROM werk WHERE id = ?', [$kategorieId, $wid])
                    : Db::ausfuehren('DELETE FROM werk_kategorie WHERE werk_id = ? AND kategorie_id = ?', [$wid, $kategorieId]);
            }
            return $n;
        });
    }

    /** @return list<int> */
    private static function gueltigeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
        if (!$ids) {
            return [];
        }
        $platzhalter = implode(',', array_fill(0, count($ids), '?'));
        return array_map('intval', array_column(Db::alle("SELECT id FROM kategorie WHERE id IN ($platzhalter)", $ids), 'id'));
    }

    private static function name(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name === '') {
            throw new Eingabefehler('Bitte einen Namen für die Kategorie eingeben.');
        }
        if (mb_strlen($name) > 80) {
            throw new Eingabefehler('Der Kategoriename ist zu lang (höchstens 80 Zeichen).');
        }
        return $name;
    }
}
