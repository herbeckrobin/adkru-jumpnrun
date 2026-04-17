# ADR-005: Schema-driven Admin-Settings

## Status

Akzeptiert — 2026-04-17

## Kontext

Alle im Spiel relevanten Tuning-Werte (Physik, Hitbox, Spawning, Branding, Anti-Cheat) sollen ueber die WordPress-Admin-Oberflaeche editierbar sein, **ohne** dass fuer jedes neue Feld eine neue Settings-API-Registrierung, ein neues Input-Template und ein neues Sanitize-Callback geschrieben werden muss. Sonst kippen Entwickler bei jedem neuen Tuning-Wert in manuelle Arbeit — und die Admin-Page verrottet.

Nach Robins Feedback (17.04.2026): "Die Settings sollen wirklich **alle** In-Game-Werte ueberschreiben koennen, nicht nur das Branding."

## Entscheidung

Eine zentrale **Single-Source-of-Truth** in [src-php/Config/ConfigSchema.php](../../src-php/Config/ConfigSchema.php) deklariert pro Feld: Typ (`int`/`float`/`string`/`bool`/`url`/`textarea`), Default, Min/Max/Step, Label und optional Help-Text, gruppiert nach Sektion.

- `ConfigService::getConfig()` merged **Schema-Defaults** < **wp_options-Override** < **configRawJson-Override** (spaetere gewinnen)
- `SettingsPage::render()` iteriert ueber das Schema und rendert pro Feldtyp das passende HTML-Input
- `SettingsPage::sanitize()` clampt jeden Wert via `ConfigSchema::sanitizeValue()`
- `GameShortcode` reicht die komplette `engineConfig()` als JSON an den Client
- Der Client parsed via Zod (Second-Gate, siehe [ADR-003](003-zod-for-engine-config.md))

Neue Felder: Eintrag in `ConfigSchema.php` + Eintrag im Zod-Schema → UI, Validation, Merge passen sich automatisch an.

## Konsequenzen

**Positiv:**

- Neue Tuning-Werte kosten ~10 Zeilen Code pro Seite (PHP + TS) statt ~50
- Admin-UI ist konsistent: gleiches Input-Rendering fuer alle Felder desselben Typs
- Range-Clamping passiert zentral — kein "vergessener Sanitize" mehr
- Die Tabs (Canvas, Physik, Hitbox, ...) folgen der Struktur des Schemas, nicht einer manuellen Sort-Order

**Negativ:**

- `ConfigSchema.php` und `src-js/engine/config/index.ts` muessen manuell synchron gehalten werden (Keys, Defaults, Ranges). Ein Build-Step der PHP aus TS generiert (oder umgekehrt) wurde verworfen — zu viel Infrastruktur fuer zwei ca. 80-Zeilen-Files. Bei Abweichung laufen beide Schemas getrennt: PHP clampt beim Speichern, Zod clampt beim Parsen. Stiller Drift wird dadurch nicht catastrophisch.
- Komplexere Feldtypen (Arrays, verschachtelte Objekte) brauchen im Schema eigene Spezifikation — bislang nicht noetig.

## Verification

- `composer test` prueft in `ConfigSchemaTest::testNoDuplicateFieldKeysAcrossSections` dass kein Feld-Key doppelt vorkommt.
- `ConfigServiceTest::testOptionValuesAreClamped` prueft dass Out-of-Range-Werte auf die Max-Grenze geklammert werden.
- Manueller Test: `gravity` im Admin auf 5.0 setzen → Frontend F5 → Spieler faellt sichtbar schneller.
