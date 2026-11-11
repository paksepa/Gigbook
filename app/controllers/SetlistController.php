<?php
declare(strict_types=1);

/** JSON-Endpunkte des Setlist-Editors. Antworten enthalten das neu gerenderte Listen-HTML. */
final class SetlistController
{
    public static function hinzufuegen(int $auftrittId): void
    {
        $werkId = (int)(json_eingabe()['werk_id'] ?? 0);
        Auftritte::eintragHinzufuegen($auftrittId, $werkId);
        self::antwort($auftrittId);
    }

    public static function aendern(int $eintragId): void
    {
        $d = json_eingabe();
        $eintrag = Auftritte::eintragAendern($eintragId, (string)($d['feld'] ?? ''), $d['wert'] ?? '');
        $setlist = Auftritte::setlist((int)$eintrag['auftritt_id']);
        json_antwort([
            'dauer' => Dauer::format($eintrag['dauer_sek'] === null ? null : (int)$eintrag['dauer_sek']),
            'mitwirkende' => $eintrag['mitwirkende'],
            'zusammenfassung' => self::zusammenfassung($setlist),
        ]);
    }

    public static function entfernen(int $eintragId): void
    {
        self::antwort(Auftritte::eintragEntfernen($eintragId));
    }

    public static function reihenfolge(int $auftrittId): void
    {
        Auftritte::reihenfolge($auftrittId, (array)(json_eingabe()['ids'] ?? []));
        self::antwort($auftrittId);
    }

    public static function alleAbgerechnet(int $auftrittId): void
    {
        Auftritte::alleAbgerechnet($auftrittId, (string)(json_eingabe()['wert'] ?? ''));
        self::antwort($auftrittId);
    }

    public static function zusammenfassung(array $setlist): string
    {
        $anzahl = count($setlist);
        $summe = array_sum(array_map(static fn($e) => (int)$e['dauer_sek'], $setlist));
        return $anzahl . ($anzahl === 1 ? ' Titel' : ' Titel') . ($summe > 0 ? ' · ' . Dauer::gesamt($summe) : '');
    }

    private static function antwort(int $auftrittId): void
    {
        Auftritte::finden($auftrittId) ?? abbrechen(404, 'Auftritt nicht gefunden.');
        $setlist = Auftritte::setlist($auftrittId);
        json_antwort([
            'html' => View::teil('auftritte/_setlist', ['setlist' => $setlist, 'auftrittId' => $auftrittId]),
            'zusammenfassung' => self::zusammenfassung($setlist),
        ]);
    }
}
