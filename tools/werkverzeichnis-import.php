<?php
declare(strict_types=1);

/**
 * Übernimmt den Werke-Export aus dem GEMA-Portal in die Datenbank (ergänzt nur, löscht nichts).
 * Aufruf: php tools/werkverzeichnis-import.php [datei.xlsx] [--probe]
 *   ohne Datei: neueste vorlage/Werke_*.xlsx
 *   --probe:    nur anzeigen, was gelesen wurde, nichts speichern
 */

if (PHP_SAPI !== 'cli') {
    exit('Nur auf der Kommandozeile ausführen.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$argumente = array_slice($argv, 1);
$probe = in_array('--probe', $argumente, true);
$datei = array_values(array_filter($argumente, static fn($a) => $a !== '--probe'))[0] ?? null;
if ($datei === null) {
    $kandidaten = glob(APP_ROOT . '/vorlage/Werke_*.xlsx') ?: [];
    usort($kandidaten, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    $datei = $kandidaten[0] ?? null;
}
if ($datei === null || !is_file($datei)) {
    fwrite(STDERR, "Keine Datei gefunden. Aufruf: php tools/werkverzeichnis-import.php Werke_….xlsx\n");
    exit(1);
}

try {
    $werke = Werkverzeichnis::lesen($datei);
    fwrite(STDOUT, sprintf("%s: %d Werke gelesen\n", basename($datei), count($werke)));
    if ($probe) {
        foreach ($werke as $w) {
            fwrite(STDOUT, sprintf("  %-14s %-50s %6s  %s\n", $w['gema_werknummer'], mb_strimwidth($w['titel'], 0, 50, '…'),
                Dauer::format($w['dauer_sek']), Werke::komponistenText($w['komponisten'])));
        }
        exit(0);
    }
    $kopie = Datensicherung::sicherheitskopie('vor-werkverzeichnis');
    $e = Werkverzeichnis::uebernehmen($werke);
    fwrite(STDOUT, sprintf("%d neu, %d ergänzt, %d unverändert.\nSicherheitskopie: %s\n",
        $e['neu'], $e['aktualisiert'], $e['unveraendert'], $kopie));
} catch (Eingabefehler $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
