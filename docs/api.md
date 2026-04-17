# REST-API — Jump-n-Run

Base-URL: `https://<deine-seite>/wp-json/jumpnrun/v1/`

Alle Endpoints sind **oeffentlich**. Die Sicherheit basiert auf IP-basiertem Rate-Limit und einer atomaren Session-State-Machine (siehe [ADR-004](adr/004-slim-anticheat-no-hmac.md)).

Der Client verwendet den Standard-WP-REST-Flow: `X-WP-Nonce`-Header + `credentials: 'same-origin'`. Third-Party-Integrationen koennen die Endpoints ohne Nonce rufen.

## `POST /session`

Startet eine neue Spielsitzung. Muss vor jedem Spiel aufgerufen werden.

**Request:**

```
POST /wp-json/jumpnrun/v1/session
Content-Type: application/json
{}
```

**Response 201:**

```json
{ "sessionId": "a8606891-e34b-425b-a21e-fba91dcf7e5f" }
```

**Response 429:**

Wenn die IP ueber dem Rate-Limit liegt (`rateLimitSessionPerMin`, Default 10/Minute).

```json
{ "code": "rate_limited", "message": "Zu viele Anfragen." }
```

---

## `POST /score`

Reicht den Score fuer eine laufende Session ein. Jede Session kann nur einmal eingereicht werden.

**Request:**

```
POST /wp-json/jumpnrun/v1/score
Content-Type: application/json
{
  "sessionId": "a8606891-e34b-425b-a21e-fba91dcf7e5f",
  "name": "robin",
  "score": 42,
  "level": 3
}
```

**Validation:**

- `sessionId`: UUIDv4
- `name`: String, wird server-seitig sanitized + auf `playerNameMaxLength` gekuerzt
- `score`: Integer 0-100000
- `level`: Integer 1-99

**Response 200:**

```json
{
  "rank": 3,
  "personalBest": true,
  "previousBest": 18,
  "storedScore": 42,
  "submittedScore": 42,
  "name": "robin"
}
```

- `storedScore` ist der gespeicherte Bestwert (kann groesser sein als `submittedScore`, wenn der User vorher einen besseren Lauf hatte)
- `rank` zaehlt den `storedScore` in der Top-Liste

**Response 409 `session_rejected`:**

Session unbekannt, bereits eingereicht oder Spielzeit unter `minSessionDurationSec`. Aus Anti-Cheat-Sicht ist das der gleiche Fehlerfall — der Client soll einen frischen Run starten.

**Response 429:** Rate-Limit (`rateLimitScorePerMin`, Default 30/Minute).

---

## `GET /highscores`

Liefert die Top N Eintraege.

**Request:**

```
GET /wp-json/jumpnrun/v1/highscores?limit=10
```

`limit` optional, Default 10, Maximum 100.

**Response 200:**

```json
{
  "data": [
    { "id": 1, "name": "robin", "score": 42, "level": 3 },
    { "id": 2, "name": "tomy",  "score": 38, "level": 3 },
    ...
  ]
}
```

---

## Fehler-Codes (Uebersicht)

| Status | Code | Wann |
|---|---|---|
| 400 | `rest_invalid_param` | Request-Body ungueltig (WP-Standard) |
| 409 | `session_rejected` | Session unbekannt, schon eingereicht oder zu schnell |
| 429 | `rate_limited` | IP ueber dem Minuten-Limit |

Alle anderen Fehler folgen dem WP-REST-Standard-Format.
