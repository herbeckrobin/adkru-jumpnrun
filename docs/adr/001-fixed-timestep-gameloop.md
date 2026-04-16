# ADR-001: Fixed-Timestep Game-Loop statt Frame-gebundener Physik

## Status

Akzeptiert — 2026-04-16

## Kontext

Der Legacy-Prototyp hat die Physik direkt pro Frame aktualisiert (`velocityY += 1.2`). Auf einem 120-Hz-Display laeuft die Gravitation damit doppelt so schnell wie auf 60 Hz — das Spiel ist de facto unspielbar auf modernen Displays. Dieser Bug wurde vom Kunden explizit gemeldet.

## Entscheidung

Wir implementieren einen Fixed-Timestep Game-Loop nach [Glenn Fiedlers "Fix Your Timestep"](https://gafferongames.com/post/fix_your_timestep/):

- Konstanter Physik-Step von 1/60 s (≈16.67 ms)
- Accumulator nimmt die reale Frame-Zeit auf und fuehrt ggf. mehrere Physik-Steps pro Render-Frame aus
- Max-Accumulator-Cap bei 250 ms gegen "Spiral of Death" nach Tab-Switches
- Physik-Werte in px/s und px/s², nicht px/frame
- Rendering mit interpolierter Sub-Frame-Position fuer fluessige Darstellung

## Konsequenzen

**Positiv:**
- Spielgeschwindigkeit deterministisch, unabhaengig von Display-Refresh-Rate
- Physik-Tests ohne Browser moeglich (DOM-frei, gegen Clock-Mock)
- Headless-Testing von FPS-Independence mit Playwright + CDP Virtual-Time

**Negativ:**
- Etwas mehr Komplexitaet in der Game-Loop
- Alle Physik-Werte muessen in px/s umgerechnet werden (einmalig)

## Verification

Testfall in `packages/engine/tests/game-loop.test.ts`: Gleicher Input-Stream bei 60/120/144 Hz → Spieler-Position nach 1000 fixed ticks identisch ±1 px.
