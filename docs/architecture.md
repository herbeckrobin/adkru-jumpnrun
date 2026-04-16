# Architektur

Das Repo IST das WordPress-Plugin. Keine Monorepo-Ceremony, keine zusaetzlichen Workspaces. Ein Klon = ein installierbares Plugin.

## Verzeichnis-Layout

```
adkru-jumpnrun/                    ← Git-Repo = WP-Plugin
├── jumpnrun.php                   ← Plugin-Header, Entry-Point
├── composer.json                  ← PHP-Abhaengigkeiten (Plugin-Update-Checker)
├── readme.txt                     ← WP.org-Stil Plugin-Info
├── src-php/                       ← PHP-Code (PSR-4 Jumpnrun\)
│   ├── Shortcode/                 ← [jumpnrun] + [jumpnrun_scoreboard]
│   ├── Admin/                     ← Settings-Page, Scoreboard-Moderation
│   ├── Api/                       ← REST-Controller /wp-json/jumpnrun/v1/*
│   ├── AntiCheat/                 ← HMAC-Session + Plausibility
│   ├── Db/                        ← Schema (dbDelta), Repositories
│   └── Config/                    ← ConfigService
├── src-js/                        ← Client-JS (TypeScript, strict)
│   ├── engine/                    ← Framework-unabhaengige Spiel-Engine
│   ├── renderer/                  ← Canvas-Renderer
│   ├── config/                    ← Zod-Schemas, Defaults
│   └── client.ts                  ← Entry, Vite-Bundle-Target
├── assets/                        ← Static Assets
│   ├── game/                      ← Vite-Output (gitignored): client.mjs, client.css
│   └── images/                    ← WebP-Sprites, Backgrounds, Coin, Obstacles
├── views/                         ← PHP-Templates fuer Admin + Shortcode-Wrapper
├── languages/                     ← .po / .mo
├── vendor/                        ← Composer-Output (gitignored)
├── demo/                          ← Standalone-Dev-Playground (nicht im Release)
├── tests/
│   ├── js/                        ← Vitest
│   ├── php/                       ← PHPUnit
│   └── e2e/                       ← Playwright
├── scripts/                       ← Build- & Release-Tooling
├── docs/                          ← Diese Dokumentation
└── legacy/                        ← Original-Prototyp vom Kunden (Referenz)
```

## Engine (src-js/engine)

- **Fixed-Timestep Game-Loop** nach [Glenn Fiedler](https://gafferongames.com/post/fix_your_timestep/): 60 Hz fester Physik-Step, Accumulator, Spiral-of-Death-Cap bei 250 ms
- **Physik in px/s² und px/s**, nicht px/frame — loest den FPS-abhaengigen Bug des Legacy-Codes
- **Systems:** `Input → Physics → Spawn → Collision → Scoring → Difficulty → Cleanup`
- **DOM-frei** → in Node/Vitest testbar ohne jsdom
- **Typisierter Event-Bus** mit 23+ typed Events
- **Seeded PRNG** (xorshift) → deterministische Spawn-Tests

## Renderer (src-js/renderer)

- Responsive Canvas-Renderer mit DPR-Korrektur
- Parallax-Layers (konfigurierbar)
- Asset-Loader mit Progress-Reporting
- Rein lesend gegenueber der Engine — Engine weiss nichts vom Renderer

## PHP-Seite (src-php)

- Plugin-Header mit `Update URI` fuer GitHub-Auto-Update ([Plugin-Update-Checker](https://github.com/YahnisElsts/plugin-update-checker))
- PSR-4 Autoload unter `Jumpnrun\`
- Shortcodes `[jumpnrun]` und `[jumpnrun_scoreboard]`
- Admin-Menue mit Settings- und Scoreboard-Seite
- REST-Routes unter `/wp-json/jumpnrun/v1/*`
- HMAC-signierte Session-Tokens fuer Anti-Cheat
- dbDelta-Migration fuer `wp_jumpnrun_highscores` + `wp_jumpnrun_sessions`

## Build-Pipeline

```
src-js/client.ts  ──[vite build]──▶  assets/game/client.mjs (+ .css)
                                              │
                                              ▼
jumpnrun.php  ──wp_enqueue_script_module──▶  Browser
```

## Config-Flow

```
WP-Admin-Settings  ──▶  wp_options['jumpnrun_config'] (JSON)
                                    │
                                    ▼
                      ConfigService::getConfig() (PHP)
                                    │
                        deep-merge auf Defaults (PHP)
                                    │
                    wp_localize_script (enqueue als Global)
                                    │
                                    ▼
                       parseConfig() via Zod (JS-Client)
                                    │
                       createGame(config, canvas)
```

## Warum kein Monorepo?

Siehe [ADR-002](adr/002-flat-plugin-layout.md). Kurzform: Das Deliverable ist ein WordPress-Plugin. Tomy installiert eine ZIP. Engine und Renderer bleiben intern sauber getrennt ueber Ordnergrenzen — aber nicht als publishable Packages.

## Architecture Decision Records

Siehe [adr/](adr/).
