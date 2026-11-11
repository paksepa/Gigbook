<?php
declare(strict_types=1);

/**
 * Füllt die offizielle GEMA-Vorlage "Excel-Upload-Template_Light_de.xlsx" (Version 1.2).
 *
 * Es wird direkt das XML des Blatts "Setlist Template" bearbeitet, damit Blattschutz,
 * Auswahllisten, Formate und das Blatt "Beispiele" exakt erhalten bleiben.
 *
 * Mapping (Eingabezeilen 21–549, je Titel eine Zeile, Wiederholungen = eigene Zeile):
 *   A  Werknummer / Werkfassungsnummer   ← werk.gema_werknummer (Text)
 *   B  Titel *                           ← werk.titel
 *   D  Anzahl Musiker/Sänger             ← setlist_eintrag.mitwirkende (Zahl 1–99999)
 *   E  Spieldauer (MM:SS)                ← setlist_eintrag.dauer_sek (Text "MM:SS", Zelle ist Textformat)
 *   F/G Komponist Nachname * / Vorname   ← 1. Komponist
 *   R/S, T/U, V/W, X/Y                   ← 2.–5. Komponist (laut Hinweis 6 der Vorlage: "weitere Spalten R–Y")
 *   K  Art der Musikwiedergabe           ← immer "Live"
 *   O  ISWC                              ← werk.iswc
 * Nicht befüllt: C, H/I, J, L, M, N, P/Q.
 */
final class GemaExport
{
    public const VORLAGE_VERSION = 'Version: 1.2';
    public const BLATT = 'xl/worksheets/sheet1.xml';
    public const STRINGS = 'xl/sharedStrings.xml';
    public const ERSTE_ZEILE = 21;
    public const LETZTE_ZEILE = 549;
    public const KOMPONISTEN_SPALTEN = [['F', 'G'], ['R', 'S'], ['T', 'U'], ['V', 'W'], ['X', 'Y']];
    public const WIEDERGABE = 'Live';

    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const STIL_STANDARD = '3';   // ungesperrt, Standardformat – wie die Eingabezellen der Vorlage

    private DOMDocument $strings;
    /** @var array<string,int> */
    private array $stringIndex = [];
    private int $stringVerwendungen = 0;

    public function __construct(private readonly string $vorlage)
    {
    }

    public static function maxZeilen(): int
    {
        return self::LETZTE_ZEILE - self::ERSTE_ZEILE + 1;
    }

    /**
     * Zeilen aus einer Setlist (Auftritte::setlist) bilden.
     * @return list<array{werknummer:?string, titel:string, mitwirkende:?int, dauer_sek:?int, iswc:?string,
     *                    komponisten:list<array{nachname:string, vorname:?string}>}>
     */
    public static function zeilenAusSetlist(array $setlist): array
    {
        return array_map(static fn($e) => [
            'werknummer' => $e['gema_werknummer'] ?? null,
            'titel' => (string)$e['titel'],
            'mitwirkende' => $e['mitwirkende'] === null ? null : (int)$e['mitwirkende'],
            'dauer_sek' => $e['dauer_sek'] === null ? null : (int)$e['dauer_sek'],
            'iswc' => $e['iswc'] ?? null,
            'komponisten' => $e['komponisten'] ?? [],
        ], $setlist);
    }

    /**
     * Prüft die Zeilen auf Pflichtfelder und Grenzen der Vorlage.
     * @return array{fehler: list<string>, warnungen: list<string>}
     */
    public static function pruefen(array $zeilen): array
    {
        $fehler = [];
        $warnungen = [];
        if (!$zeilen) {
            $fehler[] = 'Die Setlist ist leer.';
        }
        if (count($zeilen) > self::maxZeilen()) {
            $fehler[] = 'Die Vorlage fasst höchstens ' . self::maxZeilen() . ' Titel.';
        }
        $maxK = count(self::KOMPONISTEN_SPALTEN);
        foreach ($zeilen as $i => $z) {
            $nr = $i + 1;
            $titel = "Titel $nr „{$z['titel']}“";
            if (!$z['komponisten']) {
                $warnungen[] = "$titel: kein Komponist eingetragen – Pflichtfeld bei der GEMA.";
            } elseif (count($z['komponisten']) > $maxK) {
                $warnungen[] = "$titel: " . count($z['komponisten']) . " Komponisten – die Vorlage hat nur Platz für $maxK. "
                    . 'Die übrigen bitte im GEMA-Portal ergänzen.';
            }
            if ($z['mitwirkende'] !== null && ($z['mitwirkende'] < 1 || $z['mitwirkende'] > 99999)) {
                $warnungen[] = "$titel: Anzahl Mitwirkende ungültig, wird leer gelassen.";
            }
        }
        return ['fehler' => $fehler, 'warnungen' => $warnungen];
    }

    /** Erzeugt die ausgefüllte Datei unter $ziel. */
    public function erzeugen(array $zeilen, string $ziel): void
    {
        if (count($zeilen) > self::maxZeilen()) {
            throw new Eingabefehler('Die Vorlage fasst höchstens ' . self::maxZeilen() . ' Titel.');
        }
        if (!is_file($this->vorlage)) {
            throw new RuntimeException('GEMA-Vorlage nicht gefunden: ' . $this->vorlage);
        }
        if (!copy($this->vorlage, $ziel)) {
            throw new RuntimeException('Vorlage konnte nicht kopiert werden.');
        }
        $zip = new ZipArchive();
        if ($zip->open($ziel) !== true) {
            throw new RuntimeException('Vorlage ist keine gültige xlsx-Datei.');
        }
        try {
            $blattXml = $zip->getFromName(self::BLATT);
            $stringsXml = $zip->getFromName(self::STRINGS);
            if ($blattXml === false || $stringsXml === false) {
                throw new RuntimeException('Die Vorlage hat einen unerwarteten Aufbau (Blatt oder Texttabelle fehlt).');
            }
            if (!str_contains($stringsXml, self::VORLAGE_VERSION)) {
                throw new RuntimeException('Unerwartete Version der GEMA-Vorlage – das Mapping muss geprüft werden.');
            }

            $blatt = self::dom($blattXml);
            $this->stringsLaden($stringsXml);
            $xp = new DOMXPath($blatt);
            $xp->registerNamespace('m', self::NS);
            $sheetData = $xp->query('/m:worksheet/m:sheetData')->item(0)
                ?? throw new RuntimeException('Vorlage: sheetData fehlt.');

            foreach (array_values($zeilen) as $i => $z) {
                $nr = self::ERSTE_ZEILE + $i;
                $zeile = $this->zeile($sheetData, $xp, $nr);
                $this->text($zeile, 'A', $nr, $z['werknummer'] ?? null);
                $this->text($zeile, 'B', $nr, $z['titel']);
                $m = $z['mitwirkende'] ?? null;
                if ($m !== null && $m >= 1 && $m <= 99999) {
                    $this->zahl($zeile, 'D', $nr, $m);
                }
                if (($z['dauer_sek'] ?? null) !== null) {
                    $this->text($zeile, 'E', $nr, Dauer::format($z['dauer_sek'], true));
                }
                foreach (array_slice($z['komponisten'] ?? [], 0, count(self::KOMPONISTEN_SPALTEN)) as $k => $person) {
                    [$spNach, $spVor] = self::KOMPONISTEN_SPALTEN[$k];
                    $this->text($zeile, $spNach, $nr, $person['nachname'] ?? null);
                    $this->text($zeile, $spVor, $nr, $person['vorname'] ?? null);
                }
                $this->text($zeile, 'K', $nr, self::WIEDERGABE);
                $this->text($zeile, 'O', $nr, $z['iswc'] ?? null);
            }

            $sst = $this->strings->documentElement;
            $sst->setAttribute('count', (string)((int)$sst->getAttribute('count') + $this->stringVerwendungen));
            $sst->setAttribute('uniqueCount', (string)$this->anzahlSi());

            $zip->addFromString(self::BLATT, $blatt->saveXML());
            $zip->addFromString(self::STRINGS, $this->strings->saveXML());
        } finally {
            $zip->close();
        }
    }

    private static function dom(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Vorlage: XML nicht lesbar.');
        }
        return $dom;
    }

    private function stringsLaden(string $xml): void
    {
        $this->strings = self::dom($xml);
        $this->stringIndex = [];
        $this->stringVerwendungen = 0;
        $i = 0;
        foreach ($this->strings->documentElement->childNodes as $si) {
            if (!$si instanceof DOMElement || $si->localName !== 'si') {
                continue;
            }
            $t = $si->firstChild;
            // Nur einfache Einträge (<si><t>…</t></si>) wiederverwenden, keine formatierten
            if ($t instanceof DOMElement && $t->localName === 't' && $si->childNodes->length === 1) {
                $this->stringIndex[$t->textContent] ??= $i;
            }
            $i++;
        }
    }

    private function anzahlSi(): int
    {
        $n = 0;
        foreach ($this->strings->documentElement->childNodes as $si) {
            if ($si instanceof DOMElement && $si->localName === 'si') {
                $n++;
            }
        }
        return $n;
    }

    private function stringId(string $text): int
    {
        $this->stringVerwendungen++;
        if (isset($this->stringIndex[$text])) {
            return $this->stringIndex[$text];
        }
        $si = $this->strings->createElementNS(self::NS, 'si');
        $t = $this->strings->createElementNS(self::NS, 't');
        $t->appendChild($this->strings->createTextNode($text));
        if ($text !== trim($text)) {
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        }
        $si->appendChild($t);
        $this->strings->documentElement->appendChild($si);
        return $this->stringIndex[$text] = $this->anzahlSi() - 1;
    }

    private function zeile(DOMElement $sheetData, DOMXPath $xp, int $nr): DOMElement
    {
        $vorhanden = $xp->query("m:row[@r='$nr']", $sheetData)->item(0);
        if ($vorhanden instanceof DOMElement) {
            return $vorhanden;
        }
        $neu = $sheetData->ownerDocument->createElementNS(self::NS, 'row');
        $neu->setAttribute('r', (string)$nr);
        $davor = null;
        foreach ($sheetData->childNodes as $r) {
            if ($r instanceof DOMElement && (int)$r->getAttribute('r') > $nr) {
                $davor = $r;
                break;
            }
        }
        $sheetData->insertBefore($neu, $davor);
        return $neu;
    }

    private function text(DOMElement $zeile, string $spalte, int $nr, ?string $wert): void
    {
        $wert = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$wert) ?? '');
        if ($wert === '') {
            return;
        }
        $c = $this->zelle($zeile, $spalte, $nr);
        $c->setAttribute('t', 's');
        $c->appendChild($c->ownerDocument->createElementNS(self::NS, 'v', (string)$this->stringId($wert)));
    }

    private function zahl(DOMElement $zeile, string $spalte, int $nr, int $wert): void
    {
        $c = $this->zelle($zeile, $spalte, $nr);
        $c->appendChild($c->ownerDocument->createElementNS(self::NS, 'v', (string)$wert));
    }

    /** Vorhandene Zelle leeren oder neue Zelle in Spaltenreihenfolge einfügen. Stil bleibt erhalten. */
    private function zelle(DOMElement $zeile, string $spalte, int $nr): DOMElement
    {
        $ref = $spalte . $nr;
        $ziel = self::spaltenIndex($spalte);
        $davor = null;
        foreach ($zeile->childNodes as $c) {
            if (!$c instanceof DOMElement || $c->localName !== 'c') {
                continue;
            }
            $r = $c->getAttribute('r');
            if ($r === $ref) {
                while ($c->firstChild) {
                    $c->removeChild($c->firstChild);
                }
                $c->removeAttribute('t');
                return $c;
            }
            if ($davor === null && self::spaltenIndex(preg_replace('/\d+/', '', $r)) > $ziel) {
                $davor = $c;
            }
        }
        $c = $zeile->ownerDocument->createElementNS(self::NS, 'c');
        $c->setAttribute('r', $ref);
        $c->setAttribute('s', self::STIL_STANDARD);
        $zeile->insertBefore($c, $davor);

        if (preg_match('/^(\d+):(\d+)$/', $zeile->getAttribute('spans'), $m)) {
            $zeile->setAttribute('spans', min((int)$m[1], $ziel) . ':' . max((int)$m[2], $ziel));
        }
        return $c;
    }

    public static function spaltenIndex(string $buchstaben): int
    {
        $n = 0;
        foreach (str_split(strtoupper($buchstaben)) as $b) {
            $n = $n * 26 + (ord($b) - 64);
        }
        return $n;
    }
}
