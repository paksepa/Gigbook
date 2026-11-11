<?php
declare(strict_types=1);

/** Liest eine erzeugte xlsx und liefert Zellwerte des Blatts "Setlist Template". */
function xlsx_lesen(string $pfad): array
{
    $zip = new ZipArchive();
    if ($zip->open($pfad) !== true) {
        throw new AssertionError('xlsx nicht lesbar');
    }
    $dateien = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $dateien[$name] = $zip->getFromIndex($i);
    }
    $zip->close();

    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $sst = new DOMDocument();
    $sst->loadXML($dateien['xl/sharedStrings.xml']);
    $strings = [];
    foreach ($sst->getElementsByTagNameNS($ns, 'si') as $si) {
        $strings[] = $si->textContent;
    }
    $blatt = new DOMDocument();
    $blatt->loadXML($dateien['xl/worksheets/sheet1.xml']);
    $zellen = [];
    foreach ($blatt->getElementsByTagNameNS($ns, 'c') as $c) {
        $v = $c->getElementsByTagNameNS($ns, 'v')->item(0);
        if ($v === null) {
            continue;
        }
        $zellen[$c->getAttribute('r')] = [
            'wert' => $c->getAttribute('t') === 's' ? $strings[(int)$v->textContent] : $v->textContent,
            'typ' => $c->getAttribute('t'),
            'stil' => $c->getAttribute('s'),
        ];
    }
    return ['zellen' => $zellen, 'dateien' => $dateien, 'blatt' => $blatt, 'sst' => $sst, 'strings' => $strings];
}

function export_test(array $zeilen): array
{
    $ziel = tempnam(sys_get_temp_dir(), 'gtest') . '.xlsx';
    (new GemaExport(APP_ROOT . '/vorlage/Excel-Upload-Template_Light_de.xlsx'))->erzeugen($zeilen, $ziel);
    $ergebnis = xlsx_lesen($ziel);
    unlink($ziel);
    return $ergebnis;
}

function beispiel_zeilen(): array
{
    return [
        [
            'werknummer' => '1234567-001', 'titel' => 'Blue in Green', 'mitwirkende' => 4, 'dauer_sek' => 450,
            'iswc' => 'T-123.456.789-0',
            'komponisten' => [['nachname' => 'Davis', 'vorname' => 'Miles'], ['nachname' => 'Evans', 'vorname' => 'Bill']],
        ],
        [
            'werknummer' => null, 'titel' => 'Eigenes Stück mit Ümläut & <Zeichen>', 'mitwirkende' => 3, 'dauer_sek' => 4505,
            'iswc' => null,
            'komponisten' => [
                ['nachname' => 'Eins', 'vorname' => 'A'], ['nachname' => 'Zwei', 'vorname' => 'B'],
                ['nachname' => 'Drei', 'vorname' => 'C'], ['nachname' => 'Vier', 'vorname' => null],
                ['nachname' => 'Fünf', 'vorname' => 'E'], ['nachname' => 'Sechs', 'vorname' => 'F'],
            ],
        ],
        [
            'werknummer' => null, 'titel' => 'Blue in Green', 'mitwirkende' => null, 'dauer_sek' => null, 'iswc' => null,
            'komponisten' => [['nachname' => 'Davis', 'vorname' => 'Miles']],
        ],
    ];
}

$vorlagePfad = APP_ROOT . '/vorlage/Excel-Upload-Template_Light_de.xlsx';

test('Export: Pflichtfelder Titel (B) und Komponist (F/G)', function () {
    $z = export_test(beispiel_zeilen())['zellen'];
    gleich('Blue in Green', $z['B21']['wert']);
    gleich('Davis', $z['F21']['wert']);
    gleich('Miles', $z['G21']['wert']);
    gleich('Eigenes Stück mit Ümläut & <Zeichen>', $z['B22']['wert'], 'Sonderzeichen');
});

test('Export: Werknummer (A) als Text, ISWC (O)', function () {
    $z = export_test(beispiel_zeilen())['zellen'];
    gleich('1234567-001', $z['A21']['wert']);
    gleich('s', $z['A21']['typ'], 'Werknummer als Text');
    gleich('T-123.456.789-0', $z['O21']['wert']);
    wahr(!isset($z['A22']), 'leere Werknummer bleibt leer');
});

test('Export: Dauer (E) als Text MM:SS, auch über 60 Minuten', function () {
    $z = export_test(beispiel_zeilen())['zellen'];
    gleich('07:30', $z['E21']['wert']);
    gleich('s', $z['E21']['typ']);
    gleich('63', $z['E21']['stil'], 'Textformat der Vorlage bleibt');
    gleich('75:05', $z['E22']['wert']);
    wahr(!isset($z['E23']), 'fehlende Dauer bleibt leer');
});

test('Export: Mitwirkende (D) als Zahl', function () {
    $z = export_test(beispiel_zeilen())['zellen'];
    gleich('4', $z['D21']['wert']);
    gleich('', $z['D21']['typ'], 'numerisch');
    wahr(!isset($z['D23']), 'leer bleibt leer');
});

test('Export: weitere Komponisten in R/S, T/U, V/W, X/Y; ab dem 6. nicht', function () {
    $z = export_test(beispiel_zeilen())['zellen'];
    gleich('Evans', $z['R21']['wert']);
    gleich('Bill', $z['S21']['wert']);
    gleich(['Eins', 'Zwei', 'Drei', 'Vier', 'Fünf'], [$z['F22']['wert'], $z['R22']['wert'], $z['T22']['wert'], $z['V22']['wert'], $z['X22']['wert']]);
    gleich('E', $z['Y22']['wert']);
    wahr(!isset($z['W22']), 'Vorname leer bleibt leer');
    wahr(!in_array('Sechs', array_column($z, 'wert'), true), '6. Komponist nicht exportiert');
});

test('Export: Art der Wiedergabe (K) immer „Live“, ungenutzte Spalten leer', function () {
    $z = export_test(beispiel_zeilen())['zellen'];
    foreach ([21, 22, 23] as $r) {
        gleich('Live', $z["K$r"]['wert']);
        foreach (['C', 'H', 'I', 'J', 'L', 'M', 'N', 'P', 'Q'] as $sp) {
            wahr(!isset($z["$sp$r"]), "$sp$r muss leer sein");
        }
    }
    wahr(!isset($z['B24']), 'keine Zeile nach dem letzten Titel');
});

test('Export: Wiederholte Titel bekommen eigene Zeilen', function () {
    $z = export_test(beispiel_zeilen())['zellen'];
    gleich('Blue in Green', $z['B23']['wert']);
});

test('Export: Vorlage bleibt unverändert (Blattschutz, Auswahllisten, Beispiele, Kopf)', function () use ($vorlagePfad) {
    $neu = export_test(beispiel_zeilen());
    $original = xlsx_lesen($vorlagePfad);
    foreach ($original['dateien'] as $name => $inhalt) {
        if (in_array($name, [GemaExport::BLATT, GemaExport::STRINGS], true)) {
            continue;
        }
        wahr(($neu['dateien'][$name] ?? null) === $inhalt, "$name verändert");
    }
    gleich(array_keys($original['dateien']), array_keys($neu['dateien']), 'gleiche Dateien im Archiv');
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    foreach (['sheetProtection', 'dataValidation', 'mergeCell', 'col'] as $tag) {
        gleich(
            $original['blatt']->getElementsByTagNameNS($ns, $tag)->length,
            $neu['blatt']->getElementsByTagNameNS($ns, $tag)->length,
            $tag
        );
    }
    // Kopfbereich (Zeilen 1–20) identisch
    foreach ($original['zellen'] as $ref => $zelle) {
        if ((int)preg_replace('/\D/', '', $ref) <= 20) {
            gleich($zelle['wert'], $neu['zellen'][$ref]['wert'] ?? null, $ref);
        }
    }
    gleich('TITEL* 2)', $neu['zellen']['B19']['wert']);
});

test('Export: Texttabelle konsistent (uniqueCount, vorhandene Texte wiederverwendet)', function () use ($vorlagePfad) {
    $neu = export_test(beispiel_zeilen());
    $original = xlsx_lesen($vorlagePfad);
    $sst = $neu['sst']->documentElement;
    gleich(count($neu['strings']), (int)$sst->getAttribute('uniqueCount'));
    gleich($original['strings'], array_slice($neu['strings'], 0, count($original['strings'])), 'alte Einträge unverändert');
    gleich(1, count(array_keys($neu['strings'], 'Live', true)), '„Live“ aus der Vorlage wiederverwendet');
    gleich(1, count(array_keys($neu['strings'], 'Blue in Green', true)), 'Duplikate einmal gespeichert');
});

test('Export: maximale Zeilenzahl', function () {
    $zeile = ['werknummer' => null, 'titel' => 'X', 'mitwirkende' => 1, 'dauer_sek' => 60, 'iswc' => null,
              'komponisten' => [['nachname' => 'Y', 'vorname' => null]]];
    gleich(529, GemaExport::maxZeilen());
    $z = export_test(array_fill(0, 529, $zeile))['zellen'];
    gleich('X', $z['B549']['wert']);
    gleich('3', $z['A549']['stil'] ?? '3');
    wirft(Eingabefehler::class, fn() => export_test(array_fill(0, 530, $zeile)));
});

test('Export: Prüfung meldet fehlende Komponisten und zu viele Komponisten', function () {
    $p = GemaExport::pruefen([
        ['titel' => 'Ohne', 'komponisten' => [], 'mitwirkende' => 2],
        ['titel' => 'Viele', 'komponisten' => array_fill(0, 6, ['nachname' => 'X', 'vorname' => null]), 'mitwirkende' => 2],
    ]);
    gleich([], $p['fehler']);
    gleich(2, count($p['warnungen']));
    wahr(str_contains($p['warnungen'][0], 'kein Komponist'), 'Warnung fehlender Komponist');
    wahr(str_contains($p['warnungen'][1], 'nur Platz für 5'), 'Warnung zu viele');
    gleich(['Die Setlist ist leer.'], GemaExport::pruefen([])['fehler']);
});

test('Export: Ende-zu-Ende aus der Datenbank', function () {
    $w1 = Werke::speichern(null, ['titel' => 'Solar', 'gema_werknummer' => '555', 'dauer_sek' => 300,
        'komponisten' => [['nachname' => 'Davis', 'vorname' => 'Miles']]]);
    $w2 = Werke::speichern(null, ['titel' => 'Mein Stück', 'eigenes_werk' => true,
        'komponisten' => [['nachname' => 'Musterbass', 'vorname' => 'Max']]]);
    $a = Auftritte::anlegen(['datum' => '2026-11-08', 'anzahl_mitwirkende' => 3]);
    Auftritte::eintragHinzufuegen($a, $w2);
    Auftritte::eintragHinzufuegen($a, $w1);
    $z = export_test(GemaExport::zeilenAusSetlist(Auftritte::setlist($a)))['zellen'];
    gleich('Mein Stück', $z['B21']['wert']);
    gleich('Musterbass', $z['F21']['wert']);
    gleich('3', $z['D21']['wert'], 'Mitwirkende vom Auftritt übernommen');
    gleich('Solar', $z['B22']['wert']);
    gleich('05:00', $z['E22']['wert'], 'Dauer vom Werk übernommen');
    gleich('555', $z['A22']['wert']);
});

test('Export: falsche Vorlagen-Version wird erkannt', function () use ($vorlagePfad) {
    $kopie = tempnam(sys_get_temp_dir(), 'gv') . '.xlsx';
    copy($vorlagePfad, $kopie);
    $zip = new ZipArchive();
    $zip->open($kopie);
    $zip->addFromString(GemaExport::STRINGS, str_replace('Version: 1.2', 'Version: 2.0', $zip->getFromName(GemaExport::STRINGS)));
    $zip->close();
    wirft(RuntimeException::class, fn() => (new GemaExport($kopie))->erzeugen([], tempnam(sys_get_temp_dir(), 'gz')));
    unlink($kopie);
});
