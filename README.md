# CineRent Shop

WordPress-Plugin für die Equipment-Vermietung auf cinerent.de, gebaut für **Elementor Pro** (Theme Builder, Loop Grid, Dynamic Tags).

- Katalog aus einer Datenquelle (jetzt `data/katalog.json`, später die Storybase-Shop-API), gespiegelt als Custom Post Type `equipment`.
- Dynamic Tags und eigene Widgets für alles, was Elementor nicht kann: Zeitraum, Verfügbarkeit, Kalender, Anfrageliste, Anfrageformular.
- Keine externen Ressourcen, keine WordPress-Konten für Mietkunden, Storybase-Aufrufe nur serverseitig.

Version 0.1.0 · Textdomain `cinerent-shop` · PHP ab 8.0 · WordPress ab 6.4 · Elementor Pro 3.x

---

## Inhalt

1. [Installation](#installation)
2. [Einstellungen](#einstellungen)
3. [Katalog-Sync](#katalog-sync)
4. [Datenmodell `katalog.json`](#datenmodell-katalogjson)
5. [Elementor einrichten](#elementor-einrichten)
6. [Theme-Builder-Vorlagen aufbauen](#theme-builder-vorlagen-aufbauen)
7. [Dynamic Tags](#dynamic-tags)
8. [Widgets](#widgets)
9. [Loop-Grid-Filter `cinerent_frei`](#loop-grid-filter-cinerent_frei)
10. [Anfrageformular und Spamschutz](#anfrageformular-und-spamschutz)
11. [Datenschutz, Cookies, Caching](#datenschutz-cookies-caching)
12. [Wechsel auf die Storybase-API](#wechsel-auf-die-storybase-api)
13. [Entwicklung](#entwicklung)

---

## Installation

1. ZIP aus `dist/` nehmen (`cinerent-shop-0.1.0.zip`).
2. WordPress → Plugins → Installieren → Plugin hochladen → ZIP auswählen → Aktivieren.
3. Equipment → Einstellungen öffnen, Empfängeradresse für Anfragen prüfen und **Jetzt synchronisieren** klicken.
4. Einstellungen → Permalinks einmal speichern, falls `/equipment/…` nicht sofort erreichbar ist.

Elementor Pro wird für Dynamic Tags, Widgets und Loop Grid gebraucht. Der Katalog-Sync läuft auch ohne.

Empfehlung: Zuerst auf einer Staging-Kopie installieren.

## Einstellungen

**Equipment → Einstellungen**

| Einstellung | Zweck |
|---|---|
| Aktive Quelle | `Lokale JSON-Datei` oder `Storybase-API (in Vorbereitung)`. Seiten und Vorlagen bleiben beim Wechsel unverändert. |
| Storybase-URL | Basis-URL der Supabase Edge Functions, z. B. `https://<projekt>.supabase.co/functions/v1` |
| Shop-Schlüssel | Wird nur serverseitig genutzt und nie ausgegeben. Besser in `wp-config.php`: `define( 'CINERENT_STORYBASE_SHOP_KEY', '…' );` |
| Empfänger für Anfragen | E-Mail-Adresse, an die Anfragen gehen |
| Absendername | Name im Absender der Mails |
| Mietbedingungen (URL) | Wird im Formular verlinkt |
| Datenschutzerklärung (URL) | Leer = WordPress-Datenschutzseite |
| Anfragen je IP und Stunde | Rate-Limit (Standard 5) |
| ALTCHA-Komplexität | Obergrenze für den Proof-of-Work (Standard 100 000, ca. 0,3 s Rechenzeit) |
| Mitgelieferte Schriften laden | Bindet Schibsted Grotesk und IBM Plex Mono lokal ein |
| Google Fonts in Elementor unterbinden | Elementor lädt keine Schriften von Google-Servern |

## Katalog-Sync

Die Quelle ist führend. WordPress hält nur eine Kopie für SEO, Theme Builder und Loop Grid.

- **Automatisch:** stündlich per WP-Cron (`cinerent_shop_sync`). Für verlässliche Läufe den echten Server-Cron nutzen und `DISABLE_WP_CRON` setzen.
- **Manuell:** Button **Jetzt synchronisieren** in den Einstellungen.
- **Abgleich** über die externe ID (Meta `_cinerent_external_id`):
  - neue Einträge werden angelegt,
  - geänderte aktualisiert (unveränderte werden per Fingerabdruck übersprungen),
  - Einträge, die in der Quelle fehlen, werden **auf Entwurf gesetzt, nicht gelöscht**. Tauchen sie wieder auf, werden sie wieder veröffentlicht.
- **Kategorien** werden als Terme der Taxonomie `equipment_kategorie` angelegt bzw. umbenannt.
- **Bilder** werden in die Mediathek übernommen. Das erste Bild wird Beitragsbild, alle zusammen bilden die Galerie (Dynamic Tag „Bildergalerie“). Ändert sich ein Bild, wird es neu importiert und das alte entfernt.
- **Schutz:** Liefert die Quelle einen Fehler oder gar keine Einträge, bricht der Sync ab und ändert nichts.

**Schreibschutz im Backend:** Equipment-Beiträge zeigen „Wird aus Storybase gepflegt“. Titel, Inhalt, Preise, Bilder, Kategorie und Status sind ausgegraut und werden auch serverseitig gegen Änderungen geschützt (auch über REST und Schnellbearbeitung). Neu anlegen und Löschen ist gesperrt. Bearbeitbar bleiben die Felder von **Yoast SEO** bzw. **Rank Math**. Bei Kategorien bleiben Beschreibung und SEO-Felder bearbeitbar.

**Verfügbarkeit wird nie gespiegelt**, sondern immer live über die Datenquelle abgefragt. In der JSON-Phase lautet sie immer „auf Anfrage“.

## Datenmodell `katalog.json`

```json
{
  "kategorien": [
    { "id": "kameras", "slug": "kameras", "name": "Kameras", "reihenfolge": 1 }
  ],
  "items": [
    {
      "id": "eq-0001",
      "slug": "sony-fx6",
      "name": "Sony FX6",
      "kategorie": "kameras",
      "kurztext": "…",
      "beschreibung": "<p>…</p>",
      "technische_daten": [ { "label": "Sensor", "wert": "Vollformat" } ],
      "preis_tag_cent": 14900,
      "preis_woche_cent": 59600,
      "kaution_cent": 150000,
      "bilder": [ "assets/placeholder/kamera-sony-fx6.png", "https://…/bild.jpg" ],
      "ist_kit": false,
      "kit_inhalt": [ { "name": "V-Mount-Akku", "menge": 4 } ]
    }
  ]
}
```

- Beträge in Cent, Anzeige deutsch formatiert (`149,00 €`).
- `kategorie` verweist auf `id` (oder `slug`) einer Kategorie.
- `bilder`: Pfade relativ zum Plugin-Ordner oder https-URLs.
- `beschreibung` darf einfaches HTML enthalten (wird mit `wp_kses_post` bereinigt).
- Der Beispielkatalog enthält 6 Einträge in 6 Kategorien, darunter ein Kit, mit Platzhalterbildern. Preise sind Beispielwerte.

Eigene Datei außerhalb des Plugins verwenden (übersteht Updates):

```php
add_filter( 'cinerent_shop_local_json_file', fn() => WP_CONTENT_DIR . '/cinerent/katalog.json' );
```

## Elementor einrichten

### Globale Farben (Website-Einstellungen → Globale Farben)

Die Widgets übernehmen die globalen Farben und Schriften des Kits. Empfohlene Belegung:

| Elementor-Farbe | Name | Wert |
|---|---|---|
| Primär | Nacht | `#11181C` |
| Sekundär | Stein | `#59656E` |
| Text | Nacht | `#11181C` |
| Akzent | Fjord | `#2C4A5A` |
| Eigene Farbe | Eis | `#DCE5EA` |
| Eigene Farbe | Frost | `#F3F5F6` |
| Eigene Farbe | frei | `#2F7A4E` |
| Eigene Farbe | belegt | `#B3261E` |

Hintergrund der Seite: Frost oder Weiß. Rundungen 2 px, feine 1-px-Linien in Eis, keine Verläufe.

### Schriften (Website-Einstellungen → Globale Schriftarten)

| Elementor-Schrift | Schrift |
|---|---|
| Primär, Sekundär, Text | Schibsted Grotesk |
| Akzent | Schibsted Grotesk 500 |

Preise und technische Daten setzt das Plugin automatisch in **IBM Plex Mono**.

Die Schriften liegen in `assets/fonts/` (WOFF2, SIL Open Font License, Lizenztexte daneben). Es gibt zwei Wege, sie in Elementor zu nutzen:

**Weg A, automatisch (Standard):** Mit der Einstellung „Mitgelieferte Schriften laden“ erscheinen beide Schriften in jeder Elementor-Schriftauswahl in der Gruppe **„CineRent (lokal)“**. Die `@font-face`-Regeln kommen aus dem Plugin-Ordner.

**Weg B, als Elementor Custom Fonts:**

1. Elementor → Benutzerdefinierte Schriftarten → Neu hinzufügen.
2. Name: `Schibsted Grotesk`. Für jede Stärke eine Variante anlegen: Gewicht 400, 500, 600, 700, Stil normal, jeweils die passende Datei `schibsted-grotesk-latin-<gewicht>-normal.woff2` als WOFF2 hochladen.
3. Dasselbe für `IBM Plex Mono` mit 400 und 500 (`ibm-plex-mono-latin-<gewicht>-normal.woff2`).
4. Veröffentlichen. Danach in den Plugin-Einstellungen „Mitgelieferte Schriften laden“ ausschalten, damit die Schriften nicht doppelt geladen werden.

Zusätzlich unter Elementor → Einstellungen → Erweitert „Google Fonts laden“ deaktivieren (das Plugin unterbindet es ohnehin, wenn die Option aktiv ist).

## Theme-Builder-Vorlagen aufbauen

Elementor → Vorlagen → Theme Builder. Alle Inhalte kommen über die Dynamic Tags der Gruppe **CineRent** und die Standard-Tags von Elementor (Beitragstitel, Beitragsbild, Inhalt, Permalink).

### 1. Kopfzeile (Header)

- Logo, Menü
- Widget **Zeitraum-Auswahl**, Variante „Kompakt“
- Widget **Anfrageliste**, Variante „Mini“, Link auf die Seite „Anfrage“

### 2. Loop-Element „Equipment-Karte“ (Theme Builder → Loop-Element)

Vorschau-Inhalt: Equipment. Aufbau:

- Bild: Dynamic Tag *Beitragsbild*
- Überschrift: *Beitragstitel*, verlinkt mit *Beitrags-URL*
- Text: CineRent → *Kurztext*
- Text: CineRent → *Technische Daten*, Darstellung „Eine Zeile“, höchstens 3
- Text: CineRent → *Preis pro Tag*, davor „ab “, danach „ / Tag“ (Erweitert → Davor/Danach)
- Widget **Verfügbarkeits-Badge**
- Widget **Zur Anfrageliste**

### 3. Archiv „Equipment“ (Bedingung: Equipment-Archiv, Equipment-Kategorie-Archive)

- Widget **Zeitraum-Auswahl**, Variante „Groß“, „Seite nach Änderung neu laden“ an
- **Loop Grid** mit dem Loop-Element „Equipment-Karte“, Quelle: Aktuelle Abfrage (bzw. Equipment), optional Query-ID `cinerent_frei`
- Optional Taxonomie-Filter von Elementor Pro mit `equipment_kategorie`

### 4. Einzelseite „Equipment“ (Bedingung: Equipment → Alle)

- Galerie: Widget *Galerie* mit Dynamic Tag CineRent → *Bildergalerie*, oder *Beitragsbild*
- *Beitragstitel*, CineRent → *Kategorie* (verlinkt), *Kit ja/nein* (z. B. Text „Kit“)
- Preise: *Preis pro Tag*, *Preis pro Woche*, *Kaution*
- **Verfügbarkeits-Badge**, **Zeitraum-Auswahl** (groß), **Zur Anfrageliste**
- **Verfügbarkeitskalender**
- *Beitragsinhalt* (Beschreibung), CineRent → *Technische Daten*, CineRent → *Kit-Inhalt* (Anzeigebedingung: nur bei Kits)

### 5. Seite „Anfrage“ (normale Seite, mit Elementor bearbeiten)

- Widget **Anfrageliste**, Variante „Voll“ (Link leer lassen, wenn das Formular auf derselben Seite steht)
- Widget **Anfrageformular** (Anzeige von Liste und Zeitraum im Formular kann dann ausgeschaltet werden)

Equipment-Beiträge selbst nicht mit Elementor bearbeiten. Die Darstellung kommt ausschließlich aus den Theme-Builder-Vorlagen.

## Dynamic Tags

Gruppe **CineRent**, nutzbar in Theme-Builder-Vorlagen und im Loop Grid (der Tag liest immer den aktuellen Eintrag).

| Tag | Ausgabe | Optionen |
|---|---|---|
| Preis pro Tag | `149,00 €` | Mit Euro-Zeichen oder nur Zahl |
| Preis pro Woche | `596,00 €` | wie oben |
| Kaution | `1.500,00 €` | wie oben |
| Kurztext | Text | – |
| Technische Daten | Liste Label/Wert (`<dl class="cr-specs">`) | Liste oder eine Zeile; Anzahl begrenzen |
| Kit-Inhalt | Liste „Menge × Name“ (`<ul class="cr-kit">`) | – |
| Kit ja/nein | frei wählbarer Text | Text bei Kit / Einzelgerät |
| Kategorie | Kategoriename(n) | Mit Kategorieseite verlinken |
| Bildergalerie | Galerie (alle übernommenen Bilder) | – |

## Widgets

Kategorie **CineRent** im Elementor-Panel. Alle Widgets nutzen standardmäßig die globalen Farben und Schriften des Kits; Typografie, Farben, Rahmen, Rundung, Innen- und Zwischenabstände sind im Reiter „Stil“ einstellbar. Icons sind monochrome Inline-SVGs.

| Widget | Funktion |
|---|---|
| Zeitraum-Auswahl | Abholung/Rückgabe, „Kompakt“ für die Kopfzeile, „Groß“ für Seiten. Gilt seitenübergreifend (Cookie), zeigt die Mietdauer in Tagen. Gleicher Tag = 1 Miettag, sonst Differenz der Kalendertage. |
| Verfügbarkeits-Badge | frei / belegt / auf Anfrage für das aktuelle Equipment und den gewählten Zeitraum. Funktioniert im Loop Grid und auf der Einzelseite; alle Badges einer Seite werden mit einer Abfrage geladen. |
| Verfügbarkeitskalender | Monatsansicht (1 oder 2 Monate), belegte Tage markiert, optional Zeitraum per Klick wählen. |
| Zur Anfrageliste | Legt das Equipment auf die Anfrageliste; fragt in einem Dialog nach dem Zeitraum, falls noch keiner gewählt ist. |
| Anfrageliste | „Mini“ mit Zähler für die Kopfzeile, „Voll“ mit Menge, Richtwert je Position und Summe. Speicherung im Browser (localStorage). |
| Anfrageformular | Firma, Name, E-Mail, Telefon, Abholung/Versand, Nachricht, Zustimmung zu Mietbedingungen und Datenschutz. Übernimmt Anfrageliste und Zeitraum. |

**Richtwert:** volle Wochen zum Wochenpreis, Resttage zum Tagespreis, höchstens aber ein weiterer Wochenpreis. Der Richtwert ist unverbindlich und wird beim Absenden serverseitig neu berechnet (Preise aus dem Browser werden nie übernommen).

## Loop-Grid-Filter `cinerent_frei`

Im Loop Grid unter Abfrage → **Query-ID** `cinerent_frei` eintragen. Ist ein Zeitraum gewählt und liefert die Datenquelle Verfügbarkeiten, zeigt das Grid nur freie Geräte. In der JSON-Phase hat der Filter keine Wirkung (alles „auf Anfrage“).

Damit der Filter nach einer Zeitraum-Änderung greift, in der Zeitraum-Auswahl „Seite nach Änderung neu laden“ aktivieren.

## Anfrageformular und Spamschutz

Ablauf: Beim Laden holt das Formular per REST frische Werte (Nonce, Zeit-Token, ALTCHA-Challenge), rechnet den Proof-of-Work im Hintergrund und sendet die Anfrage als JSON an `/wp-json/cinerent/v1/anfrage`.

Schutzschichten, serverseitig in dieser Reihenfolge geprüft:

1. **Honeypot** – unsichtbares Feld `website`. Ausgefüllt = Bot; er erhält eine scheinbare Erfolgsmeldung.
2. **Nonce** (`cinerent_anfrage`).
3. **Mindest-Ausfüllzeit 3 Sekunden** – signiertes Zeit-Token (HMAC), höchstens 2 Stunden gültig.
4. **Rate-Limit je IP** über Transients (Standard 5 pro Stunde). Die IP wird nur als HMAC-Hash gespeichert.
5. **ALTCHA** – selbst gehostet, Proof-of-Work nach ALTCHA-Protokoll (SHA-256, HMAC-signiert, Ablaufzeit, jede Challenge nur einmal gültig). Der Löser (`assets/js/altcha.js`) ist eine schlanke eigene Umsetzung ohne externe Dienste und läuft in einem Web Worker. Das offizielle ALTCHA-Widget wird nicht benötigt.
6. **Validierung** – Pflichtfelder, E-Mail, Zeitraum, Zustimmungen; Positionen werden gegen veröffentlichte Equipment-Beiträge geprüft.

Versand vorerst per `wp_mail` an die hinterlegte Adresse (Antworten gehen per Reply-To direkt an den Kunden) plus Bestätigungsmail an den Kunden. Für zuverlässige Zustellung ein SMTP-Plugin verwenden.

Steht WordPress hinter einem Proxy oder CDN, die echte Besucher-IP über den Filter `cinerent_shop_client_ip` liefern.

## Datenschutz, Cookies, Caching

- **Keine externen Ressourcen:** Schriften, Icons, Skripte und der Spamschutz liegen im Plugin. Keine Google Fonts, keine CDNs.
- **Cookie `cinerent_zeitraum`** (Inhalt `JJJJ-MM-TT_JJJJ-MM-TT`, 30 Tage, SameSite=Lax): speichert nur den gewählten Mietzeitraum, keine personenbezogenen Daten. Technisch notwendig für die gewünschte Funktion; in der Datenschutzerklärung erwähnen.
- **localStorage `cinerent_anfrageliste`:** Gerätenamen, IDs und Preise der Anfrageliste, nur im Browser.
- **Formulardaten** werden nur per E-Mail versendet, nicht in der Datenbank gespeichert. Der Zeitpunkt der Zustimmung steht in der Mail.
- **Keine Benutzerkonten** für Mietkunden.
- **Seiten-Cache:** Alle Seiten sind cachebar. Zeitraum, Anfrageliste, Badges, Kalender und Formular-Token werden im Browser bzw. über nicht gecachte REST-Antworten geladen. Ausnahme: Seiten mit dem Filter `cinerent_frei` hängen vom Cookie ab. Diese Seiten im Cache-Plugin ausschließen oder den Cache nach dem Cookie `cinerent_zeitraum` variieren lassen.

## Wechsel auf die Storybase-API

Die Seiten müssen dafür nicht umgebaut werden: Vorlagen, Widgets und Tags lesen nur den gespiegelten CPT bzw. die REST-Endpunkte des Plugins, nie die Quelle direkt.

Aufbau der Datenschicht:

```
includes/data/interface-data-source.php      Schnittstelle Data_Source
includes/data/class-catalog.php              Normalisierung (gemeinsames Format)
includes/data/class-local-json-source.php    LocalJsonSource
includes/data/class-storybase-api-source.php StorybaseApiSource (Stub)
```

Geplante Endpunkte (Supabase Edge Functions, alle Aufrufe serverseitig aus PHP, Header `x-shop-key`):

| Methode der Schnittstelle | Geplanter Endpunkt | Antwort |
|---|---|---|
| `get_categories()` | `GET {basis}/shop-katalog` | `{ kategorien: [...] }` |
| `get_items()` | `GET {basis}/shop-katalog` | `{ items: [...] }` im Format von `katalog.json` |
| `get_availability( ids, von, bis )` | `GET {basis}/shop-verfuegbarkeit?ids=…&von=…&bis=…` | `{ "<id>": "frei" \| "belegt" \| "anfrage" }` |
| `get_calendar( id, monat )` | `GET {basis}/shop-kalender?id=…&monat=JJJJ-MM` | `{ status: "ok", belegt: ["JJJJ-MM-TT", …] }` |
| `submit_inquiry( anfrage )` | `POST {basis}/shop-anfrage` | Anfrage inkl. Code-Bestätigung per E-Mail durch Storybase |

Schritte:

1. In `class-storybase-api-source.php` die Methoden mit `$this->request()` umsetzen (Hilfsfunktion ist vorhanden) und Antworten durch `Catalog::normalize_item()` / `normalize_category()` schicken.
2. `supports_availability()` und `supports_inquiries()` auf `true` setzen. Ab dann zeigen Badges und Kalender echte Daten, der Filter `cinerent_frei` greift, und Anfragen gehen an Storybase statt per `wp_mail`.
3. Shop-Schlüssel in `wp-config.php` hinterlegen (`CINERENT_STORYBASE_SHOP_KEY`), Basis-URL in den Einstellungen.
4. Auf Staging die Quelle umstellen und **Jetzt synchronisieren**. Gleiche externe IDs werden aktualisiert, neue angelegt, nicht mehr vorhandene auf Entwurf gesetzt.

Bis dahin liefert der Stub für den Katalog einen Fehler (der Sync bricht dann ohne Änderungen ab) und für die Verfügbarkeit „auf Anfrage“.

## Entwicklung

```
cinerent-shop.php            Plugin-Header, Autoloader
includes/                    PHP-Klassen (Namespace CineRent\Shop)
includes/elementor/          Integration, Dynamic Tags, Widgets
assets/css, assets/js        Frontend und Backend, ohne Build-Schritt
assets/fonts                 Schriften (WOFF2, OFL)
assets/placeholder           Platzhalterbilder des Beispielkatalogs
data/katalog.json            Beispielkatalog
languages/cinerent-shop.pot  Übersetzungsvorlage
bin/build-zip.sh             Baut dist/cinerent-shop-<version>.zip
```

ZIP bauen:

```bash
bin/build-zip.sh
```

Coding Standards prüfen (WPCS 3):

```bash
composer global require --dev wp-coding-standards/wpcs:^3
phpcs --standard=phpcs.xml.dist .
```

Übersetzungsvorlage neu erzeugen:

```bash
wp i18n make-pot . languages/cinerent-shop.pot --exclude=dist,bin,vendor
```

REST-Endpunkte (`/wp-json/cinerent/v1/…`): `verfuegbarkeit`, `kalender`, `formular`, `anfrage`.

Hooks für Erweiterungen: Filter `cinerent_shop_data_source`, `cinerent_shop_local_json_file`, `cinerent_shop_client_ip`; Actions `cinerent_shop_synced`, `cinerent_shop_inquiry_sent`.

Deinstallation entfernt Einstellungen und Cron. Equipment-Beiträge, Kategorien und Bilder bleiben erhalten.
