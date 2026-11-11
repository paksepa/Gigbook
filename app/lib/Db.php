<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pfad(): string
    {
        $ordner = rtrim((string)Config::get('data_dir'), '/\\');
        $pfad = $ordner . '/gigbook.sqlite';
        // Datenbank aus der Zeit vor der Umbenennung (Gigbuch) einmalig übernehmen
        if (!is_file($pfad) && is_file($ordner . '/gigbuch.sqlite')) {
            @rename($ordner . '/gigbuch.sqlite', $pfad);
        }
        return $pfad;
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::oeffnen(self::pfad());
    }

    /** Für Tests: eigene Verbindung (z. B. ':memory:') verwenden. */
    public static function verwenden(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function oeffnen(string $pfad): PDO
    {
        if ($pfad !== ':memory:') {
            $ordner = dirname($pfad);
            if (!is_dir($ordner) && !mkdir($ordner, 0770, true) && !is_dir($ordner)) {
                throw new RuntimeException("Datenverzeichnis $ordner kann nicht angelegt werden.");
            }
        }
        $pdo = new PDO('sqlite:' . $pfad, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        Schema::migrieren($pdo);
        return $pdo;
    }

    public static function alle(string $sql, array $parameter = []): array
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($parameter);
        return $s->fetchAll();
    }

    public static function eine(string $sql, array $parameter = []): ?array
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($parameter);
        $zeile = $s->fetch();
        return $zeile === false ? null : $zeile;
    }

    public static function wert(string $sql, array $parameter = []): mixed
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($parameter);
        $wert = $s->fetchColumn();
        return $wert === false ? null : $wert;
    }

    public static function ausfuehren(string $sql, array $parameter = []): int
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($parameter);
        return $s->rowCount();
    }

    public static function letzteId(): int
    {
        return (int)self::pdo()->lastInsertId();
    }

    /**
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function transaktion(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $ergebnis = $fn();
            $pdo->commit();
            return $ergebnis;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
