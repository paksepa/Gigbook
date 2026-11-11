<?php
declare(strict_types=1);

final class ExportController
{
    public static function seite(int $id): void
    {
        [$a, $setlist] = self::laden($id);
        $pruefung = GemaExport::pruefen(GemaExport::zeilenAusSetlist($setlist));
        View::seite('auftritte/export', [
            'a' => $a,
            'setlist' => $setlist,
            'pruefung' => $pruefung,
            'vorlageVorhanden' => is_file((string)Config::get('vorlage')),
        ], 'Export', 'auftritte');
    }

    public static function gema(int $id): void
    {
        [$a, $setlist] = self::laden($id);
        $zeilen = GemaExport::zeilenAusSetlist($setlist);
        $pruefung = GemaExport::pruefen($zeilen);
        if ($pruefung['fehler']) {
            throw new Eingabefehler(implode(' ', $pruefung['fehler']));
        }
        $tmp = tempnam(sys_get_temp_dir(), 'gema');
        try {
            (new GemaExport((string)Config::get('vorlage')))->erzeugen($zeilen, $tmp);
            self::senden(
                (string)file_get_contents($tmp),
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                SetlistDokument::dateiname($a, 'GEMA-Setlist', 'xlsx')
            );
        } finally {
            @unlink($tmp);
        }
    }

    public static function pdf(int $id): void
    {
        [$a, $setlist] = self::laden($id);
        self::senden(SetlistDokument::pdf($a, $setlist), 'application/pdf', SetlistDokument::dateiname($a, 'Setlist', 'pdf'));
    }

    public static function csv(int $id): void
    {
        [$a, $setlist] = self::laden($id);
        self::senden(SetlistDokument::csv($a, $setlist), 'text/csv; charset=utf-8', SetlistDokument::dateiname($a, 'Setlist', 'csv'));
    }

    private static function laden(int $id): array
    {
        $a = Auftritte::finden($id) ?? abbrechen(404, 'Auftritt nicht gefunden.');
        return [$a, Auftritte::setlist($id)];
    }

    public static function senden(string $inhalt, string $typ, string $dateiname): never
    {
        header('Content-Type: ' . $typ);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $dateiname) . '"');
        header('Content-Length: ' . strlen($inhalt));
        echo $inhalt;
        exit;
    }
}
