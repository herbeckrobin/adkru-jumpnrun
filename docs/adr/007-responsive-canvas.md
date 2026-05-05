# ADR-007: Responsive Canvas via Transform-Matrix

## Status

Akzeptiert — 2026-05-05

## Kontext

Adcos Anforderung (Telefontermin 2026-05-04): Das Spiel muss auf allen Geraeten (Desktop, Tablet, Smartphone Landscape) sauber laufen — nichts darf abgeschnitten werden, kein Verzerren der Sprites bei Display-Aspect != 16:9. Bisheriger Stand: Canvas fest 960x540 Pixel, CSS `width: 100%; height: auto` skaliert zwar visuell, aber die intrinsischen Pixel bleiben 960x540 → Schrift unscharf auf Retina, und auf schmaleren Displays passt das Spielfeld nicht ins Viewport.

## Entscheidung

Engine bleibt in **logischen Koordinaten** (default 960x540 aus `ConfigSchema`), der **Renderer skaliert** auf physische Pixel via `ctx.setTransform()`.

- [src-js/renderer/canvas.ts](../../src-js/renderer/canvas.ts) bekommt `setSize(cssW, cssH, dpr?)` — passt `canvas.width/height` mit DevicePixelRatio an und berechnet contain-fit-Skalierung
- Pro Frame: erst physisches `clearRect` mit Identity-Transform (Letterbox-Bereiche werden schwarz), dann Game-Transform `scale(scale * dpr) + translate(offset)` setzen
- Engine-Code (`world.update`, `physics.ts` etc.) braucht **keine** Anpassung — alle Koordinaten bleiben logisch
- [src-js/client.ts](../../src-js/client.ts) installiert einen `ResizeObserver` auf den Wrap-Container; Wrap selbst hat `aspect-ratio` aus `engineCfg.canvasWidth/Height` als CSS-Custom-Property
- Aspect != Viewport → Letterboxing (schwarze Balken oben/unten oder links/rechts), kein Crop, keine Verzerrung

Verworfene Alternative: Engine-Koordinaten an Container koppeln (resize → world.canvasWidth aktualisieren). Waere "echtes" Skalieren, aber: Spawning-Logik, Hitbox-Buffer und Plattform-Geometrien sind in Pixel kalibriert. Skalierung per Transform ist algorithmisch einfacher und reproduzierbar.

## Konsequenzen

**Positiv:**

- Spiel laeuft ohne Logik-Aenderungen auf jeder Bildschirmgroesse
- Schrift und Sprites scharf auf Retina (DPR-aware)
- Engine-Tests bleiben in logischen Koordinaten gueltig

**Negativ:**

- Auf sehr breiten Displays (Ultrawide) entstehen breite Letterbox-Streifen — nicht vermeidbar ohne Aspect-Verzerrung. Akzeptabel, da Spiel-Design auf 16:9 ausgelegt ist.
- HUD-Elemente (Score, Level-Banner) skalieren mit — Schrift kann auf sehr kleinen Displays unleserlich werden. Mindestgroesse-Check (ADR-Phase Viewport Guard) faengt das ab.

## Verification

- `bun run typecheck && bun run lint && bun run test` gruen
- Manuell: Browser-Window resizen waehrend Spiel laeuft — kein Sprite-Verzerren, kein Crop, fluessige Skalierung
- Manuell: Auf iPhone Landscape oeffnen — Spielfeld fuellt Viewport mit Letterbox-Balken oben/unten falls Aspect != 16:9
