# Installation — Jump-n-Run by ADKRU

## Voraussetzungen

- WordPress 6.4 oder neuer
- PHP 8.2 oder neuer
- Schreibrecht auf `wp-content/plugins/`

## Installation in 4 Schritten

1. Aktuelle ZIP herunterladen: [https://github.com/herbeckrobin/adkru-jumpnrun/releases/latest](https://github.com/herbeckrobin/adkru-jumpnrun/releases/latest) → Asset `jumpnrun-<version>.zip`
2. WordPress-Admin → **Plugins → Neu hinzufuegen → Plugin hochladen**
3. ZIP auswaehlen → **Jetzt installieren** → **Aktivieren**
4. Shortcode `[jumpnrun]` in eine Seite oder einen Post einsetzen

## Automatische Updates

Ab diesem Moment zeigt WordPress neue Versionen unter **Plugins** an, sobald Robin einen neuen Release auf GitHub veroeffentlicht. Per Klick updaten — fertig.

Kein Marketplace-Account noetig, keine Tokens, kein manueller ZIP-Austausch.

## Konfiguration

Nach der Aktivierung gibt es im WP-Admin ein neues Menue **"Jump-n-Run"**:

- **Einstellungen** — Rabattcode, Trigger-Level, Feature-Flags, Anti-Cheat
- **Scoreboard** — Eingehende Scores moderieren, verdaechtige Eintraege loeschen

## Shortcodes

| Shortcode | Zweck |
|---|---|
| `[jumpnrun]` | Rendert das Spiel-Fenster |
| `[jumpnrun_scoreboard]` | Zeigt die Top-10 als Tabelle |
| `[jumpnrun_scoreboard limit="5"]` | Top-5 statt Top-10 |

Mehr Details: [integration-wordpress.md](integration-wordpress.md).

## Deinstallation

Plugin deaktivieren reicht nicht — per **Deinstallieren** wird (optional, Opt-In in Settings) auch die Highscore-Tabelle entfernt. Per Default bleiben die Scores erhalten.
