# ADR-002: Flaches Plugin-Layout statt Monorepo

## Status

Akzeptiert — 2026-04-16

## Kontext

Der ursprueiliche Plan sah ein Bun-Workspaces-Monorepo mit `packages/engine`, `packages/renderer-canvas` und `packages/wp-plugin` vor. Trennung von Engine, Renderer und Plugin-Shell auf Package-Ebene klingt sauber.

Im Sanity-Check (vor dem ersten Commit) wurde aber klar: Das Deliverable ist ein WordPress-Plugin. Tomy erwartet eine ZIP, die er per `Plugins → Hochladen` installiert. Engine und Renderer werden nie separat publiziert. Der Workspaces-Overhead (Bundling-Konfiguration ueber drei Packages, Symlink-Gymnastics beim Release, `package-plugin.mjs` das aus Subpackages stagt) bringt keinen sichtbaren Nutzen fuer das Deliverable und keinen echten Gewinn bei der Trennung der Schichten.

## Entscheidung

Das Git-Repo ist das WordPress-Plugin. Layout flach:

- `jumpnrun.php` (Plugin-Entry) im Repo-Root
- `src-php/` (PHP, PSR-4 `Jumpnrun\`) im Repo-Root
- `src-js/engine/` und `src-js/renderer/` als Ordner — nicht als separate Packages
- `src-js/client.ts` als einziger Vite-Entry, buendelt beides
- `demo/` als Standalone-Dev-Playground (nicht im Release)
- `tests/{js,php,e2e}/` alle im Repo-Root

## Konsequenzen

**Positiv:**
- Ein `git clone` = installierbares Plugin (nach `composer install --no-dev` + `bun run build`)
- Release-Action viel einfacher: Build, Install, Zip — fertig
- Klare mentale Map fuer Tomy: Repo = Plugin
- Symlink im DDEV-Setup zeigt direkt auf den Repo-Root
- Weniger package.json-Files, weniger tsconfig-Vererbung

**Negativ (bewusst akzeptiert):**
- Engine + Renderer sind nicht als npm-Packages publizierbar (brauchen wir nicht)
- Wenn spaeter ein zweites Game darauf basieren soll, muessen wir Extrahieren — aber das ist ein Umbau mit realem Druck, nicht spekulativ jetzt

**Architektonische Trennung bleibt erhalten** — nur ueber Ordnergrenzen statt Package-Grenzen. Engine-Code importiert niemals aus `../renderer/` oder `../../src-php/`. Die Disziplin sitzt im Code-Review, nicht im Workspace-Manifest.

## Verification

- Kein TypeScript-Import aus `src-js/engine/` in `src-js/renderer/` (Renderer nutzt Engine, nicht umgekehrt)
- Kein Import aus `src-js/client.ts` in `src-js/engine/` oder `src-js/renderer/`
- Engine-Tests in `tests/js/` laufen in Node ohne jsdom
