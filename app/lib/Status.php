<?php
declare(strict_types=1);

/** Statuswerte und ihre deutschen Bezeichnungen. */
final class Status
{
    public const SETLIST = [
        'offen' => 'Offen',
        'selbst_eingereicht' => 'Von mir eingereicht',
        'veranstalter_eingereicht' => 'Vom Veranstalter eingereicht',
        'nicht_noetig' => 'Nicht nötig',
    ];

    public const ABRECHNUNG = [
        'nicht_geprueft' => 'Nicht geprüft',
        'gefunden' => 'In Ausschüttung gefunden',
        'fehlt' => 'Fehlt',
        'reklamiert' => 'Reklamiert',
        'erledigt' => 'Erledigt',
    ];

    /** Abgleich pro Werk in der Setlist. */
    public const EINTRAG = [
        'ungeprueft' => 'Offen',
        'gefunden' => 'Gefunden',
        'fehlt' => 'Fehlt',
    ];
}
