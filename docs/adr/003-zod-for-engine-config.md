# ADR-003: Zod fuer Engine-Config-Validierung

## Status

Akzeptiert — 2026-04-16

## Kontext

Die Engine-Config landet ueber mehrere Wege im JS-Client: vom WordPress-Shortcode ueber `window.JumpnrunConfig`, aus den Admin-Settings, optional per Shortcode-Attribut oder JSON-Override. Fehlende Felder, ungueltige Typen oder out-of-range-Werte muessen abgefangen werden, bevor sie die Game-Loop erreichen.

## Entscheidung

[Zod](https://zod.dev) definiert das `GameConfigSchema` in [src-js/engine/config/index.ts](../../src-js/engine/config/index.ts) mit `default()` pro Feld und `z.number().positive()` / `z.int()` als Range-Constraints.

Der Client ruft beim Bootstrap `GameConfigSchema.parse(rawConfig.engine ?? {})` — fehlende Felder fallen auf Defaults, unbekannte Felder werden ignoriert, ungueltige Werte werfen einen Fehler.

Der Server sanitized bereits serverseitig (siehe [ADR-005](005-schema-driven-settings.md)) — Zod auf dem Client ist Second-Gate gegen manuelle `window.JumpnrunConfig`-Manipulation und Defense-in-Depth.

## Konsequenzen

**Positiv:**

- Ein Schema liefert gleichzeitig Runtime-Validierung und TypeScript-Types (`z.infer<typeof GameConfigSchema>`)
- Defaults sind am Feld definiert, nicht in einem separaten Object — weniger Drift
- Parse-Fehler zeigen das problematische Feld mit Pfad

**Negativ:**

- Zod addiert ~15 KB gzipped zum Bundle — akzeptabel fuer die Sicherheit
- Schema muss parallel zu `ConfigSchema.php` (PHP-Seite) gepflegt werden. Einmalige Kosten pro neuem Feld, ADR-005 beschreibt warum kein Build-Step zwischen beiden eingerichtet wurde.

## Verification

`GameConfigSchema.parse({})` liefert alle Defaults. `bun run test` prueft dass der Schema-Parse bei realistischen Configs durchlaeuft.
