<?php
declare(strict_types=1);

$d = static fn(string $s) => Fristen::datum($s);
$e = Einstellungen::STANDARD; // 42 Tage, 9 Monate, Vorlauf 14 / 30

test('Setlist-Frist: 6 Wochen nach dem Auftritt', function () {
    gleich('2026-12-13', Fristen::setlistFaelligAm('2026-11-01', 42)->format('Y-m-d'));
    gleich('2027-01-12', Fristen::setlistFaelligAm('2026-12-01', 42)->format('Y-m-d'), 'über Jahreswechsel');
    gleich('2026-04-09', Fristen::setlistFaelligAm('2026-02-26', 42)->format('Y-m-d'), 'über Februar');
});

test('Setlist-Frist: Sommerzeitwechsel verschiebt nichts', function () {
    gleich('2026-04-27', Fristen::setlistFaelligAm('2026-03-16', 42)->format('Y-m-d'));
    gleich(42, Fristen::tageBis(Fristen::datum('2026-04-27'), Fristen::datum('2026-03-16')));
});

test('Reklamationsfrist: 9 Kalendermonate', function () {
    gleich('2026-10-15', Fristen::reklamationBis('2026-01-15', 9)->format('Y-m-d'));
    gleich('2027-01-30', Fristen::reklamationBis('2026-04-30', 9)->format('Y-m-d'));
    gleich('2027-09-01', Fristen::reklamationBis('2026-12-01', 9)->format('Y-m-d'), 'über Jahreswechsel');
});

test('Reklamationsfrist: Monatsende wird gekürzt', function () {
    gleich('2027-02-28', Fristen::reklamationBis('2026-05-31', 9)->format('Y-m-d'));
    gleich('2028-02-29', Fristen::reklamationBis('2027-05-31', 9)->format('Y-m-d'), 'Schaltjahr');
    gleich('2027-04-30', Fristen::reklamationBis('2026-07-31', 9)->format('Y-m-d'));
});

test('plusMonate über mehrere Jahre', function () {
    gleich('2029-03-31', Fristen::plusMonate(Fristen::datum('2026-03-31'), 36)->format('Y-m-d'));
    gleich('2026-02-28', Fristen::plusMonate(Fristen::datum('2026-01-31'), 1)->format('Y-m-d'));
});

test('tageBis: Vorzeichen', function () use ($d) {
    gleich(0, Fristen::tageBis($d('2026-11-11'), $d('2026-11-11')));
    gleich(3, Fristen::tageBis($d('2026-11-14'), $d('2026-11-11')));
    gleich(-5, Fristen::tageBis($d('2026-11-06'), $d('2026-11-11')));
});

test('Setlist-Stufen: zukunft / offen / bald / überfällig', function () use ($d, $e) {
    $a = ['setlist_status' => 'offen'];
    gleich('zukunft', Fristen::setlistFrist($a + ['datum' => '2026-12-01'], $d('2026-11-11'), $e)['stufe']);
    gleich('offen', Fristen::setlistFrist($a + ['datum' => '2026-11-10'], $d('2026-11-11'), $e)['stufe']);
    // Auftritt 01.10. → fällig 12.11.; am 11.11. ein Tag übrig → bald
    $f = Fristen::setlistFrist($a + ['datum' => '2026-10-01'], $d('2026-11-11'), $e);
    gleich('bald', $f['stufe']);
    gleich(1, $f['tage']);
    // genau am Fälligkeitstag noch nicht überfällig
    gleich('bald', Fristen::setlistFrist($a + ['datum' => '2026-10-01'], $d('2026-11-12'), $e)['stufe']);
    gleich('ueberfaellig', Fristen::setlistFrist($a + ['datum' => '2026-10-01'], $d('2026-11-13'), $e)['stufe']);
});

test('Setlist-Frist entfällt bei eingereicht / nicht nötig', function () use ($d, $e) {
    foreach (['selbst_eingereicht', 'veranstalter_eingereicht', 'nicht_noetig'] as $status) {
        gleich(null, Fristen::setlistFrist(['datum' => '2026-01-01', 'setlist_status' => $status], $d('2026-11-11'), $e), $status);
    }
});

test('Reklamations-Stufen', function () use ($d, $e) {
    $a = ['abrechnung_status' => 'fehlt', 'ausschuettungstermin' => '2026-04-01']; // bis 2027-01-01
    gleich('2027-01-01', Fristen::reklamationsFrist($a, $d('2026-11-11'), $e)['bis']->format('Y-m-d'));
    gleich('laeuft', Fristen::reklamationsFrist($a, $d('2026-11-11'), $e)['stufe']);
    gleich('bald', Fristen::reklamationsFrist($a, $d('2026-12-10'), $e)['stufe']);
    gleich('bald', Fristen::reklamationsFrist($a, $d('2027-01-01'), $e)['stufe'], 'letzter Tag');
    gleich('abgelaufen', Fristen::reklamationsFrist($a, $d('2027-01-02'), $e)['stufe']);
    gleich('zukunft', Fristen::reklamationsFrist($a, $d('2026-03-01'), $e)['stufe']);
});

test('Reklamationsfrist nur bei „nicht geprüft“ und „fehlt“ und mit Termin', function () use ($d, $e) {
    $basis = ['ausschuettungstermin' => '2026-04-01'];
    foreach (['gefunden', 'reklamiert', 'erledigt'] as $s) {
        gleich(null, Fristen::reklamationsFrist($basis + ['abrechnung_status' => $s], $d('2026-12-20'), $e), $s);
    }
    wahr(Fristen::reklamationsFrist($basis + ['abrechnung_status' => 'nicht_geprueft'], $d('2026-12-20'), $e) !== null, 'nicht_geprueft');
    gleich(null, Fristen::reklamationsFrist(['abrechnung_status' => 'fehlt', 'ausschuettungstermin' => null], $d('2026-12-20'), $e));
});

test('Fristen kommen aus den Einstellungen (konfigurierbar)', function () use ($d) {
    Einstellungen::setzen(['frist_setlist_tage' => '56', 'frist_reklamation_monate' => '12']);
    $e = Einstellungen::alle();
    gleich(56, $e['frist_setlist_tage']);
    $f = Fristen::setlistFrist(['datum' => '2026-11-01', 'setlist_status' => 'offen'], $d('2026-11-11'), $e);
    gleich('2026-12-27', $f['faellig']->format('Y-m-d'));
    $r = Fristen::reklamationsFrist(['abrechnung_status' => 'fehlt', 'ausschuettungstermin' => '2026-04-01'], $d('2026-11-11'), $e);
    gleich('2027-04-01', $r['bis']->format('Y-m-d'));
});

test('Einstellungen werden validiert', function () {
    wirft(Eingabefehler::class, fn() => Einstellungen::setzen(['frist_setlist_tage' => '0']));
    wirft(Eingabefehler::class, fn() => Einstellungen::setzen(['frist_reklamation_monate' => 'neun']));
    gleich(42, Einstellungen::get('frist_setlist_tage'), 'unverändert');
});

test('Ungültiges Datum wird abgelehnt', function () {
    wirft(InvalidArgumentException::class, fn() => Fristen::datum('2026-02-30'));
    wirft(InvalidArgumentException::class, fn() => Fristen::datum('11.11.2026'));
});

test('Dashboard sortiert und filtert Fristen', function () use ($d) {
    $alt = Auftritte::anlegen(['datum' => '2026-09-01', 'venue' => 'A']);
    $neu = Auftritte::anlegen(['datum' => '2026-11-01', 'venue' => 'B']);
    Auftritte::anlegen(['datum' => '2026-12-24', 'venue' => 'Zukunft']);
    $erledigt = Auftritte::anlegen(['datum' => '2026-10-01', 'venue' => 'C']);
    Auftritte::statusSetzen($erledigt, ['setlist_status' => 'selbst_eingereicht', 'abrechnung_status' => 'fehlt', 'ausschuettungstermin' => '2026-03-01']);

    $daten = Dashboard::daten($d('2026-11-11'));
    gleich([$alt, $neu], array_map(static fn($a) => (int)$a['id'], $daten['setlists']));
    gleich('ueberfaellig', $daten['setlists'][0]['frist']['stufe']);
    gleich(2, count($daten['abrechnung']), 'nur vergangene, nicht geprüfte');
    gleich(1, count($daten['reklamation']), 'Reklamation bis 01.12.2026 ist bald');
    gleich(1, count($daten['kommende']));
});
