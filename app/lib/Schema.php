<?php
declare(strict_types=1);

/**
 * Datenbankschema mit fortlaufenden Migrationen (Version in PRAGMA user_version).
 * Neue Änderungen immer als neue Nummer anhängen, bestehende nie ändern.
 */
final class Schema
{
    private const MIGRATIONEN = [
        1 => <<<'SQL'
            CREATE TABLE werk (
                id INTEGER PRIMARY KEY,
                titel TEXT NOT NULL,
                gema_werknummer TEXT,
                iswc TEXT,
                dauer_sek INTEGER,
                eigenes_werk INTEGER NOT NULL DEFAULT 0,
                notizen TEXT,
                erstellt TEXT NOT NULL DEFAULT (datetime('now')),
                geaendert TEXT NOT NULL DEFAULT (datetime('now'))
            );
            CREATE TABLE werk_komponist (
                id INTEGER PRIMARY KEY,
                werk_id INTEGER NOT NULL REFERENCES werk(id) ON DELETE CASCADE,
                position INTEGER NOT NULL,
                nachname TEXT NOT NULL,
                vorname TEXT
            );
            CREATE INDEX idx_komponist_werk ON werk_komponist(werk_id, position);

            CREATE TABLE auftritt (
                id INTEGER PRIMARY KEY,
                datum TEXT NOT NULL,
                uhrzeit TEXT,
                venue TEXT,
                stadt TEXT,
                veranstalter_name TEXT,
                veranstalter_kontakt TEXT,
                ensemble TEXT,
                anzahl_mitwirkende INTEGER,
                notizen TEXT,
                setlist_status TEXT NOT NULL DEFAULT 'offen'
                    CHECK (setlist_status IN ('offen','selbst_eingereicht','veranstalter_eingereicht','nicht_noetig')),
                setlist_eingereicht_am TEXT,
                abrechnung_status TEXT NOT NULL DEFAULT 'nicht_geprueft'
                    CHECK (abrechnung_status IN ('nicht_geprueft','gefunden','fehlt','reklamiert','erledigt')),
                reklamiert_am TEXT,
                ausschuettungstermin TEXT,
                erstellt TEXT NOT NULL DEFAULT (datetime('now')),
                geaendert TEXT NOT NULL DEFAULT (datetime('now'))
            );
            CREATE INDEX idx_auftritt_datum ON auftritt(datum);

            CREATE TABLE setlist_eintrag (
                id INTEGER PRIMARY KEY,
                auftritt_id INTEGER NOT NULL REFERENCES auftritt(id) ON DELETE CASCADE,
                werk_id INTEGER NOT NULL REFERENCES werk(id) ON DELETE RESTRICT,
                position INTEGER NOT NULL,
                dauer_sek INTEGER,
                mitwirkende INTEGER,
                abgerechnet TEXT NOT NULL DEFAULT 'ungeprueft'
                    CHECK (abgerechnet IN ('ungeprueft','gefunden','fehlt'))
            );
            CREATE INDEX idx_eintrag_auftritt ON setlist_eintrag(auftritt_id, position);
            CREATE INDEX idx_eintrag_werk ON setlist_eintrag(werk_id);

            CREATE TABLE einstellung (
                schluessel TEXT PRIMARY KEY,
                wert TEXT NOT NULL
            );

            CREATE TABLE sitzung (
                token_hash TEXT PRIMARY KEY,
                csrf TEXT NOT NULL,
                flash TEXT,
                erstellt INTEGER NOT NULL,
                laeuft_ab INTEGER NOT NULL
            );

            CREATE TABLE login_versuch (
                id INTEGER PRIMARY KEY,
                ip TEXT NOT NULL,
                zeit INTEGER NOT NULL
            );
            CREATE INDEX idx_login_versuch ON login_versuch(ip, zeit);
            SQL,
        2 => <<<'SQL'
            ALTER TABLE werk ADD COLUMN besetzung TEXT;
            SQL,
        3 => <<<'SQL'
            CREATE TABLE kategorie (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL
            );
            CREATE TABLE werk_kategorie (
                werk_id INTEGER NOT NULL REFERENCES werk(id) ON DELETE CASCADE,
                kategorie_id INTEGER NOT NULL REFERENCES kategorie(id) ON DELETE CASCADE,
                PRIMARY KEY (werk_id, kategorie_id)
            );
            CREATE INDEX idx_werk_kategorie_kategorie ON werk_kategorie(kategorie_id);
            SQL,
        4 => <<<'SQL'
            CREATE TABLE abrechnung (
                abrechnungsnummer TEXT PRIMARY KEY,
                termin TEXT NOT NULL,
                kuerzel TEXT NOT NULL,
                sparte TEXT NOT NULL,
                bereich TEXT NOT NULL,
                verteilart TEXT,
                betrag REAL NOT NULL,
                betrag_laut_summe REAL,
                importiert TEXT NOT NULL DEFAULT (datetime('now'))
            );
            CREATE INDEX idx_abrechnung_termin ON abrechnung(termin);
            CREATE TABLE abrechnung_posten (
                id INTEGER PRIMARY KEY,
                abrechnungsnummer TEXT NOT NULL REFERENCES abrechnung(abrechnungsnummer) ON DELETE CASCADE,
                quelle TEXT NOT NULL CHECK (quelle IN ('kompakt', 'detail')),
                werknummer TEXT,
                titel TEXT NOT NULL,
                betrag REAL NOT NULL,
                anteil REAL,
                anzahl REAL,
                nutzung_von TEXT,
                nutzung_bis TEXT,
                beschreibung TEXT
            );
            CREATE INDEX idx_posten_abrechnung ON abrechnung_posten(abrechnungsnummer);
            CREATE INDEX idx_posten_werk ON abrechnung_posten(werknummer);
            SQL,
    ];

    public static function migrieren(PDO $pdo): void
    {
        $version = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
        foreach (self::MIGRATIONEN as $nummer => $sql) {
            if ($nummer <= $version) {
                continue;
            }
            $pdo->beginTransaction();
            try {
                $pdo->exec($sql);
                $pdo->exec('PRAGMA user_version = ' . (int)$nummer);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }

    public static function version(): int
    {
        return array_key_last(self::MIGRATIONEN);
    }
}
