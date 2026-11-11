<?php
declare(strict_types=1);

final class EinstellungenController
{
    public static function zeigen(?string $fehler = null, ?array $eingabe = null): void
    {
        View::seite('einstellungen/index', [
            'werte' => $eingabe ?? Einstellungen::alle(),
            'fehler' => $fehler,
            'anzahl' => [
                'werke' => (int)Db::wert('SELECT COUNT(*) FROM werk'),
                'auftritte' => (int)Db::wert('SELECT COUNT(*) FROM auftritt'),
            ],
            'werksuche' => (bool)Config::get('werksuche'),
            'kategorien' => Kategorien::alle(),
        ], 'Einstellungen', 'einstellungen');
    }

    public static function speichern(): void
    {
        try {
            Einstellungen::setzen(array_intersect_key($_POST, Einstellungen::STANDARD));
        } catch (Eingabefehler $e) {
            http_response_code(422);
            self::zeigen($e->getMessage(), array_intersect_key($_POST, Einstellungen::STANDARD) + Einstellungen::alle());
            return;
        }
        Auth::flash('Einstellungen gespeichert.');
        weiterleiten('/einstellungen');
    }

    public static function backupDb(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gigbook');
        try {
            Datensicherung::datenbankKopie($tmp);
            ExportController::senden((string)file_get_contents($tmp), 'application/vnd.sqlite3', 'gigbook-backup_' . date('Y-m-d_His') . '.sqlite');
        } finally {
            @unlink($tmp);
        }
    }

    public static function jsonExport(): void
    {
        ExportController::senden(
            json_encode(Datensicherung::exportieren(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            'application/json; charset=utf-8',
            'gigbook-export_' . date('Y-m-d_His') . '.json'
        );
    }

    public static function jsonImport(): void
    {
        if (post('bestaetigt') !== '1') {
            self::zeigen('Bitte bestätigen, dass alle vorhandenen Daten ersetzt werden.');
            return;
        }
        $datei = $_FILES['datei'] ?? null;
        if (!is_array($datei) || ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::zeigen('Bitte eine JSON-Datei auswählen.');
            return;
        }
        $daten = json_decode((string)file_get_contents($datei['tmp_name']), true);
        if (!is_array($daten)) {
            self::zeigen('Die Datei ist kein gültiges JSON.');
            return;
        }
        try {
            $kopie = Datensicherung::sicherheitskopie('vor-import');
            $ergebnis = Datensicherung::importieren($daten);
        } catch (Eingabefehler $e) {
            self::zeigen('Import abgebrochen, es wurde nichts geändert: ' . $e->getMessage());
            return;
        }
        Auth::flash(sprintf(
            'Import abgeschlossen: %d Werke, %d Auftritte. Sicherheitskopie des vorherigen Stands: data/sicherungen/%s',
            $ergebnis['werke'], $ergebnis['auftritte'], basename($kopie)
        ));
        weiterleiten('/einstellungen');
    }

    /** Werke-Export aus dem GEMA-Portal (.xlsx) übernehmen – ergänzt nur, löscht nichts. */
    public static function werkverzeichnisImport(): void
    {
        $datei = $_FILES['datei'] ?? null;
        if (!is_array($datei) || ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::zeigen('Bitte die Excel-Datei aus dem GEMA-Portal auswählen.');
            return;
        }
        try {
            $werke = Werkverzeichnis::lesen($datei['tmp_name']);
            $kopie = Datensicherung::sicherheitskopie('vor-werkverzeichnis');
            $ergebnis = Werkverzeichnis::uebernehmen($werke);
        } catch (Eingabefehler $e) {
            self::zeigen('Werkverzeichnis nicht übernommen, es wurde nichts geändert: ' . $e->getMessage());
            return;
        }
        Auth::flash(sprintf(
            'Werkverzeichnis übernommen: %d neu, %d ergänzt, %d unverändert. Sicherheitskopie: data/sicherungen/%s',
            $ergebnis['neu'], $ergebnis['aktualisiert'], $ergebnis['unveraendert'], basename($kopie)
        ));
        weiterleiten('/werke');
    }

    public static function alleAbmelden(): void
    {
        Auth::alleAbmelden();
        weiterleiten('/login');
    }
}
