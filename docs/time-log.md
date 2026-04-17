# Zeit-Log — adkru-jumpnrun

| Datum | Phase | Aktivitaet | Soll | Ist |
|---|---|---|---|---|
| 2026-04-16 15:40 | 0 | Planung, Plan-Datei geschrieben | — | — |
| 2026-04-16 16:30 | 1 | Repo-Struktur, Bun-Workspaces, Package-Skelette, WP-Plugin-Header mit PUC, DDEV-Config im Root, WP-Setup-Script, CI + Release-Workflow, Docs-Skelette, Git-Init, GitHub-Repo, Test-Tag | 2-4 h | ~1 h |
| 2026-04-16 17-21 | 2-6 | Engine (GameLoop, World, Entities, Physics, Spawning, Collision, Scoring), Renderer (Canvas, AssetLoader, Sprite-Masken), Input, Vite-Bundle, Shortcode-Render, erste spielbare Version | 14-24 h | ~5 h |
| 2026-04-17 13-14 | 8 | Milestone A: DB-Schema (Highscores + Sessions), Repositories, REST-API (Session/Score/Highscores), RateLimiter, Shortcodes als Klassen, Plugin-Bootstrap, JS-ApiClient, Save-Flow mit Name-at-End, PHPUnit-Setup | 2-3 h | ~3 h |
| 2026-04-17 15-16 | 7 | Milestone B: ConfigSchema als Single-Source-of-Truth, ConfigService mit Merge, Admin-Menu + Settings-Page + Scoreboard-Page, Views, Shortcode mit engine-Config, PHPUnit-Tests | 3-5 h | ~2 h |
| 2026-04-17 16 | 10 | Milestone C: Docs finalisieren (Tuning-Guide, API-Doc), ADRs (003 Zod, 004 Slim-AntiCheat, 005 Schema-driven, 006 GitHub-Updater) | 2-3 h | ~1 h |

## Naechste Checkpoints

- Version 0.3.0 taggen und pushen → GitHub Action erstellt Release
- Staging-WP: Upgrade-Test von 0.1.1 → 0.3.0 via PUC
- Falls stabil: Showcase an Tomy kommunizieren
