# adkru-jumpnrun

Jump-and-Run WordPress-Plugin als White-Label-Showcase fuer ADKRU.

**Status:** v0.0.1 — Phase 1 (Setup). Noch kein spielbares Ergebnis.

## Was das hier ist

Das Repo **IST** das WordPress-Plugin. Einmal klonen oder eine Release-ZIP laden — und installieren. Siehe [ADR-002](docs/adr/002-flat-plugin-layout.md) zur Struktur-Entscheidung.

- `jumpnrun.php` — Plugin-Entry
- `src-php/` — PHP-Code (PSR-4 `Jumpnrun\`)
- `src-js/engine/` — framework-unabhaengige Spiel-Engine (TypeScript, DOM-frei)
- `src-js/renderer/` — Canvas-Renderer
- `src-js/client.ts` — Vite-Entry, buendelt nach `assets/game/client.mjs`

Das Plugin liefert nur das Spiel-Fenster. Werbung, Text und Layout drumherum baut der WordPress-Nutzer (Elementor o.a.) selbst.

## Schnelleinstieg

- **Installation im WordPress:** siehe [docs/install-guide.md](docs/install-guide.md)
- **Shortcode-Nutzung:** siehe [docs/integration-wordpress.md](docs/integration-wordpress.md)
- **Gameplay anpassen:** siehe [docs/tuning-guide.md](docs/tuning-guide.md)
- **Architektur:** siehe [docs/architecture.md](docs/architecture.md)
- **Release-Workflow:** siehe [docs/release-workflow.md](docs/release-workflow.md)

## Entwicklung

```bash
bun install
composer install
bun run dev             # Standalone-Demo ohne WordPress (Port 5173)
bun run test            # Vitest
bun run build           # Baut src-js/client.ts → assets/game/client.mjs
bun run package-plugin  # Erstellt release/jumpnrun-<version>.zip
```

Vollstaendiges WP-Plugin-Testing im DDEV (im Projekt-Root, nicht im Repo):

```bash
ddev start
ddev setup-wp   # laedt WordPress, legt Plugin-Symlink an, aktiviert
# → https://adkru-jumpnrun.ddev.site
```

## Lizenz

GPL-2.0-or-later (WordPress-Standard).
