# ADR-009: Hintergrund-Tiling fuer variable Bildbreiten

## Status

Akzeptiert — 2026-05-05

## Kontext

Adcos Anforderung (Telefontermin 2026-05-04): Hintergrundbilder pro Level sollen unterschiedliche Breiten haben koennen — von schmalen Ornament-Bildern (200 px) bis zu langen Panorama-Hintergruenden (12000 px). Das Bild soll auf die Spielfeld-Hoehe gefittet werden (Aspect-preservierend) und horizontal **nahtlos kacheln** wenn das Spielfeld breiter als das skalierte Bild ist.

Bisheriger Stand: Renderer zeichnete genau zwei Kopien des Backgrounds nebeneinander (`drawImage(bg, x, 0, width, height)` zweimal), Engine resetete `backgroundX` bei `<= -canvasWidth`. Das funktionierte nur fuer Bilder die exakt eine Spielfeld-Breite hatten und sah bei kleineren oder groesseren Bildern kaputt aus (Hard-Cut, sichtbarer Sprung).

## Entscheidung

**Globaler Tiling-Modus** — alle Hintergruende werden gleich behandelt, kein Per-Background-Schema (Robins Vorgabe: "global immer").

Algorithmus in [src-js/renderer/canvas.ts](../../src-js/renderer/canvas.ts) `drawTiledBackground()`:

```ts
const scale = canvasHeight / bg.naturalHeight;
const drawW = bg.naturalWidth * scale;
const offset = ((scrollX % drawW) + drawW) % drawW; // positives Modulo
let x = -offset;
while (x < canvasWidth) {
  ctx.drawImage(bg, x, 0, drawW, canvasHeight);
  x += drawW;
}
```

Engine-Aenderung in [src-js/engine/world.ts](../../src-js/engine/world.ts): `backgroundX` sinkt jetzt **unbegrenzt**, kein Reset mehr. Engine kennt die Bildbreite nicht — der Renderer macht Modulo gegen die tatsaechlich skalierte Breite. Bei einem 12000 px Bild iteriert die while-Schleife meist nur 1-2 mal, bei einem 200 px Bild ~5x — beides performant.

Verworfene Alternativen:
- Per-Background `tilingMode: 'tile' | 'stretch'` Schema-Field: Robin verwarf das ("global immer"). Stretch wuerde das Spielfeld eh verzerren.
- `CanvasPattern` (`ctx.createPattern(bg, 'repeat-x')`): Browser-Unterstuetzung fuer fluessiges Scrollen ist gemischt, plus die Pattern-API skaliert nicht automatisch auf Spielfeld-Hoehe.

## Konsequenzen

**Positiv:**

- Beliebig grosse Hintergruende werden korrekt gerendert
- Nahtlos: Modulo-Mathematik garantiert keinen Hard-Cut
- Engine-Logik einfacher (Reset-Zeile entfernt)
- Bilder mit anderem Aspect als 16:9 werden via Height-Fit aspect-preservierend skaliert

**Negativ:**

- Sehr breite Bilder (> 8192 px) koennen auf Low-End-Mobile zu GPU-Memory-Druck fuehren — Browser kann Texturen ablehnen. Empfehlung im Admin-Doc: max 8192 px Breite. Eine Upload-Warnung wurde nicht implementiert (zukuenftiges Polish).
- Scrollgeschwindigkeit (`bgScrollBase + level * bgScrollPerLevel`) bezieht sich auf Pixel pro Frame im logischen Koordinatenraum — bei sehr breiten Bildern wirkt der Parallax langsamer (weil weniger Tile-Wiederholungen pro Spielzeit). Akzeptabel weil das visuell sogar gewollter "weiter Horizont"-Effekt ist.

## Verification

- `bun run test` gruen (Engine-Smoke-Test)
- Manuell: Test-Asset 200 × 540 als Background-CPT → sichtbares Tiling
- Manuell: Test-Asset 12000 × 540 → kachelt selten, scrollt fluessig
- Manuell: Bild mit Aspect != 16:9 (z.B. 800 × 400) → wird auf Spielfeld-Hoehe skaliert, Breite ergibt sich aus Aspect
