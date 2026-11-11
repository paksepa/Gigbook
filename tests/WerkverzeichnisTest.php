<?php
declare(strict_types=1);

/** Baut eine Datei im Aufbau des Werke-Exports aus dem GEMA-Portal (Inline-Texte wie im Original). */
function werkverzeichnis_datei(array $info, array $ids, array $beteiligte): string
{
    $blatt = static function (array $zeilen): string {
        $xml = '';
        foreach ($zeilen as $i => $zeile) {
            $xml .= '<row r="' . ($i + 1) . '">';
            foreach (array_values($zeile) as $j => $wert) {
                $ref = chr(65 + $j) . ($i + 1);
                $xml .= '<c r="' . $ref . '" t="str"><v>' . htmlspecialchars((string)$wert, ENT_XML1) . '</v></c>';
            }
            $xml .= '</row>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . $xml . '</sheetData></worksheet>';
    };
    $pfad = tempnam(sys_get_temp_dir(), 'wv') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($pfad, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
        . '<sheet name="Werkinformationen" sheetId="2" r:id="rId2"/><sheet name="Identifikatoren" sheetId="3" r:id="rId3"/>'
        . '<sheet name="Beteiligte" sheetId="4" r:id="rId4"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId2" Type="ws" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="ws" Target="worksheets/sheet3.xml"/>'
        . '<Relationship Id="rId4" Type="ws" Target="worksheets/sheet4.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet2.xml', $blatt([['Werknummer', 'Titel', 'Registrierstatus', 'Dauer', 'Besetzung'], ...$info]));
    $zip->addFromString('xl/worksheets/sheet3.xml', $blatt([['Identifikatoren'], ['Werknummer', 'Titel', 'ISWC'], ...$ids]));
    $zip->addFromString('xl/worksheets/sheet4.xml', $blatt([['Angaben', '', 'Beteiligte'], ['Werknummer', 'Titel', 'Name', 'IP-Name-Nr.', 'Rolle'], ...$beteiligte]));
    $zip->close();
    return $pfad;
}

function werkverzeichnis_beispiel(): string
{
    return werkverzeichnis_datei(
        [
            ['111-001', 'HELLBLAU', '1', '00:05:07', 'KONTRABASS (Anzahl Instrumente 1, Anzahl Spieler/-innen 1, Anzahl Stimmen 0) | '
                . 'VIOLINE (Anzahl Instrumente 2, Anzahl Spieler/-innen 2, Anzahl Stimmen 0) | VIOLA DA GAMBA (Anzahl Instrumente 1, Anzahl Spieler/-innen 0, Anzahl Stimmen 0)'],
            ['222-001', 'BEIGE NOISE', '1', '01:01:00', 'KLEINE BESETZUNG'],
            ['333-001', 'OHNE DAUER', '1', '', ''],
        ],
        [['111-001', 'HELLBLAU', 'T1234567890'], ['222-001', 'BEIGE NOISE', '-']],
        [
            ['111-001', 'HELLBLAU', 'MÜLLER, ANNA MARIA', '1', 'Komponist/-in'],
            ['222-001', 'BEIGE NOISE', 'MÜLLER, ANNA MARIA', '1', 'Komponist/-in'],
            ['222-001', 'BEIGE NOISE', 'DOE, JOHN', '2', 'Komponist/-in'],
            ['222-001', 'BEIGE NOISE', 'TEXTER, TOM', '3', 'Textdichter/-in'],
        ]
    );
}

test('Werkverzeichnis: GEMA-Export lesen', function () {
    $pfad = werkverzeichnis_beispiel();
    try {
        $w = Werkverzeichnis::lesen($pfad);
    } finally {
        @unlink($pfad);
    }
    gleich(3, count($w));
    gleich(['gema_werknummer' => '111-001', 'titel' => 'HELLBLAU', 'iswc' => 'T1234567890', 'dauer_sek' => 307,
        'besetzung' => 'Kontrabass, 2× Violine, Viola da Gamba', 'komponisten' => [['nachname' => 'Müller', 'vorname' => 'Anna Maria']]], $w[0]);
    gleich(3660, $w[1]['dauer_sek']);
    gleich('Kleine Besetzung', $w[1]['besetzung']);
    gleich(null, $w[2]['besetzung']);
    gleich(null, $w[1]['iswc'], 'ISWC "-" ist leer');
    gleich([['nachname' => 'Müller', 'vorname' => 'Anna Maria'], ['nachname' => 'Doe', 'vorname' => 'John']], $w[1]['komponisten'], 'Textdichter zählt nicht');
    gleich(null, $w[2]['dauer_sek']);
    gleich([], $w[2]['komponisten']);
});

test('Werkverzeichnis: Übernahme ergänzt, löscht nichts und ist wiederholbar', function () {
    $vorhanden = Werke::speichern(null, ['titel' => 'Hellblau', 'dauer_sek' => 300, 'notizen' => 'Ballade',
        'komponisten' => [['nachname' => 'Müller', 'vorname' => 'Anna']]]);
    $fremd = Werke::speichern(null, ['titel' => 'Take Five']);
    $pfad = werkverzeichnis_beispiel();
    try {
        $werke = Werkverzeichnis::lesen($pfad);
    } finally {
        @unlink($pfad);
    }

    gleich(['neu' => 2, 'aktualisiert' => 1, 'unveraendert' => 0], Werkverzeichnis::uebernehmen($werke));
    $w = Werke::finden($vorhanden);
    gleich('Hellblau', $w['titel'], 'eigener Titel bleibt');
    gleich('111-001', $w['gema_werknummer']);
    gleich('T1234567890', $w['iswc']);
    gleich('Kontrabass, 2× Violine, Viola da Gamba', $w['besetzung'], 'Besetzung kommt von der GEMA');
    gleich(300, (int)$w['dauer_sek'], 'eigene Dauer bleibt');
    gleich('Ballade', $w['notizen']);
    gleich([['nachname' => 'Müller', 'vorname' => 'Anna']], $w['komponisten'], 'eigene Komponisten bleiben');
    gleich(1, (int)$w['eigenes_werk']);
    wahr(Werke::finden($fremd) !== null, 'andere Werke bleiben');
    gleich(4, (int)Db::wert('SELECT COUNT(*) FROM werk'));
    $neu = Werke::liste('beige')[0];
    gleich(1, (int)$neu['eigenes_werk']);
    gleich(2, count($neu['komponisten']));
    gleich(['Hellblau'], array_column(Werke::liste('gamba'), 'titel'), 'Suche nach Instrument');

    gleich(['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 3], Werkverzeichnis::uebernehmen($werke), 'zweiter Lauf');
});

test('Werkverzeichnis: falsche Datei wird abgelehnt', function () {
    $pfad = tempnam(sys_get_temp_dir(), 'wv');
    file_put_contents($pfad, 'kein zip');
    try {
        wirft(Eingabefehler::class, fn() => Werkverzeichnis::lesen($pfad));
    } finally {
        @unlink($pfad);
    }
    wirft(Eingabefehler::class, fn() => Werkverzeichnis::lesen(APP_ROOT . '/vorlage/Excel-Upload-Template_Light_de.xlsx'));
});
