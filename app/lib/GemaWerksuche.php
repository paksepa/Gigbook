<?php
declare(strict_types=1);

/**
 * OPTIONALES Modul: Suche in der öffentlichen GEMA-Repertoiresuche.
 *
 * Es gibt keine offizielle API. Genutzt wird derselbe Endpunkt wie die Webseite
 * https://www.gema.de/portal/app/repertoiresuche/werksuche (vgl. github.com/DonMikone/PyGEMASearch).
 * Ändert die GEMA etwas, schlägt die Suche fehl – die App funktioniert normal weiter,
 * der Nutzer sieht nur einen Hinweis. Abschaltbar mit APP_WERKSUCHE=0 bzw. 'werksuche' => false.
 *
 * Es werden nur der eingegebene Titel (und ggf. Komponist zur Filterung, lokal) verwendet;
 * keine persönlichen Daten werden übertragen.
 */
final class GemaWerksuche
{
    private const KONFIG_URL = 'https://www.gema.de/portal/config/frontend';
    private const SUCH_URL = 'https://www.gema.de/portal/api/public/cloud/v1/main/repertoiresuche/suche';
    private const TIMEOUT = 8;

    /**
     * @return array{treffer: list<array>}|array{fehler: string}
     */
    public static function suchen(string $titel, string $komponist = ''): array
    {
        try {
            if (!function_exists('curl_init')) {
                return ['fehler' => 'Die GEMA-Suche benötigt die PHP-Erweiterung curl.'];
            }
            $konfig = self::anfrage('GET', self::KONFIG_URL);
            $apiKey = is_array($konfig) ? ($konfig['apiKey'] ?? null) : null;
            if (!is_string($apiKey) || $apiKey === '') {
                return ['fehler' => 'Die GEMA-Suche ist gerade nicht nutzbar (Schlüssel nicht gefunden).'];
            }
            $antwort = self::anfrage('POST', self::SUCH_URL, [
                'queryCriteria' => [['field' => 'WERK_TITEL', 'matchOperator' => 'FUZZY', 'value' => $titel]],
                'filters' => (object)[],
                'pagination' => ['page' => 0, 'pageSize' => 25],
            ], $apiKey);
            if (!is_array($antwort) || !is_array($antwort['titel'] ?? null)) {
                return ['fehler' => 'Die GEMA-Suche hat ein unerwartetes Format geliefert.'];
            }
            return ['treffer' => self::auswerten($antwort['titel'], $komponist)];
        } catch (Throwable $e) {
            error_log('[Gigbook] GEMA-Werksuche: ' . $e->getMessage());
            return ['fehler' => 'Die GEMA-Suche ist gerade nicht erreichbar.'];
        }
    }

    /** Antwort in ein schlankes, stabiles Format bringen. Öffentlich für Tests. */
    public static function auswerten(array $werke, string $komponistFilter = ''): array
    {
        $treffer = [];
        foreach ($werke as $w) {
            if (!is_array($w) || !is_string($w['titel'] ?? null)) {
                continue;
            }
            $personen = [];
            foreach ((array)($w['urheber'] ?? []) as $u) {
                if (!is_array($u)) {
                    continue;
                }
                $nachname = trim((string)($u['nachname'] ?? ''));
                $vorname = trim((string)($u['vorname'] ?? ''));
                if ($nachname === '') {
                    $nachname = trim((string)($u['name'] ?? ''));
                }
                if ($nachname === '') {
                    continue;
                }
                $personen[] = ['nachname' => $nachname, 'vorname' => $vorname, 'rolle' => (string)($u['rolle'] ?? '')];
            }
            $komponisten = array_values(array_filter($personen, static fn($p) => self::istKomponist($p['rolle'])));
            if (!$komponisten) {
                $komponisten = $personen; // Rolle unbekannt: alle anzeigen, Nutzer prüft
            }
            $text = mb_strtolower(implode(' ', array_map(static fn($p) => $p['vorname'] . ' ' . $p['nachname'], $personen)));
            if ($komponistFilter !== '' && !str_contains($text, mb_strtolower($komponistFilter))) {
                continue;
            }
            $treffer[] = [
                'titel' => $w['titel'],
                'werknummer' => (string)($w['werkfassungsnummer'] ?? $w['werknummer'] ?? ''),
                'iswc' => is_string($w['iswc'] ?? null) ? $w['iswc'] : '',
                'komponisten' => array_map(static fn($p) => ['nachname' => $p['nachname'], 'vorname' => $p['vorname']], $komponisten),
            ];
        }
        return $treffer;
    }

    /** Rollenkennungen der GEMA: K = Komponist, KT = Komponist und Textdichter (andere: T, B, V …). */
    private static function istKomponist(string $rolle): bool
    {
        $r = strtoupper(trim($rolle));
        return $r === 'K' || $r === 'KT' || $r === 'CA' || $r === 'C' || str_contains($r, 'KOMPON');
    }

    private static function anfrage(string $methode, string $url, ?array $json = null, ?string $apiKey = null): mixed
    {
        $kopf = [
            'Accept: application/json',
            'Accept-Language: de',
            'User-Agent: ' . APP_NAME . '/' . APP_VERSION . ' (private Werkverwaltung)',
        ];
        if ($apiKey !== null) {
            $kopf[] = 'API_KEY: ' . $apiKey;
            $kopf[] = 'Active-Role: OPAL_NUTZER';
            $kopf[] = 'Content-Type: application/json';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => $kopf,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        if ($methode === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_THROW_ON_ERROR));
        }
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status !== 200) {
            throw new RuntimeException("HTTP $status $fehler");
        }
        return json_decode((string)$body, true, 64, JSON_THROW_ON_ERROR);
    }
}
