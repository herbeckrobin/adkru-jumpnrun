=== Jump-n-Run by ADKRU ===
Contributors: herbeckrobin
Tags: game, jump-and-run, shortcode, highscore
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 0.4.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Jump-and-Run-Spiel als Shortcode.

== Description ==

Rendert ein Jump-and-Run-Spiel per Shortcode `[jumpnrun]` und eine Highscore-Liste per `[jumpnrun_scoreboard]`.
Werbung und Layout drumherum baut der Site-Admin selbst (Elementor, Gutenberg o.a.).

Updates kommen per GitHub-Release. Einmal per ZIP installieren, danach 1-Klick-Updates im WP-Admin.

== Installation ==

1. ZIP aus dem aktuellen GitHub-Release herunterladen
2. Plugins → Neu hinzufuegen → Plugin hochladen
3. Aktivieren
4. Shortcode `[jumpnrun]` in eine Seite einsetzen

== Changelog ==

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
