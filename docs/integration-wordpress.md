# Integration in WordPress

Das Plugin liefert nur das **Spiel-Fenster**. Layout, Werbung und Text drumherum baut der Site-Admin mit dem bevorzugten Page-Builder (Elementor, Gutenberg, Bricks).

## Shortcode-Optionen

### `[jumpnrun]`

| Attribut | Default | Bedeutung |
|---|---|---|
| `width`  | `960` | Canvas-Breite (responsive skaliert) |
| `height` | `540` | Canvas-Hoehe |
| `config` | — | JSON-Override einzelner Config-Werte (Power-User) |

Beispiel:

```
[jumpnrun width="1280" height="720"]
```

### `[jumpnrun_scoreboard]`

| Attribut | Default | Bedeutung |
|---|---|---|
| `limit` | `10` | Anzahl Eintraege |
| `show_rank` | `1` | Rang-Spalte anzeigen |
| `show_level` | `1` | Erreichtes Level anzeigen |

## Elementor-Beispiel (Tomys Standard-Setup)

1. Section mit 3 Columns anlegen (links: Banner, Mitte: Game, rechts: Banner)
2. In die mittlere Column einen **Shortcode**-Widget ziehen
3. Shortcode: `[jumpnrun]`
4. Seiten-Breite (Container) auf 1440px stellen, damit die mittlere Column mindestens 960px hat
5. Darunter eine neue Section mit Shortcode `[jumpnrun_scoreboard limit="10"]`

## Gutenberg-Beispiel

```
<!-- wp:shortcode -->
[jumpnrun]
<!-- /wp:shortcode -->
```

## Responsiveness

Das Canvas skaliert sich per CSS auf die Container-Breite. Auf Mobile erscheint ein zusaetzlicher Touch-Jump-Button. Keine weiteren Anpassungen noetig.

## Sprache

Standardmaessig Deutsch. WordPress-Sprache bestimmt die Spiel-Strings (Labels, Overlays). Uebersetzungen liegen in `packages/wp-plugin/languages/`.
