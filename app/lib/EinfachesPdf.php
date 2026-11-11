<?php
declare(strict_types=1);

/**
 * Minimaler PDF-Erzeuger (A4, Helvetica, WinAnsi) – genug für eine Setlist-Tabelle,
 * ohne externe Bibliothek. Koordinaten in Punkt, y von oben gemessen.
 */
final class EinfachesPdf
{
    public const BREITE = 595.28;
    public const HOEHE = 841.89;

    /** Zeichenbreiten Helvetica (1/1000 em) für ASCII 32–126 */
    private const BREITEN = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var list<string> */
    private array $seiten = [];
    private string $aktuell = '';

    public function __construct(private readonly string $titel = '')
    {
        $this->neueSeite();
    }

    public function neueSeite(): void
    {
        if ($this->aktuell !== '') {
            $this->seiten[] = $this->aktuell;
        }
        $this->aktuell = "0.5 w\n";
    }

    public function text(float $x, float $y, string $text, float $groesse = 10, bool $fett = false, string $ausrichtung = 'links'): void
    {
        if ($ausrichtung === 'rechts') {
            $x -= self::textBreite($text, $groesse, $fett);
        }
        $this->aktuell .= sprintf(
            "BT /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n",
            $fett ? 'F2' : 'F1', $groesse, $x, self::HOEHE - $y, self::escape(self::winAnsi($text))
        );
    }

    public function linie(float $x1, float $y1, float $x2, float $y2, float $grau = 0.6): void
    {
        $this->aktuell .= sprintf(
            "%.2F G %.2F %.2F m %.2F %.2F l S 0 G\n",
            $grau, $x1, self::HOEHE - $y1, $x2, self::HOEHE - $y2
        );
    }

    /** Text auf Breite kürzen (mit …). */
    public static function kuerzen(string $text, float $maxBreite, float $groesse, bool $fett = false): string
    {
        if (self::textBreite($text, $groesse, $fett) <= $maxBreite) {
            return $text;
        }
        while ($text !== '' && self::textBreite($text . '…', $groesse, $fett) > $maxBreite) {
            $text = mb_substr($text, 0, -1);
        }
        return rtrim($text) . '…';
    }

    public static function textBreite(string $text, float $groesse, bool $fett = false): float
    {
        $summe = 0;
        foreach (mb_str_split($text) as $z) {
            $o = mb_ord($z);
            $summe += match (true) {
                $o >= 32 && $o <= 126 => self::BREITEN[$o - 32],
                $z === '…' => 1000,
                in_array($z, ['Ö', 'Ä', 'Ü'], true) => 722,
                default => 556,
            };
        }
        return $summe * $groesse / 1000 * ($fett ? 1.07 : 1.0);
    }

    public function ausgabe(): string
    {
        $seiten = [...$this->seiten, $this->aktuell];
        $objekte = [];
        $objekte[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kinder = [];
        $n = 5;
        foreach ($seiten as $inhalt) {
            $kinder[] = "$n 0 R";
            $objekte[$n] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::BREITE, self::HOEHE, $n + 1
            );
            $objekte[$n + 1] = '<< /Length ' . strlen($inhalt) . " >>\nstream\n" . $inhalt . "\nendstream";
            $n += 2;
        }
        $objekte[2] = '<< /Type /Pages /Kids [' . implode(' ', $kinder) . '] /Count ' . count($kinder) . ' >>';
        $objekte[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objekte[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objekte);
        $info = count($objekte) + 1;
        $objekte[$info] = '<< /Title (' . self::escape(self::winAnsi($this->titel)) . ') /Producer (' . APP_NAME . ') >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objekte as $nr => $obj) {
            $offsets[$nr] = strlen($pdf);
            $pdf .= "$nr 0 obj\n$obj\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objekte) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= 'trailer << /Size ' . (count($objekte) + 1) . " /Root 1 0 R /Info $info 0 R >>\nstartxref\n$xref\n%%EOF\n";
        return $pdf;
    }

    private static function winAnsi(string $text): string
    {
        $text = strtr($text, ['–' => '-', '„' => '"', '“' => '"']);
        return (string)mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    private static function escape(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }
}
