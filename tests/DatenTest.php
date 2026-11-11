<?php
declare(strict_types=1);

test('Dauer: Eingabeformate', function () {
    gleich(450, Dauer::parsen('7:30'));
    gleich(450, Dauer::parsen('7.30'));
    gleich(450, Dauer::parsen('7,30'));
    gleich(450, Dauer::parsen('730'));
    gleich(420, Dauer::parsen('7'));
    gleich(4505, Dauer::parsen('75:05'));
    gleich(null, Dauer::parsen(''));
    wirft(Eingabefehler::class, fn() => Dauer::parsen('7:75'));
    wirft(Eingabefehler::class, fn() => Dauer::parsen('abc'));
    gleich('7:30', Dauer::format(450));
    gleich('07:30', Dauer::format(450, true));
    gleich('1:15 Std.', Dauer::gesamt(4505));
});

test('Werke: Komponisten in Reihenfolge, Nachname Pflicht', function () {
    $id = Werke::speichern(null, ['titel' => 'Take Five', 'komponisten' => [
        ['nachname' => 'Desmond', 'vorname' => 'Paul'], ['nachname' => '', 'vorname' => ''], ['nachname' => 'Brubeck', 'vorname' => ''],
    ]]);
    $w = Werke::finden($id);
    gleich([['nachname' => 'Desmond', 'vorname' => 'Paul'], ['nachname' => 'Brubeck', 'vorname' => null]], $w['komponisten']);
    gleich('Paul Desmond, Brubeck', Werke::komponistenText($w['komponisten']));
    wirft(Eingabefehler::class, fn() => Werke::speichern(null, ['titel' => 'X', 'komponisten' => [['nachname' => '', 'vorname' => 'Nur']]]));
    wirft(Eingabefehler::class, fn() => Werke::speichern(null, ['titel' => '  ']));
});

test('Werke: Suche findet Umlaute unabhängig von Groß-/Kleinschreibung', function () {
    Werke::speichern(null, ['titel' => 'Über den Wolken', 'komponisten' => [['nachname' => 'Mey', 'vorname' => 'Reinhard']]]);
    Werke::speichern(null, ['titel' => 'Autumn Leaves', 'gema_werknummer' => '987', 'komponisten' => [['nachname' => 'Kosma', 'vorname' => 'Joseph']]]);
    gleich(['Über den Wolken'], array_column(Werke::liste('über'), 'titel'));
    gleich(['Autumn Leaves'], array_column(Werke::liste('kosma'), 'titel'));
    gleich(['Autumn Leaves'], array_column(Werke::liste('987'), 'titel'));
});

test('Werke: in Setlist verwendetes Werk nicht löschbar', function () {
    $w = Werke::speichern(null, ['titel' => 'X']);
    $a = Auftritte::anlegen(['datum' => '2026-01-01']);
    Auftritte::eintragHinzufuegen($a, $w);
    wirft(Eingabefehler::class, fn() => Werke::loeschen($w));
});

test('Setlist: Hinzufügen, Sortieren, Entfernen', function () {
    $a = Auftritte::anlegen(['datum' => '2026-11-08', 'anzahl_mitwirkende' => 4]);
    $ids = [];
    foreach (['A', 'B', 'C'] as $t) {
        $ids[] = Auftritte::eintragHinzufuegen($a, Werke::speichern(null, ['titel' => $t, 'dauer_sek' => 300]));
    }
    Auftritte::reihenfolge($a, [$ids[2], $ids[0], $ids[1]]);
    gleich(['C', 'A', 'B'], array_column(Auftritte::setlist($a), 'titel'));
    Auftritte::eintragEntfernen($ids[0]);
    $s = Auftritte::setlist($a);
    gleich(['C', 'B'], array_column($s, 'titel'));
    gleich([1, 2], array_map('intval', array_column($s, 'position')));
    gleich(4, (int)$s[0]['mitwirkende']);
    wirft(Eingabefehler::class, fn() => Auftritte::reihenfolge($a, [$ids[2]]));
});

test('Setlist: Felder bearbeiten', function () {
    $a = Auftritte::anlegen(['datum' => '2026-11-08']);
    $e = Auftritte::eintragHinzufuegen($a, Werke::speichern(null, ['titel' => 'A']));
    gleich(615, (int)Auftritte::eintragAendern($e, 'dauer', '10:15')['dauer_sek']);
    gleich(5, (int)Auftritte::eintragAendern($e, 'mitwirkende', '5')['mitwirkende']);
    gleich('gefunden', Auftritte::eintragAendern($e, 'abgerechnet', 'gefunden')['abgerechnet']);
    wirft(Eingabefehler::class, fn() => Auftritte::eintragAendern($e, 'abgerechnet', 'quatsch'));
    wirft(Eingabefehler::class, fn() => Auftritte::eintragAendern($e, 'werk_id', '1'));
});

test('Setlist als Vorlage kopieren, Mitwirkende werden angepasst', function () {
    $alt = Auftritte::anlegen(['datum' => '2026-10-01', 'anzahl_mitwirkende' => 3]);
    $e1 = Auftritte::eintragHinzufuegen($alt, Werke::speichern(null, ['titel' => 'A', 'dauer_sek' => 200]));
    $e2 = Auftritte::eintragHinzufuegen($alt, Werke::speichern(null, ['titel' => 'B']));
    Auftritte::eintragAendern($e2, 'mitwirkende', '1'); // Solo-Stück
    Auftritte::eintragAendern($e1, 'abgerechnet', 'gefunden');

    $neu = Auftritte::anlegen(['datum' => '2026-11-20', 'anzahl_mitwirkende' => 5], $alt);
    $s = Auftritte::setlist($neu);
    gleich(['A', 'B'], array_column($s, 'titel'));
    gleich(5, (int)$s[0]['mitwirkende'], 'Ensemble-Anzahl übernommen');
    gleich(1, (int)$s[1]['mitwirkende'], 'individuelle Anzahl bleibt');
    gleich(200, (int)$s[0]['dauer_sek']);
    gleich('ungeprueft', $s[0]['abgerechnet'], 'Abrechnungsstatus nicht kopiert');
    gleich('offen', Auftritte::finden($neu)['setlist_status']);
});

test('Auftritt: Mitwirkende ändern zieht nicht individuell geänderte Einträge mit', function () {
    $a = Auftritte::anlegen(['datum' => '2026-10-01', 'anzahl_mitwirkende' => 3]);
    $e1 = Auftritte::eintragHinzufuegen($a, Werke::speichern(null, ['titel' => 'A']));
    $e2 = Auftritte::eintragHinzufuegen($a, Werke::speichern(null, ['titel' => 'B']));
    Auftritte::eintragAendern($e2, 'mitwirkende', '1');
    Auftritte::aktualisieren($a, Auftritte::stammdatenAusEingabe(['datum' => '2026-10-01', 'anzahl_mitwirkende' => '4']));
    gleich(4, (int)Auftritte::eintrag($e1)['mitwirkende']);
    gleich(1, (int)Auftritte::eintrag($e2)['mitwirkende']);
});

test('Auftritt: Status setzt Datum automatisch', function () {
    $a = Auftritte::anlegen(['datum' => '2026-10-01']);
    Auftritte::statusSetzen($a, ['setlist_status' => 'selbst_eingereicht', 'abrechnung_status' => 'reklamiert']);
    $x = Auftritte::finden($a);
    gleich(Fristen::heute()->format('Y-m-d'), $x['setlist_eingereicht_am']);
    gleich(Fristen::heute()->format('Y-m-d'), $x['reklamiert_am']);
    wirft(Eingabefehler::class, fn() => Auftritte::statusSetzen($a, ['setlist_status' => 'x', 'abrechnung_status' => 'fehlt']));
    wirft(Eingabefehler::class, fn() => Auftritte::stammdatenAusEingabe(['datum' => '']));
    wirft(Eingabefehler::class, fn() => Auftritte::stammdatenAusEingabe(['datum' => '2026-10-01', 'uhrzeit' => '25:00']));
});

test('JSON: Export und Import ergeben denselben Bestand', function () {
    $w = Werke::speichern(null, ['titel' => 'Solar', 'gema_werknummer' => '0012', 'iswc' => 'T-1', 'besetzung' => 'Trompete, Klavier', 'eigenes_werk' => true,
        'komponisten' => [['nachname' => 'Davis', 'vorname' => 'Miles']]]);
    $a = Auftritte::anlegen(['datum' => '2026-11-08', 'venue' => 'Jazzkeller', 'anzahl_mitwirkende' => 3]);
    $e = Auftritte::eintragHinzufuegen($a, $w);
    Auftritte::eintragAendern($e, 'abgerechnet', 'fehlt');
    Auftritte::statusSetzen($a, ['setlist_status' => 'selbst_eingereicht', 'setlist_eingereicht_am' => '2026-11-09',
        'abrechnung_status' => 'fehlt', 'ausschuettungstermin' => '2027-04-01']);
    Einstellungen::setzen(['frist_setlist_tage' => '50']);

    $export = json_decode(json_encode(Datensicherung::exportieren()), true);
    frische_db();
    gleich(['werke' => 1, 'auftritte' => 1], Datensicherung::importieren($export));
    $wieder = json_decode(json_encode(Datensicherung::exportieren()), true);
    unset($export['exportiert'], $wieder['exportiert']);
    gleich($export, $wieder);
    gleich('0012', Werke::finden($w)['gema_werknummer'], 'führende Nullen');
    gleich('Trompete, Klavier', Werke::finden($w)['besetzung']);
    gleich(50, Einstellungen::get('frist_setlist_tage'));
});

test('JSON: fehlerhafter Import ändert nichts', function () {
    Werke::speichern(null, ['titel' => 'Bleibt']);
    wirft(Eingabefehler::class, fn() => Datensicherung::importieren(['format' => 'anders']));
    wirft(Eingabefehler::class, fn() => Datensicherung::importieren([
        'format' => 'gigbook', 'version' => 1,
        'werke' => [['id' => 1, 'titel' => 'Neu']],
        'auftritte' => [['id' => 1, 'datum' => 'kaputt']],
    ]));
    gleich(['Bleibt'], array_column(Werke::liste(), 'titel'));
});

test('Login: Rate-Limit pro IP und richtiges Passwort', function () {
    Config::laden(['password_hash' => password_hash('geheim', PASSWORD_DEFAULT)]);
    $t = 1_800_000_000;
    for ($i = 0; $i < Auth::MAX_PRO_IP; $i++) {
        gleich('Passwort falsch.', Auth::anmelden('falsch', '1.2.3.4', $t + $i));
    }
    wahr(str_starts_with((string)Auth::anmelden('geheim', '1.2.3.4', $t + 10), 'Zu viele'), 'gesperrt trotz richtigem Passwort');
    gleich(null, Auth::anmelden('geheim', '5.6.7.8', $t + 10), 'andere IP nicht betroffen');
    gleich(null, Auth::anmelden('geheim', '1.2.3.4', $t + Auth::FENSTER_SEK + 5), 'nach Ablauf wieder möglich');
    Config::laden();
});

test('Login: globales Rate-Limit', function () {
    Config::laden(['password_hash' => password_hash('geheim', PASSWORD_DEFAULT)]);
    $t = 1_800_000_000;
    for ($i = 0; $i < Auth::MAX_GESAMT; $i++) {
        Auth::anmelden('falsch', "10.0.0.$i", $t);
    }
    wahr(str_starts_with((string)Auth::anmelden('falsch', '10.9.9.9', $t + 1), 'Zu viele'), 'global gesperrt');
    Config::laden();
});

test('Login: ohne konfigurierten Hash kein Zugang', function () {
    Config::laden(['password_hash' => '']);
    wahr(!Auth::passwortKonfiguriert(), 'nicht konfiguriert');
    wahr(Auth::anmelden('', '1.1.1.1') !== null, 'Login abgelehnt');
    Config::laden(['password_hash' => 'kein-hash']);
    wahr(!Auth::passwortKonfiguriert(), 'ungültiger Hash');
    Config::laden();
});

test('PDF und CSV für Veranstalter', function () {
    $w = Werke::speichern(null, ['titel' => 'Ätherische Ballade (sehr lang) ' . str_repeat('x', 80), 'dauer_sek' => 300,
        'komponisten' => [['nachname' => 'Müller', 'vorname' => 'Jörg']]]);
    $a = Auftritte::anlegen(['datum' => '2026-11-08', 'venue' => 'Jazzkeller', 'stadt' => 'Köln', 'ensemble' => 'Trio']);
    for ($i = 0; $i < 70; $i++) {
        Auftritte::eintragHinzufuegen($a, $w);
    }
    $pdf = SetlistDokument::pdf(Auftritte::finden($a), Auftritte::setlist($a));
    wahr(str_starts_with($pdf, '%PDF-1.4') && str_ends_with($pdf, "%%EOF\n"), 'PDF-Rahmen');
    wahr(substr_count($pdf, '/Type /Page ') >= 2, 'Seitenumbruch bei langer Setlist');
    wahr(str_contains($pdf, "J\xF6rg"), 'Umlaute in WinAnsi');
    preg_match('/startxref\n(\d+)/', $pdf, $m);
    gleich('xref', substr($pdf, (int)$m[1], 4), 'xref-Offset stimmt');

    $csv = SetlistDokument::csv(Auftritte::finden($a), Auftritte::setlist($a));
    wahr(str_starts_with($csv, "\xEF\xBB\xBFNr.;Titel;"), 'CSV-Kopf mit BOM');
    gleich(71, substr_count($csv, "\n"));
    gleich('Setlist_2026-11-08_Jazzkeller.pdf', SetlistDokument::dateiname(Auftritte::finden($a), 'Setlist', 'pdf'));
});

test('GEMA-Werksuche: Antwort wird robust ausgewertet', function () {
    $t = GemaWerksuche::auswerten([
        ['titel' => 'Take Five', 'werknummer' => '123', 'werkfassungsnummer' => '123-001', 'urheber' => [
            ['nachname' => 'Desmond', 'vorname' => 'Paul', 'rolle' => 'K'],
            ['name' => 'Irgendwer', 'rolle' => 'V'],
        ]],
        ['kaputt' => true],
        'auch kaputt',
    ]);
    gleich(1, count($t));
    gleich('123-001', $t[0]['werknummer']);
    gleich([['nachname' => 'Desmond', 'vorname' => 'Paul']], $t[0]['komponisten']);
    gleich([], GemaWerksuche::auswerten([['titel' => 'X', 'urheber' => [['nachname' => 'A', 'rolle' => 'K']]]], 'Brubeck'), 'Komponistenfilter');
});

test('Auftritte: Veranstalterliste mit zuletzt verwendetem Kontakt', function () {
    Auftritte::anlegen(['datum' => '2026-01-10', 'veranstalter_name' => 'Jazzclub Köln', 'veranstalter_kontakt' => 'alt@example.de']);
    Auftritte::anlegen(['datum' => '2026-03-05', 'veranstalter_name' => 'jazzclub köln ', 'veranstalter_kontakt' => 'neu@example.de']);
    Auftritte::anlegen(['datum' => '2026-04-01', 'veranstalter_name' => 'Altes Pfandhaus']);
    Auftritte::anlegen(['datum' => '2026-02-01', 'veranstalter_name' => 'Altes Pfandhaus', 'veranstalter_kontakt' => '0221 123']);
    Auftritte::anlegen(['datum' => '2026-05-01']);
    gleich([
        ['name' => 'Altes Pfandhaus', 'kontakt' => '0221 123'],
        ['name' => 'jazzclub köln', 'kontakt' => 'neu@example.de'],
    ], Auftritte::veranstalter());
});
