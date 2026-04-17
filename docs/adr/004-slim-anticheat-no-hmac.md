# ADR-004: Slim Anti-Cheat ohne HMAC und Heartbeat

## Status

Akzeptiert — 2026-04-17

## Kontext

Der urspruengliche Plan sah fuer den Anti-Cheat eine HMAC-signierte Session-Token-Kette mit Heartbeat-Endpoint, Plausibilitaetspruefung (max. Score/s, Level-Konsistenz) und Rate-Limit vor. Zielsetzung: Ein Angreifer der direkt gegen `/score` posted, soll nichts durchkriegen.

Der Use-Case des Plugins ist Marketing-Gameplay auf Kundenseiten mit moderatem Traffic (kein Competitive-Leaderboard). Ein manipulierter Highscore fuehrt dort zu peinlichem "HACKER2000 · 9999 Punkte" im Scoreboard — aergerlich, aber weder finanziell noch rechtlich kritisch.

Full-Anti-Cheat bedeutet:

- HMAC-Sign/Verify mit server-side Secret in jedem Request
- Heartbeat-Endpoint alle paar Sekunden mit incrementellen Score-Updates
- Serverseitige Plausibilitaetslogik (max. Score/Zeit, Level-Grenzen)
- Ca. 2-3 zusaetzliche Stunden Implementierung + aequivalent Testen

## Entscheidung

Wir verzichten auf HMAC und Heartbeat. Stattdessen:

- **UUIDv4** als Session-ID, erstellt in `POST /session` (DB-Row `state='active'`)
- **Atomarer State-Uebergang** in `POST /score`: `UPDATE ... SET state='submitted' WHERE id=? AND state='active' AND started_at <= NOW() - INTERVAL X SECOND` — prueft Doppel-Submit und Mindest-Spielzeit in einer Query
- **Rate-Limit** pro IP via WP-Transients (N/min) auf `/session` und `/score`
- **Sanitizer** auf dem Namen + Prepared Statements im Repository

Das schuetzt vor dem realistischen Angriffsbild: Script-Kiddies mit `fetch()`-Experimenten, Mehrfach-Submits und DoS-Versuche. Ein dediziert manipulierender Angreifer mit Browser-Debugger kommt durch — der ist aber nicht Teil der Bedrohungsmodelle fuer ein Marketing-Game.

## Konsequenzen

**Positiv:**

- Implementierung bleibt lesbar (4 Klassen statt 8)
- Kein Heartbeat-Overhead im Client und im Server
- Rate-Limit + State-Machine deckt die realistischen Missbrauchs-Vektoren ab
- Der Sprung zu Full-Anti-Cheat ist spaeter moeglich ohne Breaking Changes an der REST-API

**Negativ:**

- Ein motivierter Angreifer mit DevTools kann die `sessionId` abfangen und eine manipulierte `score`-POST senden. Mitigation: Moderation via Admin-Scoreboard (Einzel-/Bulk-Delete).
- Keine Score-Monotonie-Pruefung serverseitig

## Verification

Test-Case `testRateLimitRejects` prueft dass nach N+1 Requests eine 429 kommt. Manueller Test in DevTools: Zweites `POST /score` mit gleicher `sessionId` → 409 "session_rejected".
