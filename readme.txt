=== Jump-n-Run by ADKRU ===
Contributors: herbeckrobin
Tags: game, jump-and-run, shortcode, highscore
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 0.7.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Jump-and-Run-Spiel als Shortcode, Gutenberg-Block oder Elementor-Widget.

== Description ==

Rendert ein Jump-and-Run-Spiel mit Highscore-Liste. Drei Einbindungswege, alle
mit denselben Einstellungen:

* Shortcode `[jumpnrun]`, dazu `[jumpnrun_scoreboard]` für die Bestenliste
* Gutenberg-Block "Jump and Run"
* Elementor-Widget "Jump and Run" mit eigenen Panel-Feldern

Werbung und Layout drumherum baut der Site-Admin selbst.

Updates kommen per GitHub-Release. Einmal per ZIP installieren, danach 1-Klick-Updates im WP-Admin.

== Installation ==

1. ZIP aus dem aktuellen GitHub-Release herunterladen
2. Plugins, Neu hinzufügen, Plugin hochladen
3. Aktivieren
4. Spiel einsetzen, wahlweise per Shortcode `[jumpnrun]`, Gutenberg-Block oder Elementor-Widget

== Changelog ==

= 0.7.0 =
* Elementor-Widget "Jump and Run" mit eigener Panel-Kategorie und Feldern für Breite, Höhe und Rabattcode
* Gutenberg-Block `jumpnrun/game` mit denselben Feldern
* Fix: Auf Seiten, die mit einem Page-Builder gebaut sind, wurde das Spiel-Bundle nicht geladen. Die Prüfung lief gegen post_content, Elementor speichert den Inhalt aber in _elementor_data. Sichtbar war eine leere Fläche. Das Laden hängt jetzt am Rendern statt an einer Inhalts-Heuristik.
* Editor-Vorschau zeigt eine maßstabsgetreue Platzhalter-Fläche in Spielfeldgröße statt das laufende Spiel
* Instanz-Attribute liegen in einem gemeinsamen Schema, aus dem Shortcode, Block und Widget ihre Felder ableiten
* Packaging bricht ab, wenn Plugin-Header, Konstante, package.json und readme.txt unterschiedliche Versionen tragen
* Sourcemaps landen nicht mehr im Release-ZIP

= 0.5.0 =
* Highscore-Liste wandert vom permanenten Sidebar-Slot in den Game-Over-Overlay (Canvas bekommt mehr Platz, Liste motiviert am richtigen Moment)
* Pre-Flight-Namenscheck beim Speichern: Warnung wenn der eingegebene Name schon einem anderen Highscore-Eintrag gehört, mit Auswahl "Anderen Namen" oder "Score ersetzen/Verwerfen" je nach Score-Vergleich
* Rang-Anzeige stimmt bei Score-Gleichstand jetzt mit der Listen-Sortierung überein (Tie-Breaking nach updated_at, identisch zum topN-Query)
* Neuer REST-Endpoint `GET /highscore/lookup?name=X` für den Pre-Flight-Check

= 0.4.0 =
* Asset-Pools als CPTs: Hintergründe, Hindernisse und Plattformen als eigene Inhaltstypen mit Featured-Image, gewichteter Zufallsauswahl und Min-Level-Filter
* Neue zentrale Admin-Seite "Spiel-Assets" mit 4 Tabs (Hintergründe, Hindernisse, Plattformen, Sprites) auf WP_List_Table-Basis (Bulk-Actions, Trash/Restore, Suche, Pagination)
* Sprite-Settings (Spielfigur, Coin) per Media-Picker, mit Seed-Button für die mitgelieferten Defaults
* Pool-First-Konsistenz: keine Mediathek-Zuweisung → Solid-Color-Fallback im Spiel
* Highscore-Sidebar permanent neben dem Canvas mit Auto-Refresh nach Save
* 3-Sek-Countdown nach Bestätigen des Rabattcode-Popups (kein Sofort-Tod mehr)
* Umlaute im Namensfeld zugelassen (Client-Regex auf Unicode umgestellt)
* Coins spawnen nicht mehr in Hindernissen
* Debug-Toggle auf Shift+Ctrl+D + URL-Param ?jnr-debug=1 (statt versehentlich triggerbar mit D)

= 0.3.0 =
DB-Schema, REST-Endpoints, Schema-driven Settings, Slim-AntiCheat ohne HMAC.

= 0.0.1 =
Phase 1 — Plugin-Skelett, noch kein spielbares Ergebnis.
