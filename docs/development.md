# Entwicklung

## Voraussetzungen

- [Bun](https://bun.sh) 1.3+ (`brew install oven-sh/bun/bun`)
- [DDEV](https://ddev.com) 1.24+ (`brew install ddev/ddev/ddev`)
- Docker Desktop oder OrbStack
- Node 22+ (fuer Tooling-Fallbacks)

## Einmaliges Setup

Im Projekt-Root (`jumpnrun/`, **nicht** im Repo):

```bash
ddev start          # Startet Container (WP, MariaDB, Node)
ddev setup-wp       # Laedt WordPress nach Code/wp/, legt Plugin-Symlink, aktiviert Plugin
```

Nach dem Setup:

- **Site:**  https://adkru-jumpnrun.ddev.site
- **Admin:** https://adkru-jumpnrun.ddev.site/wp-admin (User `admin`, Passwort `admin`)
- **DB:**    `ddev mysql` oder PHPMyAdmin via `ddev launch -p`

`Code/wp/` wird erst beim Setup angelegt — vorher existiert der Ordner nicht.

## Struktur

```
jumpnrun/                                      ← DDEV approot (nicht versioniert)
├── .ddev/                                     ← DDEV-Config (nicht versioniert)
├── Code/
│   ├── wp/                                    ← WordPress-Core (lokal, nicht im Repo)
│   │   └── wp-content/plugins/jumpnrun       → Symlink auf ../../../../adkru-jumpnrun
│   └── adkru-jumpnrun/                        ← Git-Repo (dieses Plugin)
└── Projekt/                                   ← Notizen, Assets (nicht versioniert)
```

Der Symlink `Code/wp/wp-content/plugins/jumpnrun` zeigt auf den Repo-Root von `Code/adkru-jumpnrun/` — Aenderungen an `jumpnrun.php`, `src-php/`, `assets/game/` sind sofort live in WordPress.

## Dev-Workflow

```bash
cd Code/adkru-jumpnrun

bun install                 # JS-Deps installieren
bun run dev                 # Standalone-Demo auf http://localhost:5173 (ohne WP)
bun run build               # Baut Engine + Renderer → assets/game/client.mjs
bun run test                # Vitest
bun run typecheck           # TypeScript strict-check
bun run lint                # Biome
bun run format              # Biome auto-format
bun run package-plugin      # release/jumpnrun-<version>.zip (nach build + composer install --no-dev)

composer install            # PHP-Deps (Plugin-Update-Checker, PHPUnit)
```

## Typischer WP-Plugin-Development-Flow

1. PHP-Code in `src-php/` bearbeiten oder JS in `src-js/`
2. Nach JS-Aenderungen: `bun run build` → Browser refresh
3. PHP-Aenderungen: Browser refresh reicht (Symlink zeigt auf Live-Dateien)
4. Debug-Logs: `tail -f ../wp/wp-content/debug.log` oder `ddev logs -s web`

## Release

Siehe [release-workflow.md](release-workflow.md).
