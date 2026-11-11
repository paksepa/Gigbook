<?php
declare(strict_types=1);

final class AuftritteController
{
    public static function liste(): void
    {
        $jahre = Auftritte::jahre();
        $jahr = isset($_GET['jahr']) && preg_match('/^\d{4}$/', (string)$_GET['jahr']) ? (int)$_GET['jahr'] : null;
        $filter = (string)($_GET['filter'] ?? '');
        $heute = Fristen::heute();
        $e = Einstellungen::alle();

        $auftritte = [];
        foreach (Auftritte::liste($jahr) as $a) {
            $a['setlist_frist'] = Fristen::setlistFrist($a, $heute, $e);
            $a['reklamation_frist'] = Fristen::reklamationsFrist($a, $heute, $e);
            $vergangen = $a['datum'] <= $heute->format('Y-m-d');
            $passt = match ($filter) {
                'setlist' => $a['setlist_status'] === 'offen' && $vergangen,
                'abrechnung' => in_array($a['abrechnung_status'], ['nicht_geprueft', 'fehlt', 'reklamiert'], true) && $vergangen,
                'kommend' => !$vergangen,
                default => true,
            };
            if ($passt) {
                $auftritte[] = $a;
            }
        }
        View::seite('auftritte/liste', [
            'auftritte' => $auftritte,
            'jahre' => $jahre,
            'jahr' => $jahr,
            'filter' => $filter,
        ], 'Auftritte', 'auftritte');
    }

    public static function neu(): void
    {
        $vorlageId = isset($_GET['vorlage']) ? (int)$_GET['vorlage'] : null;
        $auftritt = ['id' => null, 'datum' => Fristen::heute()->format('Y-m-d')];
        if ($vorlageId && ($vorlage = Auftritte::finden($vorlageId))) {
            foreach (['uhrzeit', 'venue', 'stadt', 'veranstalter_name', 'veranstalter_kontakt', 'ensemble', 'anzahl_mitwirkende'] as $f) {
                $auftritt[$f] = $vorlage[$f];
            }
        }
        self::formular($auftritt, $vorlageId);
    }

    public static function anlegen(): void
    {
        $vorlageId = post('vorlage_id') !== '' ? (int)post('vorlage_id') : null;
        try {
            $daten = Auftritte::stammdatenAusEingabe($_POST);
            $id = Auftritte::anlegen($daten, $vorlageId);
        } catch (Eingabefehler $e) {
            http_response_code(422);
            self::formular(['id' => null] + array_map(static fn($v) => is_string($v) ? trim($v) : '', $_POST), $vorlageId, $e->getMessage());
            return;
        }
        Auth::flash($vorlageId ? 'Auftritt angelegt, Setlist übernommen.' : 'Auftritt angelegt.');
        weiterleiten("/auftritte/$id");
    }

    public static function zeigen(int $id): void
    {
        $auftritt = Auftritte::finden($id) ?? abbrechen(404, 'Auftritt nicht gefunden.');
        $heute = Fristen::heute();
        $e = Einstellungen::alle();
        View::seite('auftritte/detail', [
            'a' => $auftritt,
            'setlist' => Auftritte::setlist($id),
            'setlistFrist' => Fristen::setlistFrist($auftritt, $heute, $e),
            'reklamationFrist' => Fristen::reklamationsFrist($auftritt, $heute, $e),
            'reklamationBis' => $auftritt['ausschuettungstermin']
                ? Fristen::reklamationBis($auftritt['ausschuettungstermin'], $e['frist_reklamation_monate'])
                : null,
            'setlistFaellig' => Fristen::setlistFaelligAm($auftritt['datum'], $e['frist_setlist_tage']),
        ], $auftritt['venue'] ?: 'Auftritt', 'auftritte');
    }

    public static function bearbeiten(int $id): void
    {
        $auftritt = Auftritte::finden($id) ?? abbrechen(404, 'Auftritt nicht gefunden.');
        self::formular($auftritt);
    }

    public static function speichern(int $id): void
    {
        Auftritte::finden($id) ?? abbrechen(404, 'Auftritt nicht gefunden.');
        try {
            Auftritte::aktualisieren($id, Auftritte::stammdatenAusEingabe($_POST));
        } catch (Eingabefehler $e) {
            http_response_code(422);
            self::formular(['id' => $id] + array_map(static fn($v) => is_string($v) ? trim($v) : '', $_POST), null, $e->getMessage());
            return;
        }
        Auth::flash('Gespeichert.');
        weiterleiten("/auftritte/$id");
    }

    public static function status(int $id): void
    {
        Auftritte::finden($id) ?? abbrechen(404, 'Auftritt nicht gefunden.');
        try {
            Auftritte::statusSetzen($id, $_POST);
            Auth::flash('Status gespeichert.');
        } catch (Eingabefehler $e) {
            Auth::flash($e->getMessage());
        }
        weiterleiten("/auftritte/$id");
    }

    public static function loeschen(int $id): void
    {
        Auftritte::loeschen($id);
        Auth::flash('Auftritt gelöscht.');
        weiterleiten('/auftritte');
    }

    private static function formular(array $auftritt, ?int $vorlageId = null, ?string $fehler = null): void
    {
        $vorlagen = array_slice(Auftritte::liste(), 0, 50);
        View::seite('auftritte/formular', [
            'a' => $auftritt,
            'vorlageId' => $vorlageId,
            'vorlagen' => array_values(array_filter($vorlagen, static fn($v) => (int)$v['titel_anzahl'] > 0)),
            'fehler' => $fehler,
            'ensembles' => array_column(Db::alle('SELECT DISTINCT ensemble FROM auftritt WHERE ensemble IS NOT NULL ORDER BY ensemble'), 'ensemble'),
            'staedte' => array_column(Db::alle('SELECT DISTINCT stadt FROM auftritt WHERE stadt IS NOT NULL ORDER BY stadt'), 'stadt'),
            'veranstalter' => Auftritte::veranstalter(),
        ], $auftritt['id'] ? 'Auftritt bearbeiten' : 'Neuer Auftritt', 'auftritte');
    }
}
