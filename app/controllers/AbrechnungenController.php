<?php
declare(strict_types=1);

final class AbrechnungenController
{
    public static function start(): void
    {
        $jahre = Abrechnungen::jahre();
        if (!$jahre) {
            View::seite('abrechnungen/leer', [], 'Abrechnungen', 'abrechnungen');
            return;
        }
        weiterleiten('/abrechnungen/' . $jahre[0]);
    }

    public static function jahr(int $jahr): void
    {
        $jahre = Abrechnungen::jahre();
        if (!in_array($jahr, $jahre, true)) {
            abbrechen(404, "Für $jahr liegen keine Abrechnungen vor.");
        }
        View::seite('abrechnungen/jahr', [
            'jahr' => $jahr,
            'jahre' => $jahre,
            'auswertung' => Abrechnungen::jahr($jahr),
        ], "Abrechnungen $jahr", 'abrechnungen');
    }

    public static function monat(int $jahr, int $monat): void
    {
        if ($monat < 1 || $monat > 12) {
            abbrechen(404, 'Unbekannter Monat.');
        }
        $daten = Abrechnungen::monat($jahr, $monat);
        if (!$daten['bereiche']) {
            abbrechen(404, 'Für diesen Monat liegen keine Abrechnungen vor.');
        }
        View::seite('abrechnungen/monat', [
            'jahr' => $jahr,
            'monat' => $monat,
            'monate' => Abrechnungen::monateMitDaten($jahr),
            'auswertung' => $daten,
        ], Abrechnungen::MONATE[$monat] . " $jahr", 'abrechnungen');
    }

    /** Upload einer oder mehrerer CSV-Dateien bzw. eines ZIP-Archivs aus dem GEMA-Portal. */
    public static function importieren(): void
    {
        $dateien = [];
        $hochgeladen = $_FILES['dateien'] ?? null;
        if (is_array($hochgeladen) && is_array($hochgeladen['name'] ?? null)) {
            foreach ($hochgeladen['name'] as $i => $name) {
                if (($hochgeladen['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $dateien[(string)$name] = (string)file_get_contents($hochgeladen['tmp_name'][$i]);
                }
            }
        }
        if (!$dateien) {
            Auth::flash('Bitte die CSV-Dateien (oder das ZIP) aus dem GEMA-Portal auswählen.');
            weiterleiten('/abrechnungen');
        }
        try {
            $e = Abrechnungen::importieren($dateien);
        } catch (Eingabefehler $ex) {
            Auth::flash('Nichts übernommen: ' . $ex->getMessage());
            weiterleiten('/abrechnungen');
        }
        $meldung = sprintf('%d Datei%s übernommen: %d Abrechnung%s mit %d Posten.', $e['dateien'], $e['dateien'] === 1 ? '' : 'en',
            $e['abrechnungen'], $e['abrechnungen'] === 1 ? '' : 'en', $e['posten']);
        if ($e['unbekannt']) {
            $meldung .= ' Nicht erkannt: ' . implode(', ', $e['unbekannt']) . '.';
        }
        $anzahlHochgeladen = count($hochgeladen['name'] ?? []);
        $maxDateien = (int)ini_get('max_file_uploads');
        if ($maxDateien > 0 && $anzahlHochgeladen >= $maxDateien) {
            $meldung .= " Der Server nimmt höchstens $maxDateien Dateien auf einmal an. Falls du mehr ausgewählt hattest, lade den Rest nach oder nutze das ZIP aus dem Portal.";
        }
        Auth::flash($meldung);
        weiterleiten('/abrechnungen');
    }

    public static function monatLoeschen(int $jahr, int $monat): void
    {
        Abrechnungen::monatLoeschen($jahr, $monat);
        Auth::flash('Abrechnungen für ' . (Abrechnungen::MONATE[$monat] ?? $monat) . " $jahr gelöscht.");
        weiterleiten('/abrechnungen');
    }
}
