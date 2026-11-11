<?php
declare(strict_types=1);

test('Kategorien: anlegen ohne Dubletten, umbenennen, löschen lässt Werke stehen', function () {
    $a = Kategorien::anlegen('  Trio   Elf ');
    gleich($a, Kategorien::anlegen('trio elf'), 'gleicher Name ohne Groß-/Kleinschreibung');
    gleich('Trio Elf', Kategorien::finden($a)['name']);
    $b = Kategorien::anlegen('Quartett');
    wirft(Eingabefehler::class, fn() => Kategorien::umbenennen($b, 'TRIO ELF'));
    wirft(Eingabefehler::class, fn() => Kategorien::anlegen('   '));
    Kategorien::umbenennen($b, 'Oktett');
    gleich(['Oktett', 'Trio Elf'], array_column(Kategorien::alle(), 'name'));

    $w = Werke::speichern(null, ['titel' => 'X', 'kategorien' => [$a, $b, 999]]);
    gleich(['Oktett', 'Trio Elf'], array_column(Werke::finden($w)['kategorien'], 'name'), 'unbekannte IDs ignoriert');
    Kategorien::loeschen($a);
    gleich(['Oktett'], array_column(Werke::finden($w)['kategorien'], 'name'));
});

test('Kategorien: Speichern ohne Kategorien-Angabe lässt Zuordnung unverändert', function () {
    $k = Kategorien::anlegen('Band');
    $w = Werke::speichern(null, ['titel' => 'A', 'kategorien' => [$k]]);
    Werke::speichern($w, ['titel' => 'A neu']);
    gleich([$k], array_column(Werke::finden($w)['kategorien'], 'id'));
    Werke::speichern($w, ['titel' => 'A neu', 'kategorien' => []]);
    gleich([], Werke::finden($w)['kategorien']);
});

test('Kategorien: Mehrfachauswahl und Filter in der Werkliste', function () {
    $band = Kategorien::anlegen('Trio');
    $w1 = Werke::speichern(null, ['titel' => 'Eins']);
    $w2 = Werke::speichern(null, ['titel' => 'Zwei']);
    Werke::speichern(null, ['titel' => 'Drei']);
    gleich(2, Kategorien::zuordnen([$w1, $w2, $w2], $band, true));
    gleich(0, Kategorien::zuordnen([$w1], $band, true), 'doppelt zuordnen ändert nichts');
    gleich(['Eins', 'Zwei'], array_column(Werke::liste('', false, 0, $band), 'titel'));
    gleich(['Drei'], array_column(Werke::liste('', false, 0, 0), 'titel'), 'ohne Kategorie');
    gleich(['Eins', 'Zwei'], array_column(Werke::liste('trio'), 'titel'), 'Suche findet Kategorienamen');
    gleich(1, Kategorien::zuordnen([$w1], $band, false));
    gleich(['Zwei'], array_column(Werke::liste('', false, 0, $band), 'titel'));
    gleich(1, Kategorien::alle()[0]['anzahl']);
    wirft(Eingabefehler::class, fn() => Kategorien::zuordnen([], $band, true));
    wirft(Eingabefehler::class, fn() => Kategorien::zuordnen([$w1], 999, true));
});

test('Kategorien: JSON-Export und -Import', function () {
    $k = Kategorien::anlegen('Oktett');
    $w = Werke::speichern(null, ['titel' => 'Suite', 'kategorien' => [$k]]);
    $export = json_decode(json_encode(Datensicherung::exportieren()), true);
    frische_db();
    Datensicherung::importieren($export);
    gleich(['Oktett'], array_column(Werke::finden($w)['kategorien'], 'name'));
    // Exporte ohne Kategorien (ältere Version) bleiben importierbar
    unset($export['kategorien']);
    foreach ($export['werke'] as &$x) { unset($x['kategorien']); }
    unset($x);
    frische_db();
    gleich(['werke' => 1, 'auftritte' => 0], Datensicherung::importieren($export));
    gleich([], Werke::finden($w)['kategorien']);
});

test('Kategorien: Werkliste nach Gruppen sortiert', function () {
    $trio = Kategorien::anlegen('Trio');
    $oktett = Kategorien::anlegen('Oktett');
    Werke::speichern(null, ['titel' => 'Beide', 'kategorien' => [$trio, $oktett]]);
    Werke::speichern(null, ['titel' => 'Nur Trio', 'kategorien' => [$trio]]);
    Werke::speichern(null, ['titel' => 'Lose']);
    Kategorien::anlegen('Leer');
    $g = Werke::gruppiert(Werke::liste(), Kategorien::alle());
    gleich(['Oktett', 'Trio', 'Ohne Kategorie'], array_column($g, 'name'), 'alphabetisch, leere entfallen, ohne zuletzt');
    gleich(['Beide'], array_column($g[0]['werke'], 'titel'));
    gleich(['Beide', 'Nur Trio'], array_column($g[1]['werke'], 'titel'), 'Werk in mehreren Gruppen');
    gleich(['Lose'], array_column($g[2]['werke'], 'titel'));
});
