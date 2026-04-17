# Tuning-Guide — Das Spiel fuer einen anderen Kunden anpassen

Das Plugin ist als White-Label-Basis gebaut. Fuer die meisten Kundenprojekte reichen Aenderungen im WordPress-Admin — kein Code-Touch noetig. Nur bei Sprite-Wechseln oder sehr spezifischen Wuenschen wird eine kleine Custom-Version gebraucht.

## Was ohne Code geht

Komplette Konfiguration ueber **WP-Admin → Jump-n-Run → Einstellungen**. Geaenderte Werte greifen beim naechsten Seitenladen.

### Branding

| Feld | Beispiel | Effekt |
|---|---|---|
| `discountCode` | `BACK20` | Der Rabattcode im Popup |
| `discountLevel` | `3` | Level, bei dem das Popup erscheint (1-10) |

### Gameplay-Gefuehl

| Feld | Was es tut | Tuning-Tipp |
|---|---|---|
| `gravity` | Wie stark der Spieler faellt | `1.2` fuer leichtes Floaten, `2.5` fuer stacheliges Gameplay |
| `jumpVelocity` | Sprungkraft (negativ = nach oben) | `-18` fuer kleinere Spruenge, `-26` fuer grosse |
| `maxJumps` | Double-Jump (2) oder Triple-Jump (3) | Fuer jungere Zielgruppen `3` oder `4` |
| `baseSpeed` | Wie schnell die Welt scrollt | `3` leicht, `5` normal, `8` crazy |
| `speedPerLevel` | Wie viel schneller's pro Level wird | `0.3` sanft, `0.8` fordernd |
| `coinsPerLevel` | Wie viele Coins bis zum Level-Up | `5` flott, `10` gemuetlich |

### Hitbox-Toleranz

| Feld | Was es tut | Empfehlung |
|---|---|---|
| `hitboxBuffer` | Obstacle-Hitbox in Pixeln schrumpfen | `10` freundlich, `0` pixelgenau, `-5` gnadenlos |
| `coinMagnet` | Coins werden frueher eingesammelt | `10` spielerfreundlich, `0` exakt |

### Canvas-Groesse

`canvasWidth` / `canvasHeight` in Pixeln. Das Plugin skaliert das Canvas responsive — realistisch sind `960×540` (Desktop-gerecht) bis `480×270` (Mobile-first).

### Anti-Cheat

| Feld | Default | Wann aendern |
|---|---|---|
| `rateLimitSessionPerMin` | `10` | Hochdrehen bei starkem Event-Traffic (Messe, TV-Kampagne) |
| `minSessionDurationSec` | `5` | Runter auf `2` wenn das Spiel schneller endet als geplant |

### Raw-JSON-Override (Tab "Erweitert")

Fuer Werte die nicht im UI sind oder zum Schnell-Tuning ohne Tab-Wechsel. Beispiel:

```json
{ "gravity": 1.5, "hitboxBuffer": 8, "discountLevel": 4 }
```

Wird auf die Admin-Settings draufgemergt — spaetere Values gewinnen.

## Beispiel: Aus "Burner King" wird "Baeckerei Mueller"

Kunde moechte einen Oster-Event mit:

- Rabattcode: `OSTER20`
- Code erscheint bei Level 2 (kuerzeres Engagement)
- Freundlicheres Gameplay (keine Frustration fuer Gelegenheitsspieler)

Schritte im Admin:

1. **Branding** → `discountCode` auf `OSTER20`, `discountLevel` auf `2`
2. **Physik** → `gravity` von `1.8` auf `1.5` (weniger "steil")
3. **Welt** → `baseSpeed` von `5` auf `4`, `coinsPerLevel` von `5` auf `7`
4. **Hitbox** → `hitboxBuffer` von `10` auf `15` (grosszuegiger gegen Spieler)
5. Speichern → Shortcode-Seite F5 → sofort aktiv

Aufwand: ~5 Minuten. Keine Code-Deployment, keine Build, kein Test-Cycle.

## Was braucht Code

- **Andere Sprites** (neuer Player, andere Hindernisse, neue Hintergruende): PNGs in [assets/sprites/](../assets/sprites/) austauschen. Die Dateinamen sind fest — siehe [GameShortcode::spriteMap()](../src-php/Shortcode/GameShortcode.php).
- **Neue Gameplay-Mechaniken** (Power-Ups, andere Obstacle-Typen): Engine-Arbeit in [src-js/engine/](../src-js/engine/).
- **Andere Rabatt-Logik** (z.B. dynamische Codes aus einer externen API): Custom Shortcode-Klasse oder `rest_pre_dispatch` Hook.

## Tipp fuer den Design-Prozess

Werte zuerst in "Erweitert" → `configRawJson` eintragen, spielen, iterieren. Erst wenn das Tuning stimmt, die Werte in die passenden Tabs uebertragen und das Raw-JSON leeren.
