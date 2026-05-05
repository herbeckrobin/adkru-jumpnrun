# ADR-008: Asset-Preloader mit Progress-Anzeige

## Status

Akzeptiert — 2026-05-05

## Kontext

Adcos Anforderung (Telefontermin 2026-05-04): Hintergrundbilder duerfen sehr gross sein (z.B. 1000 × 12000 Pixel). Bei dieser Groesse dauert das Laden mehrere Sekunden. Bisher startete das Spiel sofort und ein leeres Sky-Gradient-Fallback war sichtbar bis Bilder asynchron eintrafen — schlechter erster Eindruck. Daher: vor Spielstart sichtbarer Loading-State + Browser soll moeglichst frueh anfangen zu laden.

## Entscheidung

Zwei Mechanismen kombiniert:

1. **`<link rel="preload" as="image">` Tags im `<head>`** — [src-php/Shortcode/GameShortcode.php](../../src-php/Shortcode/GameShortcode.php) `maybePreloadHeroAssets()` haengt sich an `wp_head` und gibt Tags fuer max. 3 Level-1-Hintergruende aus. Browser laedt sie parallel zum JS-Bundle, bevor `bootstrap()` ueberhaupt laeuft.

2. **Loading-Overlay mit Progress-Bar** — [src-js/loading-screen.ts](../../src-js/loading-screen.ts) erstellt ein Overlay ueber dem Wrap. [src-js/renderer/assets.ts](../../src-js/renderer/assets.ts) `loadImages` bekommt einen optionalen `onProgress(loaded, total)` Callback der nach jedem Asset (success ODER failure via `Promise.allSettled` + `finally`) feuert. Loading-Screen versteckt sich automatisch bei `loaded === total` mit kurzem Fade-Out.

Pre-Loader laeuft sofort beim DOM-Ready im `bootstrap()` — der Spieler kann den Start-Button erst klicken wenn das Loading-Overlay weg ist (Overlay blockt Pointer-Events).

Verworfene Alternativen:
- Service Worker fuer Asset-Caching: zu viel Infrastruktur fuer ein WordPress-Plugin
- Sprite-Atlas (alle Bilder in einer Datei): starres Asset-Pool-Konzept des Plugins erlaubt das nicht (Tomy weist Bilder per CPT zu)

## Konsequenzen

**Positiv:**

- Spieler sieht sofort einen visuellen Indikator dass etwas passiert
- `<link rel="preload">` startet Asset-Download bevor JS geparst ist — verkuerzt Time-to-Interactive messbar
- Progress-Bar zeigt auch bei kaputten Assets bis 100% (Promise-Loop nutzt `finally`)

**Negativ:**

- Maximal 3 Preload-Hints konkurrieren um Bandbreite mit anderen kritischen Ressourcen — bewusst limitiert
- Preload-Tags ignorieren CPT-Pool-Weights (immer die ersten 3 in DB-Reihenfolge). Akzeptabel weil Level 1 typisch nur 1-2 Hintergruende hat.
- Loading-Screen blockt Spielstart auch bei sehr schnellen Loads — minimaler "Flash" ist OK weil der Fade-Out 280ms dauert.

## Verification

- `bun run typecheck && bun run lint && bun run test` gruen
- Manuell: DevTools → Network → Throttling "Slow 3G" → Loading-Screen zeigt Progress-Bar die sichtbar zaehlt
- Manuell: DevTools → Network → "Disable cache" + Hard-Reload → `<link rel="preload">` Requests starten vor JS-Bundle (siehe Initiator-Spalte)
