# Gigbook

Selbst gehostete Web-App für Musiker:innen (GEMA-Mitglieder): Werke und Auftritte verwalten,
Setlists im Format der offiziellen GEMA-Excel-Vorlage exportieren, Fristen im Blick behalten
und prüfen, ob alles korrekt abgerechnet wurde. Oberfläche auf Deutsch, für das Handy optimiert.

- **Stack:** PHP 8.2+ und SQLite, keine Fremdbibliotheken, kein Build-Schritt, kein Node.
- **Betrieb:** auf normalem Webspace (z. B. netcup Webhosting mit Plesk) oder optional per Docker.
- **Login:** genau ein Nutzer. Das Passwort steht als Hash in der Konfiguration, die Sitzung läuft über ein Cookie, Fehlversuche werden begrenzt.

---

## Funktionen

| Bereich | Was es kann |
|---|---|
| **Werke** | Titel, GEMA-Werknummer, ISWC, Komponist(en) mit Nachname/Vorname (beliebig viele), Dauer, „eigenes Werk“, Notizen. Suche nach Titel, Komponist oder Werknummer. |
| **Auftritte** | Datum, Uhrzeit, Ort, Stadt, Veranstalter (Name, Kontakt), Ensemble, Mitwirkende, Notizen. |
| **Setlist** | Werke per Suche antippen, unbekannte Titel direkt neu anlegen, Reihenfolge per Drag & Drop (auch auf dem Handy), Dauer und Mitwirkende pro Titel. Eine Setlist lässt sich als Vorlage für den nächsten Gig kopieren. |
| **Status** | Setlist: offen / von mir eingereicht (Datum) / vom Veranstalter eingereicht / nicht nötig. Abrechnung: nicht geprüft / gefunden / fehlt / reklamiert (Datum) / erledigt. Dazu der Ausschüttungstermin und ein Abgleich pro Titel. |
| **Dashboard** | Fällige und überfällige Setlists, ungeprüfte Abrechnungen, nahende Reklamationsfristen, kommende Auftritte, Statistik pro Jahr und pro Werk. |
| **Export** | GEMA-Excel (ausgefüllte Original-Vorlage zum Hochladen), PDF- und CSV-Setlist für Veranstalter. |
| **Kategorien** | Werke frei einsortieren, z. B. nach Bands. Ein Werk kann in mehreren Kategorien sein. Werkliste nach Kategorie filtern, viele Werke auf einmal über „Einsortieren …“ zuordnen, Kategorien unter *Einstellungen* umbenennen oder löschen. |
| **Abrechnungen** | GEMA-Abrechnungen (CSV/ZIP aus dem Portal) hochladen: Monatsansicht mit allen Posten, Jahresübersicht nach Monaten, Bereichen und Werken, druckbar. |
| **Werkverzeichnis** | Werke-Export aus dem GEMA-Portal (.xlsx) importieren: Werknummer, ISWC, Dauer, Besetzung, Komponisten. Ergänzt nur, löscht nichts. |
| **Datensicherung** | Datenbank als Download, JSON-Export und -Import (mit automatischer Sicherheitskopie). |

### Fristen

Beide Fristen lassen sich unter **Mehr → Einstellungen** ändern, falls die GEMA sie anpasst:

- **Setlist einreichen:** Auftrittsdatum + 42 Tage (6 Wochen)
- **Reklamation:** Ausschüttungstermin + 9 Kalendermonate. Gibt es den Tag im Zielmonat nicht, gilt das Monatsende (31.05. + 9 Monate = 28./29.02.).

Die Reklamationsfrist erscheint im Dashboard nur bei Auftritten mit dem Status „Nicht geprüft“ oder „Fehlt“, für die ein Ausschüttungstermin eingetragen ist.

---

## Installation auf netcup-Webspace (Plesk)

### 1. Voraussetzungen in Plesk prüfen

- **PHP-Version** 8.2 oder neuer (empfohlen 8.3), einzustellen unter *PHP-Einstellungen* der Domain
- **PHP-Erweiterungen:** `pdo_sqlite`, `zip`, `dom`, `mbstring`. Bei netcup sind sie normalerweise aktiv. Prüfen lässt sich das notfalls mit einer temporären `phpinfo()`-Datei, die du danach wieder löschst.
- **HTTPS** für die (Sub-)Domain, z. B. über Let's Encrypt in Plesk unter *SSL/TLS-Zertifikate*. Dazu die Weiterleitung von HTTP auf HTTPS aktivieren.

### 2. Dateien hochladen

Lade den **gesamten Projektordner** per SFTP oder über den Plesk-Dateimanager hoch, z. B. nach
`/gigbook/` im Webspace:

```
gigbook/
├── app/              ← Programmcode (nicht öffentlich)
├── data/             ← Datenbank (nicht öffentlich, muss beschreibbar sein)
├── public/           ← NUR dieser Ordner ist öffentlich
├── tools/
├── vorlage/          ← offizielle GEMA-Excel-Vorlage
├── config.php        ← legst du in Schritt 4 an
└── …
```

### 3. Dokumentstamm auf `public/` setzen (wichtig!)

In Plesk unter *Hosting-Einstellungen* (bzw. *Hosting & DNS → Hosting*) der (Sub-)Domain stellst du den
**Dokumentstamm** auf `gigbook/public`.

Nur so bleiben `config.php`, `data/` (die Datenbank) und der Programmcode von außen unerreichbar.
Zur Sicherheit sperren zusätzlich `.htaccess`-Dateien im Projektordner und in `data/` jeden Zugriff,
falls der Dokumentstamm einmal falsch steht. Dann zeigt die Seite einfach „Forbidden“.

### 4. Passwort einrichten

Auf deinem Rechner (PHP ist dort installiert):

```bash
php tools/passwort-hash.php
```

Das Skript fragt das Passwort ab (mindestens 12 Zeichen) und gibt einen Hash aus, der mit `$2y$…` beginnt.
Dann kopierst du `config.example.php` nach `config.php` und trägst den Hash ein, **in einfachen Anführungszeichen**:

```php
'password_hash' => '$2y$10$abc…',
```

`config.php` lädst du in den Projektordner hoch, neben `config.example.php` und **nicht** nach `public/`.
Das Passwort selbst wird nirgends gespeichert.

**Passwort ändern:** neuen Hash erzeugen, in `config.php` ersetzen und danach in der App unter
*Mehr → Auf allen Geräten abmelden* tippen.

### 5. Aufrufen

Öffne die Domain im Browser und melde dich an. Beim ersten Aufruf legt die App die Datenbank
`data/gigbook.sqlite` automatisch an. Kommt eine Fehlermeldung, ist `data/` meist nicht beschreibbar.
Prüfe dann im Plesk-Dateimanager die Rechte: Der Ordner muss dem Webspace-Benutzer gehören.

**Tipp fürs Handy:** Im Browser „Zum Startbildschirm hinzufügen“ wählen. Die Anmeldung bleibt
30 Tage gültig und verlängert sich bei Nutzung (einstellbar mit `session_days`).

### Updates einspielen

Neue Dateien hochladen und dabei **`data/` und `config.php` nicht überschreiben**. Änderungen am
Datenbankschema spielt die App beim nächsten Aufruf automatisch ein. Vorher sicherheitshalber
ein Backup herunterladen (siehe unten).

---

## Datensicherung

| Was | Wo | Wofür |
|---|---|---|
| **Datenbank herunterladen** | *Mehr → Datensicherung* | Vollständige, konsistente Kopie (`.sqlite`), auch während die App läuft. Zum Wiederherstellen die Datei als `data/gigbook.sqlite` hochladen. |
| **JSON-Export** | *Mehr → Datensicherung* | Lesbares Format mit allen Werken, Auftritten, Setlists und Einstellungen. Zum Archivieren oder Umziehen. |
| **JSON-Import** | *Mehr → Datensicherung* | **Ersetzt alle Daten.** Vorher legt die App automatisch eine Kopie in `data/sicherungen/` ab (die letzten 10 bleiben erhalten). Ist die Datei fehlerhaft, ändert sich nichts. |
| **Webspace-Backup** | netcup/Plesk | Sichert den ganzen Webspace inklusive `data/`. Ersetzt nicht den eigenen Download. |

Empfehlung: Lade die Datenbank regelmäßig herunter (z. B. einmal im Monat und vor Updates) und
bewahre sie außerhalb des Servers auf.

---

## GEMA-Export

Die App füllt die offizielle Vorlage `vorlage/Excel-Upload-Template_Light_de.xlsx` (**Version 1.2**)
aus dem GEMA-Portal. Sie schreibt die Werte direkt in die Original-Datei. Blattschutz, Auswahllisten,
Formate und das Blatt „Beispiele“ bleiben unverändert.

| Spalte | Vorlage | Inhalt |
|---|---|---|
| A | Werknummer / Werkfassungsnummer | GEMA-Werknummer (als Text) |
| B | Titel * | Titel. Wird ein Titel mehrmals gespielt, bekommt er mehrere Zeilen. |
| D | Anzahl Musiker/Sänger | Mitwirkende des Setlist-Eintrags |
| E | Spieldauer (MM:SS) | Dauer des Setlist-Eintrags, z. B. `07:30` |
| F / G | Interpret/Komponist Nachname * / Vorname | 1. Komponist |
| R/S, T/U, V/W, X/Y | weitere Personen | 2.–5. Komponist, laut Hinweis 6 der Vorlage („weitere Spalten R–Y“) |
| K | Art der Musikwiedergabe | immer `Live` |
| O | ISWC | ISWC, falls eingetragen |

Nicht befüllt werden die Spalten C, H/I, J, L, M, N und P/Q (Satzangabe, Texter, Verlag, Tonträger,
Großes Recht, Potpourri, Bearbeiter).

- Die **Veranstaltungsdaten** (Datum, Ort, Veranstalter) stehen nicht in der Excel-Datei. Du gibst
  sie im GEMA-Portal ein. Die Export-Seite zeigt sie zum Abschreiben an.
- Vor dem Download warnt die App bei fehlendem Komponisten (Pflichtfeld) und bei mehr als 5 Komponisten.
- **Neue Vorlage von der GEMA?** Lege die Datei in `vorlage/` ab. Hat sie eine andere Version als 1.2,
  verweigert der Export bewusst den Dienst, bis das Mapping geprüft und angepasst ist
  (`app/lib/GemaExport.php`).
- **Empfehlung:** Lade beim ersten Mal eine exportierte Datei testweise hoch und prüfe im Portal,
  ob alles richtig ankommt, vor allem die Werknummer (Spalte A) und die Dauer.

---

## GEMA-Werkverzeichnis importieren

Den Excel-Export deiner Werke aus dem GEMA-Online-Portal (`Werke_<Datum>_<Mitgliedsnr>.xlsx`)
übernimmt die App unter *Mehr → Einstellungen → GEMA-Werkverzeichnis*. Gelesen werden Werknummer,
Titel, ISWC, Dauer, Besetzung und die Komponist:innen. Textdichter:innen zählen nicht als Komponist:innen.

- **Neue Werke** werden angelegt und als „eigen“ markiert.
- **Vorhandene Werke** werden über die Werknummer erkannt, sonst über den gleichen Titel bei einem Werk
  ohne Nummer. Werknummer, ISWC und Besetzung kommen dann von der GEMA. Titel, Notizen sowie schon eingetragene
  Komponisten und Dauern bleiben unverändert.
- Es wird **nichts gelöscht**. Der Import lässt sich nach jedem neuen Export einfach wiederholen. Vorher legt
  die App automatisch eine Sicherheitskopie in `data/sicherungen/` an.

Auf der Kommandozeile geht es auch: `php tools/werkverzeichnis-import.php [datei.xlsx] [--probe]`.
Ohne Datei nimmt das Werkzeug die neueste `vorlage/Werke_*.xlsx`. Mit `--probe` zeigt es nur an, was es
gelesen hat, und speichert nichts. Die persönliche Exportdatei steht in `.gitignore` und kommt nicht ins Repo.

---

## GEMA-Abrechnungen

Unter **Abrechnung** lädst du die CSV-Dateien aus dem GEMA-Portal hoch, alle auf einmal oder als ZIP-Archiv.
Die App erkennt die Dateiarten am Spaltenkopf:

| Datei | Inhalt | Verwendung |
|---|---|---|
| `SUM_<Termin>_….csv` | Summe je Sparte | Kontrollbetrag; reicht allein für die Übersicht |
| `KMP_<Termin>_….csv` | Kompaktaufstellung je Werk | Einzelposten, wenn keine Detaildatei vorliegt |
| `U_…`, `M_…`, `R_…`, `MOD.S_…`, `MT.VR_…`, `A_…` usw. | Detailaufstellung je Sparte | Einzelposten mit Konzertort, Sender, Streamingdienst, Tonträger … |

- **Jahresübersicht:** Gesamtbetrag, Verlauf nach Monaten, Anteile nach Bereich (Live, Radio, Online, Ausland, Musikträger)
  und Erlöse je Werk. Mit *Drucken / PDF* entsteht eine druckfertige Jahresübersicht.
- **Monatsansicht:** alle Posten je Bereich und Sparte, mit Datum, Ort/Sender/Dienst und Anteil. Werke sind mit
  Gigbook verknüpft. Live-Posten zeigen den Auftritt mit gleichem Datum, falls vorhanden.
- Erneutes Hochladen ersetzt und zählt nichts doppelt. Weichen die Einzelposten von der Summendatei ab, erscheint ein Hinweis.
- Die Abrechnungsdateien sind persönlich und stehen in `.gitignore` (`vorlage/*.csv`, `vorlage/*.zip`).

---

## Optionale GEMA-Werksuche

Eine offizielle API gibt es nicht. Das Modul `app/lib/GemaWerksuche.php` nutzt den Endpunkt der
öffentlichen Repertoiresuche (wie [PyGEMASearch](https://github.com/DonMikone/PyGEMASearch)).

**Stand November 2026:** Dieser Endpunkt funktioniert nicht mehr, weil die GEMA ihr Portal umgebaut
hat. Das Modul ist deshalb **standardmäßig ausgeschaltet** (`'werksuche' => false`). Wer es
einschaltet, bekommt einen sauberen Hinweis („gerade nicht erreichbar“). Die App blockiert nie,
das Timeout liegt bei 8 Sekunden. Übertragen wird nur der gesuchte Titel.

---

## Konfiguration

Alle Werte stehen in `config.php` oder in Umgebungsvariablen. Umgebungsvariablen haben Vorrang.

| `config.php` | Umgebungsvariable | Standard | Bedeutung |
|---|---|---|---|
| `password_hash` | `APP_PASSWORD_HASH` | – | Passwort-Hash (Pflicht) |
| `session_days` | `APP_SESSION_DAYS` | 30 | Gültigkeit der Anmeldung in Tagen |
| `trusted_proxies` | `APP_TRUSTED_PROXIES` | leer | IPs eigener Reverse Proxys (kommagetrennt). Nur dann wird `X-Forwarded-For` vertraut. |
| `werksuche` | `APP_WERKSUCHE` | false | GEMA-Werksuche ein/aus |
| `data_dir` | `APP_DATA_DIR` | `data/` | Ort der Datenbank |
| `gema_vorlage` | `APP_GEMA_VORLAGE` | `vorlage/…xlsx` | Pfad zur GEMA-Vorlage |

## Sicherheit

- **Login:** Passwort-Hash mit `password_hash` (bcrypt). Sitzungs-Tokens sind zufällig (256 Bit),
  stehen in der Datenbank nur als SHA-256 und liegen im Cookie mit `HttpOnly`, `SameSite=Lax`
  und `Secure` (bei HTTPS).
- **Rate-Limit:** höchstens 5 Fehlversuche pro IP in 15 Minuten, dazu insgesamt höchstens 30 Fehlversuche
  in 15 Minuten. Die zweite Grenze bremst auch verteilte Angriffe. Danach ist der Login vorübergehend
  gesperrt, das gilt dann auch für dich.
- **CSRF:** Token bei allen Änderungen plus Prüfung des `Origin`-Headers.
- **Header:** strikte Content-Security-Policy (kein Inline-JavaScript), `X-Frame-Options: DENY`,
  `noindex`.

---

## Lokal entwickeln und testen

Voraussetzung: PHP 8.2+ mit `pdo_sqlite` und `zip`. Unter Windows ist `pdo_sqlite` teils nicht
aktiv, dann `-d extension=pdo_sqlite` anhängen.

```bash
# Server starten (http://localhost:8000)
php -d extension=pdo_sqlite -S localhost:8000 -t public public/index.php

# Tests: Fristen, Excel-Export, Setlists, Import/Export, Login-Rate-Limit, PDF/CSV
php -d extension=pdo_sqlite tests/run.php
```

Für den lokalen Server braucht es ebenfalls eine `config.php` mit Passwort-Hash, oder du setzt
`APP_PASSWORD_HASH` als Umgebungsvariable.

---

## Optional: Betrieb mit Docker (VPS)

Auf dem netcup-Webspace brauchst du das **nicht**. Für einen eigenen Server:

```bash
php tools/passwort-hash.php            # oder: docker compose run --rm gigbook php tools/passwort-hash.php
echo "APP_PASSWORD_HASH='\$2y\$10\$…'" > .env   # einfache Anführungszeichen!
docker compose up -d --build
```

Die App lauscht dann auf `127.0.0.1:8080`, die Daten liegen im Volume `gigbook-daten`.
Davor gehört ein Reverse Proxy mit HTTPS, z. B. Caddy:

```
gigbook.example.de {
    reverse_proxy 127.0.0.1:8080
}
```

Läuft der Proxy auf demselben Host, trägst du dessen IP aus Sicht des Containers in
`APP_TRUSTED_PROXIES` ein (z. B. `172.17.0.1`), damit das Rate-Limit die echten Client-IPs sieht.

**Backup im Docker-Betrieb:** über die App (siehe oben) oder direkt:
`docker compose exec gigbook php -r 'require "app/bootstrap.php"; Datensicherung::datenbankKopie("data/backup.sqlite");'`
und die Datei danach aus dem Volume kopieren.

---

## Projektstruktur

```
app/
  bootstrap.php, helpers.php
  lib/            Datenzugriff, Fristen, GEMA-Export, PDF, Login, Router …
  controllers/    eine Klasse pro Bereich
  views/          PHP-Templates
public/           index.php, .htaccess, assets/ (CSS, JS, Icon)
tests/            Test-Runner ohne Abhängigkeiten
tools/            passwort-hash.php, werkverzeichnis-import.php
vorlage/          GEMA-Excel-Vorlage (+ eigener Werke-Export, nicht im Repo)
data/             SQLite-Datenbank (wird angelegt)
```
