# adkru-jumpnrun

Jump-and-Run WordPress-Plugin als White-Label-Showcase fuer ADKRU.

**Status:** v0.5.0 — feature-komplett und produktiv einsetzbar.

## Was das hier ist

Das Repo **IST** das WordPress-Plugin. Einmal klonen oder eine Release-ZIP laden und installieren. Siehe [ADR-002](docs/adr/002-flat-plugin-layout.md) zur Struktur-Entscheidung.

Das Plugin liefert zwei Shortcodes:

- `[jumpnrun]` — das Spiel-Fenster (Canvas + UI-Overlay)
- `[jumpnrun_scoreboard]` — Top-N Highscore-Tabelle als eigenstaendiger Block

Werbung, Text und Layout drumherum baut der WordPress-Nutzer (Gutenberg, Elementor o.a.) selbst.

## Features

- Spielbare Endless-Runner-Engine in TypeScript mit fixed Timestep (ADR-001), pixel-praezisen Hitboxen und Contain-Fit-Rendering
- Persistente Highscores in eigener DB-Tabelle, REST-Endpoints fuer Session-Start, Score-Submit, Top-N und Namens-Lookup
- Pre-Flight-Namenscheck im Game-Over-Overlay (keine Ueberschreibung fremder Scores)
- Asset-Pools als Custom Post Types (Hintergruende, Hindernisse, Plattformen) mit gewichteter Zufallsauswahl und Min-Level-Filter
- Zentrale Admin-Seite "Spiel-Assets" mit vier Tabs auf `WP_List_Table`-Basis (Bulk-Actions, Trash/Restore, Suche, Pagination)
- Sprite-Settings (Spielfigur, Coin) per Media-Picker, mit Seed-Button fuer die mitgelieferten Defaults
- Schema-driven Settings-Page mit Tabs nach Kategorie (Branding, Engine-Tuning, AntiCheat) — siehe [ADR-005](docs/adr/005-schema-driven-settings.md)
- Rabattcode-Popup bei konfigurierbarem Level + 3-Sek-Countdown vor dem Weiterspielen
- Slim-AntiCheat ohne HMAC: Session-Validierung, Rate-Limiting per IP, Plausibilitaetspruefung der Scores — siehe [ADR-004](docs/adr/004-slim-anticheat-no-hmac.md)
- GitHub-Updater: Tag-Pushes werden automatisch als 1-Klick-Update im WP-Admin erkannt — siehe [ADR-006](docs/adr/006-github-updater-over-wporg.md)
- PHPUnit-Tests fuer Config, Sanitizer, RateLimiter, ConfigSchema und Merge-Verhalten

## Repo-Struktur

- `jumpnrun.php` — Plugin-Entry
- `src-php/` — PHP-Code (PSR-4 `Jumpnrun\`)
  - `Admin/` — Admin-Menu, Settings, Scoreboard, Assets-Page, MetaBoxes
  - `Api/` — REST-Controller + Endpoints (Session, Score, Highscore, Lookup)
  - `AntiCheat/` — RateLimiter, Sanitizer, Validator
  - `Assets/` — Asset-Pool-CPTs und Sprite-Settings
  - `Config/` — ConfigService + ConfigSchema (Single-Source-of-Truth)
  - `Db/` — Schema, Repositories, Migrationen
  - `Shortcode/` — `[jumpnrun]` und `[jumpnrun_scoreboard]`
- `src-js/engine/` — framework-unabhaengige Spiel-Engine (TypeScript, DOM-frei)
- `src-js/renderer/` — Canvas-Renderer mit Sprite-Loading
- `src-js/client.ts` — Vite-Entry, buendelt nach `assets/game/client.js`
- `src-js/api-client.ts` — REST-Client fuer Save-Flow
- `demo/` — Standalone-Browser-Demo ohne WordPress
- `docs/adr/` — Architecture Decision Records (001-006)
- `legacy/` — alter Vanilla-JS-Prototyp (Referenz, nicht ausfuehrbar)

## Schnelleinstieg

- **Installation im WordPress:** siehe [docs/install-guide.md](docs/install-guide.md)
- **Shortcode-Nutzung:** siehe [docs/integration-wordpress.md](docs/integration-wordpress.md)
- **Gameplay anpassen:** siehe [docs/tuning-guide.md](docs/tuning-guide.md)
- **Architektur:** siehe [docs/architecture.md](docs/architecture.md)
- **REST-API:** siehe [docs/api.md](docs/api.md)
- **Release-Workflow:** siehe [docs/release-workflow.md](docs/release-workflow.md)

## Entwicklung

```bash
bun install
composer install
bun run dev             # Standalone-Demo ohne WordPress (Port 5173)
bun run test            # Vitest
bun run build           # Baut src-js/client.ts → assets/game/client.js
bun run typecheck       # tsc --noEmit
bun run lint            # Biome
bun run package-plugin  # Erstellt release/jumpnrun-<version>.zip
```

Vollstaendiges WP-Plugin-Testing im DDEV (im Projekt-Root, nicht im Repo):

```bash
ddev start
ddev setup-wp   # laedt WordPress, legt Plugin-Symlink an, aktiviert
# → https://adkru-jumpnrun.ddev.site
```

## Requirements

- WordPress 6.5+
- PHP 8.2+
- Node 22+ / Bun 1.3+ (nur fuer Development)

## Lizenz

GPL-2.0-or-later (WordPress-Standard).
