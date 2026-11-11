<?php
/*
 * Konfiguration – kopieren nach config.php und anpassen.
 * config.php liegt NICHT im öffentlichen Ordner public/ und ist damit nicht abrufbar.
 * Umgebungsvariablen (APP_PASSWORD_HASH usw.) haben Vorrang vor dieser Datei.
 */
return [
    // Passwort-Hash erzeugen mit:  php tools/passwort-hash.php
    // Einfache Anführungszeichen verwenden (der Hash enthält $-Zeichen)!
    'password_hash' => '',

    // Wie lange eine Anmeldung gültig bleibt (Tage, verlängert sich bei Nutzung)
    'session_days' => 30,

    // Nur nötig, wenn die App hinter einem eigenen Reverse Proxy läuft:
    // IP-Adressen des Proxys, dessen X-Forwarded-For/-Proto vertraut wird.
    'trusted_proxies' => [],

    // Optionale Suche in der GEMA-Repertoiredatenbank (inoffiziell, siehe README)
    'werksuche' => false,

    // Standardpfade – normalerweise nicht ändern
    // 'data_dir' => __DIR__ . '/data',
    // 'gema_vorlage' => __DIR__ . '/vorlage/Excel-Upload-Template_Light_de.xlsx',
];
