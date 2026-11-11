<?php
declare(strict_types=1);

/** Beispieldateien im Aufbau der GEMA-Portal-Exporte (gekürzte Spalten, Reihenfolge wie im Original egal). */
function abrechnung_csv(array $kopf, array $zeilen): string
{
    $f = static fn($v) => is_string($v) && $v !== '' && !is_numeric(str_replace(',', '.', $v)) ? '"' . str_replace('"', '""', $v) . '"' : (string)$v;
    $text = "\xEF\xBB\xBF" . implode(';', $kopf) . "\r\n";
    foreach ($zeilen as $z) {
        $text .= implode(';', array_map($f, $z)) . "\r\n";
    }
    return $text;
}

function abrechnung_beispiel(): array
{
    $sumKopf = ['Ausschüttungstermin', 'Nutzungsbereich', 'Verteilungssparte', 'Spartencode', 'Spartenkürzel', 'Abrechnungsnummer',
        'Verteilart', 'Satzzahl in Kompaktaufstellung', 'Betrag gebucht'];
    $kmpKopf = ['Ausschüttungstermin', 'Abrechnungsnummer', 'Spartencode', 'Spartenkürzel', 'Berufsgruppe', 'Betrag gebucht', 'Anteil Prozent',
        'Werknummer', 'Fassungsnummer', 'Werkfassungstitel', 'Lizenznehmer', 'Verkaufsland', 'Nutzungsjahr', 'Quartal von', 'Quartal bis',
        'Sender', 'Anzahl Aufführungen', 'Anzahl Ausstrahlungen', 'Menge/Anzahl', 'Verteilart'];
    $uKopf = ['Ausschüttungstermin', 'Spartengruppe', 'Verteilungssparte', 'Verteilart', 'Betrag gebucht', 'Anteil Prozent',
        'Werkfassungsnummer', 'Werkfassungstitel', 'Anzahl Aufführungen', 'Nutzungsdatum von', 'Nutzungsdatum bis',
        'Veranstaltungsort Name', 'Ort', 'Interpret', 'Abrechnungsnummer'];
    return [
        'SUM_01.06.2026_1.csv' => abrechnung_csv($sumKopf, [
            ['01.06.2026', 'Live / Wiedergabe', 'Unterhaltungsmusik (U)', 'U1', 'U', '2026000001', 'Hauptverteilung', '2', '30,5000000000'],
            ['01.06.2026', 'Online', 'Music on Demand Streaming (MOD S)', '12', 'MOD S', '2026000002', 'Hauptverteilung', '1', '0,0040000000'],
        ]),
        'KMP_01.06.2026_1.csv' => abrechnung_csv($kmpKopf, [
            ['01.06.2026', '2026000001', 'U1', 'U', '1', '28,0000000000', '100,0000000000', '111', '001', 'HERBSTMONAT', '', '', '2025', '2', '2', '', '1', '', '', 'HV'],
            ['01.06.2026', '2026000001', 'U1', 'U', '1', '2,5000000000', '25,0000000000', '222', '001', 'SPINNING', '', '', '2025', '4', '4', '', '1', '', '', 'HV'],
            ['01.06.2026', '2026000002', '12', 'MOD S', '1', '0,0040000000', '100,0000000000', '111', '001', 'HERBSTMONAT', 'TIDAL MUSIC AS', 'NO', '2025', '2', '2', '', '', '', '3', 'HV'],
        ]),
        'U_01.06.2026_HV_1.csv' => abrechnung_csv($uKopf, [
            ['01.06.2026', 'Live / Wiedergabe', 'Unterhaltungsmusik (U)', 'Hauptverteilung', '28,0000000000', '100,0000000000', '111-001', 'HERBSTMONAT', '1',
                '22.05.2025', '22.05.2025', 'LVR Landesmuseum', 'Bonn', 'Quartett', '2026000001'],
            ['01.06.2026', 'Live / Wiedergabe', 'Unterhaltungsmusik (U)', 'Hauptverteilung', '2,5000000000', '25,0000000000', '222-001', 'SPINNING', '1',
                '29.12.2025', '29.12.2025', 'Heimathirsch', 'Köln', 'Band', '2026000001'],
        ]),
    ];
}

test('Abrechnungen: Dateiarten werden am Spaltenkopf erkannt', function () {
    $d = abrechnung_beispiel();
    gleich('summe', Abrechnungen::lesen($d['SUM_01.06.2026_1.csv'])['typ']);
    gleich('kompakt', Abrechnungen::lesen($d['KMP_01.06.2026_1.csv'])['typ']);
    gleich('detail', Abrechnungen::lesen($d['U_01.06.2026_HV_1.csv'])['typ']);
    gleich(null, Abrechnungen::lesen("a;b\n1;2")['typ']);
    gleich('HERBSTMONAT', Abrechnungen::lesen($d['U_01.06.2026_HV_1.csv'])['zeilen'][0]['Werkfassungstitel'], 'BOM entfernt, Anführungszeichen gelöst');
});

test('Abrechnungen: Detail vor Kompakt, Summe als Kontrolle, nichts doppelt', function () {
    $d = abrechnung_beispiel();
    $e = Abrechnungen::importieren($d + ['notiz.txt' => 'hallo']);
    gleich(['dateien' => 3, 'abrechnungen' => 2, 'posten' => 3, 'unbekannt' => ['notiz.txt']], $e);
    gleich('detail', Db::wert("SELECT DISTINCT quelle FROM abrechnung_posten WHERE abrechnungsnummer = '2026000001'"));
    gleich('kompakt', Db::wert("SELECT DISTINCT quelle FROM abrechnung_posten WHERE abrechnungsnummer = '2026000002'"));

    // erneut hochladen – auch nur die KMP – ändert nichts
    Abrechnungen::importieren($d);
    Abrechnungen::importieren(['KMP_01.06.2026_1.csv' => $d['KMP_01.06.2026_1.csv']]);
    gleich(3, (int)Db::wert('SELECT COUNT(*) FROM abrechnung_posten'));

    $j = Abrechnungen::jahr(2026);
    gleich(30.504, round($j['summe'], 6));
    gleich(['Juni'], array_column($j['monate'], 'name'));
    gleich(['Live / Wiedergabe', 'Online'], array_column($j['bereiche'], 'name'));
    gleich(['HERBSTMONAT', 'SPINNING'], array_column($j['werke'], 'titel'));
    gleich([], $j['warnungen']);
});

test('Abrechnungen: Monatsansicht mit Beschreibung, Werk- und Auftrittsverknüpfung', function () {
    $werk = Werke::speichern(null, ['titel' => 'Herbstmonat', 'gema_werknummer' => '111-001']);
    $auftritt = Auftritte::anlegen(['datum' => '2025-05-22', 'venue' => 'Landesmuseum']);
    Abrechnungen::importieren(abrechnung_beispiel());
    $m = Abrechnungen::monat(2026, 6);
    gleich(['2026-06-01'], $m['termine']);
    $live = $m['bereiche'][0]['sparten'][0]['posten'];
    gleich('HERBSTMONAT', $live[0]['titel'], 'höchster Betrag zuerst');
    gleich('LVR Landesmuseum, Bonn · Quartett', $live[0]['beschreibung']);
    gleich($werk, (int)$live[0]['werk_id']);
    gleich($auftritt, (int)$live[0]['auftritt']['id']);
    gleich(null, $live[1]['auftritt']);
    $online = $m['bereiche'][1]['sparten'][0]['posten'][0];
    gleich('TIDAL MUSIC AS · NO · Nutzung Q2/2025', $online['beschreibung']);
    gleich('111-001', $online['werknummer'], 'Werknummer aus Werknummer + Fassung');
    gleich('< 0,01 €', Abrechnungen::euro(0.004));
    gleich('1.234,50 €', Abrechnungen::euro(1234.5));
});

test('Abrechnungen: nur Summe, Abweichung, ZIP, Löschen, JSON-Sicherung', function () {
    $d = abrechnung_beispiel();
    Abrechnungen::importieren(['SUM.csv' => $d['SUM_01.06.2026_1.csv']]);
    $j = Abrechnungen::jahr(2026);
    gleich(30.504, round($j['summe'], 6), 'ohne Posten zählt die Summe');
    gleich(2, count($j['warnungen']));

    // Detaildatei mit anderem Betrag als die Summe → Hinweis
    Abrechnungen::importieren(['U.csv' => str_replace('28,0000000000', '27,0000000000', $d['U_01.06.2026_HV_1.csv'])]);
    wahr((bool)array_filter(Abrechnungen::jahr(2026)['warnungen'], fn($w) => str_contains($w, 'laut Summendatei')), 'Abweichung gemeldet');

    $zipPfad = tempnam(sys_get_temp_dir(), 'abr');
    $zip = new ZipArchive();
    $zip->open($zipPfad, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($d as $name => $inhalt) {
        $zip->addFromString('Abrechnung/' . $name, $inhalt);
    }
    $zip->close();
    $e = Abrechnungen::importieren(['export.zip' => (string)file_get_contents($zipPfad)]);
    @unlink($zipPfad);
    gleich(3, $e['dateien']);
    gleich([], Abrechnungen::jahr(2026)['warnungen'], 'nach vollständigem Upload stimmt alles');

    $export = json_decode(json_encode(Datensicherung::exportieren()), true);
    frische_db();
    Datensicherung::importieren($export);
    gleich(30.504, round(Abrechnungen::jahr(2026)['summe'], 6), 'JSON-Sicherung enthält Abrechnungen');

    gleich(2, Abrechnungen::monatLoeschen(2026, 6));
    gleich([], Abrechnungen::jahre());
    gleich(0, (int)Db::wert('SELECT COUNT(*) FROM abrechnung_posten'), 'Posten mitgelöscht');
    wirft(Eingabefehler::class, fn() => Abrechnungen::importieren(['x.csv' => "a;b\n1;2"]));
});
