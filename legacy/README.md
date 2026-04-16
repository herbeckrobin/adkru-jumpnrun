# Legacy — Original-Prototyp vom Kunden

**Nicht mehr ausfuehren.** Dieser Ordner ist Referenz fuer Assets und Tuning-Werte.

## Urspruenglicher Lieferzustand

Urspruengliches Repo: lokal, Commit `3375154 — init state vom kunde`.

**Technik:** Vanilla JavaScript, HTML5 Canvas, MAMP (PHP+MySQL), keine Build-Pipeline.

**Bekannte Probleme (fuehrten zum Rewrite):**

- FPS-gebundene Physik (`velocityY += 1.2` pro Frame) — doppelt so schnell auf 120-Hz-Displays
- 20+ hardcoded `http://localhost:8888` URLs in game.js und index.html
- SQL-Injection in [update_highscore.php](update_highscore.php) (String-Konkatenation)
- DB-Credentials `root:root` hardcoded in 3 Dateien
- Unlimitierte Multi-Jumps (nur doppelt gewollt)
- Canvas fix 800x600 Pixel
- Keine Anti-Cheat-Massnahmen (Client sendet beliebige Scores)

## Was aus dem Legacy uebernommen wird

- Bilder in [images/](images/) — Spraydose "Carry", 10 Backgrounds, 6 Obstacles, Coin, 2 Platforms
- Physik-Werte als Ausgangspunkt fuer `packages/engine/src/config/defaults.ts` (auf px/s-Einheiten umgerechnet)
- Rabattcode-Konzept (`BURNERKING20`, Level 3) als Default-Config
- `save_score.php`-Upsert-Pattern (`ON DUPLICATE KEY UPDATE GREATEST`) wandert in `HighscoreRepository`
