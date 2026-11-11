<?php
declare(strict_types=1);

/** Front-Controller: Routing, Login-Pflicht, CSRF, Sicherheits-Header, Fehlerseiten. */
final class App
{
    /** @return list<array{0:string,1:string,2:callable,3?:bool}> [Methode, Muster, Handler, öffentlich] */
    private static function routen(): array
    {
        return [
            ['GET', '/login', [AuthController::class, 'formular'], true],
            ['POST', '/login', [AuthController::class, 'anmelden'], true],
            ['POST', '/logout', [AuthController::class, 'abmelden']],

            ['GET', '/', [DashboardController::class, 'index']],

            ['GET', '/werke', [WerkeController::class, 'liste']],
            ['GET', '/werke/neu', [WerkeController::class, 'neu']],
            ['POST', '/werke', [WerkeController::class, 'anlegen']],
            ['GET', '/werke/(\d+)', [WerkeController::class, 'bearbeiten']],
            ['POST', '/werke/(\d+)', [WerkeController::class, 'speichern']],
            ['POST', '/werke/(\d+)/loeschen', [WerkeController::class, 'loeschen']],
            ['POST', '/werke/kategorien', [WerkeController::class, 'kategorienZuordnen']],
            ['POST', '/kategorien/(\d+)', [WerkeController::class, 'kategorieUmbenennen']],
            ['POST', '/kategorien/(\d+)/loeschen', [WerkeController::class, 'kategorieLoeschen']],
            ['GET', '/api/werke', [WerkeController::class, 'apiSuche']],
            ['POST', '/api/werke', [WerkeController::class, 'apiAnlegen']],
            ['GET', '/api/werksuche', [WerkeController::class, 'apiGemaSuche']],

            ['GET', '/auftritte', [AuftritteController::class, 'liste']],
            ['GET', '/auftritte/neu', [AuftritteController::class, 'neu']],
            ['POST', '/auftritte', [AuftritteController::class, 'anlegen']],
            ['GET', '/auftritte/(\d+)', [AuftritteController::class, 'zeigen']],
            ['GET', '/auftritte/(\d+)/bearbeiten', [AuftritteController::class, 'bearbeiten']],
            ['POST', '/auftritte/(\d+)', [AuftritteController::class, 'speichern']],
            ['POST', '/auftritte/(\d+)/status', [AuftritteController::class, 'status']],
            ['POST', '/auftritte/(\d+)/loeschen', [AuftritteController::class, 'loeschen']],

            ['POST', '/api/auftritte/(\d+)/setlist', [SetlistController::class, 'hinzufuegen']],
            ['POST', '/api/auftritte/(\d+)/reihenfolge', [SetlistController::class, 'reihenfolge']],
            ['POST', '/api/auftritte/(\d+)/abgerechnet', [SetlistController::class, 'alleAbgerechnet']],
            ['POST', '/api/setlist/(\d+)', [SetlistController::class, 'aendern']],
            ['POST', '/api/setlist/(\d+)/loeschen', [SetlistController::class, 'entfernen']],

            ['GET', '/auftritte/(\d+)/export', [ExportController::class, 'seite']],
            ['GET', '/auftritte/(\d+)/export/gema', [ExportController::class, 'gema']],
            ['GET', '/auftritte/(\d+)/export/pdf', [ExportController::class, 'pdf']],
            ['GET', '/auftritte/(\d+)/export/csv', [ExportController::class, 'csv']],

            ['GET', '/abrechnungen', [AbrechnungenController::class, 'start']],
            ['POST', '/abrechnungen/import', [AbrechnungenController::class, 'importieren']],
            ['GET', '/abrechnungen/(\d{4})', [AbrechnungenController::class, 'jahr']],
            ['GET', '/abrechnungen/(\d{4})/(\d{1,2})', [AbrechnungenController::class, 'monat']],
            ['POST', '/abrechnungen/(\d{4})/(\d{1,2})/loeschen', [AbrechnungenController::class, 'monatLoeschen']],

            ['GET', '/einstellungen', [EinstellungenController::class, 'zeigen']],
            ['POST', '/einstellungen', [EinstellungenController::class, 'speichern']],
            ['GET', '/einstellungen/backup', [EinstellungenController::class, 'backupDb']],
            ['GET', '/einstellungen/export', [EinstellungenController::class, 'jsonExport']],
            ['POST', '/einstellungen/import', [EinstellungenController::class, 'jsonImport']],
            ['POST', '/einstellungen/werkverzeichnis', [EinstellungenController::class, 'werkverzeichnisImport']],
            ['POST', '/einstellungen/abmelden-alle', [EinstellungenController::class, 'alleAbmelden']],
        ];
    }

    public static function ausfuehren(): void
    {
        self::sicherheitsHeader();
        $_GET = utf8_bereinigen($_GET);
        $_POST = utf8_bereinigen($_POST);
        $methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $methode = $methode === 'HEAD' ? 'GET' : $methode;
        $pfad = self::pfad();
        $istApi = str_starts_with($pfad, '/api/');

        try {
            foreach (self::routen() as $route) {
                [$m, $muster, $handler] = $route;
                $oeffentlich = $route[3] ?? false;
                if ($m !== $methode || !preg_match('#^' . $muster . '$#', $pfad, $treffer)) {
                    continue;
                }
                if (!$oeffentlich && !Auth::angemeldet()) {
                    if ($istApi) {
                        json_antwort(['fehler' => 'Bitte neu anmelden.'], 401);
                    }
                    weiterleiten('/login');
                }
                if ($methode === 'POST') {
                    self::postPruefen($oeffentlich);
                }
                $handler(...array_map('intval', array_slice($treffer, 1)));
                return;
            }
            abbrechen(404, 'Seite nicht gefunden.');
        } catch (HttpFehler $e) {
            self::fehler($e->status, $e->getMessage() ?: 'Fehler', $istApi);
        } catch (Eingabefehler $e) {
            self::fehler(422, $e->getMessage(), $istApi);
        } catch (Throwable $e) {
            error_log('[Gigbook] ' . $e);
            self::fehler(500, 'Interner Fehler. Details stehen im Fehlerprotokoll des Servers.', $istApi);
        }
    }

    private static function pfad(): string
    {
        $pfad = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $basis = basis_pfad();
        if ($basis !== '' && str_starts_with($pfad, $basis)) {
            $pfad = substr($pfad, strlen($basis));
        }
        $pfad = '/' . trim($pfad, '/');
        return $pfad === '/index.php' ? '/' : $pfad;
    }

    /** Same-Origin-Prüfung und (für angemeldete Routen) CSRF-Token. */
    private static function postPruefen(bool $oeffentlich): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && $origin !== 'null') {
            $host = parse_url($origin, PHP_URL_HOST) . (($p = parse_url($origin, PHP_URL_PORT)) ? ":$p" : '');
            if (strcasecmp($host, (string)($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
                abbrechen(403, 'Anfrage von fremder Seite abgelehnt.');
            }
        }
        if ($oeffentlich) {
            return;
        }
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Auth::csrfPruefen($token)) {
            abbrechen(403, 'Sicherheitstoken ungültig. Bitte Seite neu laden und erneut versuchen.');
        }
    }

    private static function sicherheitsHeader(): void
    {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
            . "connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'; object-src 'none'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
    }

    private static function fehler(int $status, string $meldung, bool $json): void
    {
        if ($json || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            json_antwort(['fehler' => $meldung], $status);
        }
        http_response_code($status);
        View::seite('fehler', ['status' => $status, 'meldung' => $meldung], 'Fehler');
    }
}
