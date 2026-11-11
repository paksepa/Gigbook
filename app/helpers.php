<?php
declare(strict_types=1);

/** HTML-Ausgabe escapen. */
function e(mixed $wert): string
{
    return htmlspecialchars((string)($wert ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Pfad, unter dem die App läuft ('' wenn direkt im Document Root). */
function basis_pfad(): string
{
    static $basis = null;
    if ($basis === null) {
        $skript = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $basis = rtrim(str_replace('\\', '/', dirname($skript)), '/.');
    }
    return $basis;
}

function url(string $pfad = '/', array $query = []): string
{
    $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
    return basis_pfad() . $pfad . ($query ? '?' . http_build_query($query) : '');
}

function weiterleiten(string $pfad): never
{
    header('Location: ' . url($pfad), true, 303);
    exit;
}

function abbrechen(int $status, string $meldung = ''): never
{
    throw new HttpFehler($status, $meldung);
}

function json_antwort(array $daten, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Ungültiges UTF-8 in Eingaben ersetzen (rekursiv). */
function utf8_bereinigen(mixed $wert): mixed
{
    if (is_string($wert)) {
        return mb_scrub($wert, 'UTF-8');
    }
    if (is_array($wert)) {
        return array_map('utf8_bereinigen', $wert);
    }
    return $wert;
}

function json_eingabe(): array
{
    $daten = json_decode((string)file_get_contents('php://input'), true);
    return is_array($daten) ? $daten : [];
}

function ist_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (in_array($_SERVER['REMOTE_ADDR'] ?? '', Config::get('trusted_proxies'), true)) {
        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
    return false;
}

/** Getrimmter POST-Wert als String. */
function post(string $schluessel, string $standard = ''): string
{
    $wert = $_POST[$schluessel] ?? $standard;
    return is_string($wert) ? trim($wert) : $standard;
}

function csrf_feld(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
}

/** Liefert 'Y-m-d' oder null, wenn leer. Wirft bei ungültigem Datum. */
function datum_oder_null(string $wert, string $feldname): ?string
{
    if ($wert === '') {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $wert);
    if (!$d || $d->format('Y-m-d') !== $wert) {
        throw new Eingabefehler("$feldname: ungültiges Datum.");
    }
    return $wert;
}

function zahl_oder_null(string $wert, string $feldname, int $min = 1, int $max = 99999): ?int
{
    if ($wert === '') {
        return null;
    }
    if (!preg_match('/^\d+$/', $wert) || (int)$wert < $min || (int)$wert > $max) {
        throw new Eingabefehler("$feldname: bitte eine ganze Zahl zwischen $min und $max eingeben.");
    }
    return (int)$wert;
}

const WOCHENTAGE = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

function datum_de(?string $ymd, bool $mitWochentag = false): string
{
    if (!$ymd) {
        return '';
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
    if (!$d) {
        return $ymd;
    }
    return ($mitWochentag ? WOCHENTAGE[(int)$d->format('w')] . ', ' : '') . $d->format('d.m.Y');
}

function optionen(array $werte, ?string $gewaehlt): string
{
    $html = '';
    foreach ($werte as $wert => $label) {
        $sel = (string)$wert === (string)$gewaehlt ? ' selected' : '';
        $html .= '<option value="' . e($wert) . '"' . $sel . '>' . e($label) . '</option>';
    }
    return $html;
}

/** "in 5 Tagen", "heute", "seit 3 Tagen" */
function tage_text(int $tage): string
{
    return match (true) {
        $tage === 0 => 'heute',
        $tage === 1 => 'morgen',
        $tage === -1 => 'seit gestern',
        $tage > 1 => "in $tage Tagen",
        default => 'seit ' . abs($tage) . ' Tagen',
    };
}

/** Dateinamen-tauglicher Text. */
function slug(string $text): string
{
    $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
    $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}
