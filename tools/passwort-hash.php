<?php
declare(strict_types=1);

/**
 * Erzeugt einen Passwort-Hash für config.php bzw. APP_PASSWORD_HASH.
 * Aufruf: php tools/passwort-hash.php
 * (Das Passwort wird abgefragt und nicht gespeichert.)
 */

if (PHP_SAPI !== 'cli') {
    exit('Nur auf der Kommandozeile ausführen.');
}

fwrite(STDOUT, 'Neues Passwort (mind. 12 Zeichen): ');
$passwort = rtrim((string)fgets(STDIN), "\r\n");
fwrite(STDOUT, 'Passwort wiederholen: ');
$wiederholung = rtrim((string)fgets(STDIN), "\r\n");

if ($passwort !== $wiederholung) {
    fwrite(STDERR, "Die Passwörter stimmen nicht überein.\n");
    exit(1);
}
if (mb_strlen($passwort) < 12) {
    fwrite(STDERR, "Bitte mindestens 12 Zeichen verwenden.\n");
    exit(1);
}

$hash = password_hash($passwort, PASSWORD_DEFAULT);
fwrite(STDOUT, "\nHash:\n$hash\n\n");
fwrite(STDOUT, "config.php:           'password_hash' => '$hash',\n");
fwrite(STDOUT, "docker-compose (.env): APP_PASSWORD_HASH='$hash'\n");
