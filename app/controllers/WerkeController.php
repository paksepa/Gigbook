<?php
declare(strict_types=1);

final class WerkeController
{
    public static function liste(): void
    {
        $suche = trim((string)($_GET['q'] ?? ''));
        $nurEigene = !empty($_GET['eigene']);
        $kategorie = isset($_GET['kat']) && preg_match('/^\d+$/', (string)$_GET['kat']) ? (int)$_GET['kat'] : null;
        View::seite('werke/liste', [
            'werke' => Werke::liste($suche, $nurEigene, 0, $kategorie),
            'suche' => $suche,
            'nurEigene' => $nurEigene,
            'kategorie' => $kategorie,
            'kategorien' => Kategorien::alle(),
            'auswahl' => !empty($_GET['auswahl']),
        ], 'Werke', 'werke');
    }

    public static function neu(): void
    {
        $werk = ['id' => null, 'titel' => trim((string)($_GET['titel'] ?? '')), 'komponisten' => [],
            'kategorien' => isset($_GET['kat']) && Kategorien::finden((int)$_GET['kat'])
                ? [['id' => (int)$_GET['kat'], 'name' => '']] : []];
        self::formular($werk);
    }

    /** Mehrfachauswahl: ausgewählte Werke einer Kategorie hinzufügen oder daraus entfernen. */
    public static function kategorienZuordnen(): void
    {
        $zurueck = self::listenUrl($_POST);
        try {
            $kategorieId = post('kategorie_id') === 'neu'
                ? Kategorien::anlegen(post('neue_kategorie'))
                : (int)post('kategorie_id');
            $hinzufuegen = post('aktion') !== 'entfernen';
            $n = Kategorien::zuordnen((array)($_POST['werke'] ?? []), $kategorieId, $hinzufuegen);
            $name = Kategorien::finden($kategorieId)['name'];
            Auth::flash($hinzufuegen
                ? "$n Werk" . ($n === 1 ? '' : 'e') . " zu „{$name}“ hinzugefügt."
                : "$n Werk" . ($n === 1 ? '' : 'e') . " aus „{$name}“ entfernt.");
        } catch (Eingabefehler $e) {
            Auth::flash($e->getMessage());
            weiterleiten(self::listenUrl($_POST, true));
        }
        weiterleiten($zurueck);
    }

    public static function kategorieUmbenennen(int $id): void
    {
        try {
            Kategorien::umbenennen($id, post('name'));
            Auth::flash('Kategorie umbenannt.');
        } catch (Eingabefehler $e) {
            Auth::flash($e->getMessage());
        }
        weiterleiten('/einstellungen#kategorien');
    }

    public static function kategorieLoeschen(int $id): void
    {
        $k = Kategorien::finden($id) ?? abbrechen(404, 'Kategorie nicht gefunden.');
        Kategorien::loeschen($id);
        Auth::flash("Kategorie „{$k['name']}“ gelöscht. Die Werke sind erhalten.");
        weiterleiten('/einstellungen#kategorien');
    }

    /** "/werke?q=…&kat=…" aus den mitgeschickten Filtern */
    private static function listenUrl(array $quelle, bool $auswahl = false): string
    {
        $query = array_filter([
            'q' => trim((string)($quelle['q'] ?? '')),
            'eigene' => !empty($quelle['eigene']) ? '1' : '',
            'kat' => preg_match('/^\d+$/', (string)($quelle['kat'] ?? '')) ? (string)$quelle['kat'] : '',
            'auswahl' => $auswahl ? '1' : '',
        ], static fn($v) => $v !== '');
        return '/werke' . ($query ? '?' . http_build_query($query) : '');
    }

    public static function bearbeiten(int $id): void
    {
        $werk = Werke::finden($id) ?? abbrechen(404, 'Werk nicht gefunden.');
        self::formular($werk);
    }

    public static function anlegen(): void
    {
        self::sichern(null);
    }

    public static function speichern(int $id): void
    {
        Werke::finden($id) ?? abbrechen(404, 'Werk nicht gefunden.');
        self::sichern($id);
    }

    public static function loeschen(int $id): void
    {
        try {
            Werke::loeschen($id);
            Auth::flash('Werk gelöscht.');
            weiterleiten('/werke');
        } catch (Eingabefehler $e) {
            Auth::flash($e->getMessage());
            weiterleiten("/werke/$id");
        }
    }

    /** Live-Suche für den Setlist-Editor. */
    public static function apiSuche(): void
    {
        $suche = trim((string)($_GET['q'] ?? ''));
        $werke = $suche === '' ? [] : Werke::liste($suche, false, 15);
        json_antwort(['werke' => array_map(static fn($w) => [
            'id' => (int)$w['id'],
            'titel' => $w['titel'],
            'komponisten' => Werke::komponistenText($w['komponisten']),
            'dauer' => Dauer::format($w['dauer_sek'] === null ? null : (int)$w['dauer_sek']),
            'eigen' => (bool)$w['eigenes_werk'],
        ], $werke)]);
    }

    /** Schnellanlage aus dem Setlist-Editor (nur Titel). */
    public static function apiAnlegen(): void
    {
        $d = json_eingabe();
        $id = Werke::speichern(null, ['titel' => (string)($d['titel'] ?? '')]);
        json_antwort(['id' => $id]);
    }

    /** Optionale Suche in der GEMA-Repertoiredatenbank. */
    public static function apiGemaSuche(): void
    {
        if (!Config::get('werksuche')) {
            json_antwort(['fehler' => 'Die GEMA-Werksuche ist deaktiviert.'], 503);
        }
        $titel = trim((string)($_GET['titel'] ?? ''));
        $komponist = trim((string)($_GET['komponist'] ?? ''));
        if (mb_strlen($titel) < 2) {
            json_antwort(['fehler' => 'Bitte mindestens zwei Zeichen des Titels eingeben.'], 422);
        }
        $ergebnis = GemaWerksuche::suchen($titel, $komponist);
        json_antwort($ergebnis, isset($ergebnis['fehler']) ? 502 : 200);
    }

    private static function sichern(?int $id): void
    {
        $komponisten = [];
        foreach ((array)($_POST['komponisten'] ?? []) as $k) {
            if (is_array($k)) {
                $komponisten[] = ['nachname' => (string)($k['nachname'] ?? ''), 'vorname' => (string)($k['vorname'] ?? '')];
            }
        }
        $daten = [
            'titel' => post('titel'),
            'gema_werknummer' => post('gema_werknummer'),
            'iswc' => post('iswc'),
            'besetzung' => post('besetzung'),
            'kategorien' => array_map('intval', (array)($_POST['kategorien'] ?? [])),
            'eigenes_werk' => post('eigenes_werk') === '1',
            'notizen' => post('notizen'),
            'komponisten' => $komponisten,
        ];
        try {
            $daten['dauer_sek'] = Dauer::parsen(post('dauer'));
            if (post('neue_kategorie') !== '') {
                $daten['kategorien'][] = Kategorien::anlegen(post('neue_kategorie'));
            }
            $neueId = Werke::speichern($id, $daten);
        } catch (Eingabefehler $e) {
            http_response_code(422);
            $daten['kategorien'] = array_map(static fn($k) => ['id' => $k, 'name' => ''], $daten['kategorien']);
            self::formular(['id' => $id, 'dauer_eingabe' => post('dauer'), 'neue_kategorie' => post('neue_kategorie')] + $daten, $e->getMessage());
            return;
        }
        Auth::flash('Werk gespeichert.');
        $zurueck = post('zurueck');
        weiterleiten(preg_match('#^/auftritte/\d+$#', $zurueck) ? $zurueck : '/werke');
    }

    private static function formular(array $werk, ?string $fehler = null): void
    {
        $zurueck = (string)($_GET['zurueck'] ?? $_POST['zurueck'] ?? '');
        View::seite('werke/formular', [
            'werk' => $werk,
            'fehler' => $fehler,
            'verwendungen' => $werk['id'] ? Werke::verwendungen((int)$werk['id']) : 0,
            'zurueck' => preg_match('#^/auftritte/\d+$#', $zurueck) ? $zurueck : '',
            'werksuche' => (bool)Config::get('werksuche'),
            'alleKategorien' => Kategorien::alle(),
        ], $werk['id'] ? 'Werk bearbeiten' : 'Neues Werk', 'werke');
    }
}
