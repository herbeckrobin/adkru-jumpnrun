/** REST-API-Wurzel und WP-Nonce — kommen aus dem GameShortcode als Bootstrap. */
export interface ApiConfig {
  root: string;
  nonce: string;
}

/** Antwort des /score-Endpoints mit Rank, personalBest und gespeichertem Score. */
export interface ScoreResult {
  rank: number;
  personalBest: boolean;
  previousBest: number | null;
  storedScore: number;
  submittedScore: number;
  name: string;
}

/** Ein einzelner Eintrag in der Top-Liste. */
export interface HighscoreEntry {
  id: number;
  name: string;
  score: number;
  level: number;
}

/** Duenner Wrapper um die Plugin-REST-API mit Nonce-Handling und Silent-Fail. */
export class ApiClient {
  constructor(private readonly config: ApiConfig) {}

  /** Legt eine neue Session an und liefert deren UUID zurueck. */
  async startSession(): Promise<string | null> {
    const res = await this.post<{ sessionId: string }>('session', {});
    return res?.sessionId ?? null;
  }

  /** Schliesst die Session und uebermittelt den finalen Score. */
  async submitScore(
    sessionId: string,
    name: string,
    score: number,
    level: number,
  ): Promise<ScoreResult | null> {
    return this.post<ScoreResult>('score', { sessionId, name, score, level });
  }

  /**
   * Prueft ob ein Name bereits in der Highscore-Liste existiert. Gibt den
   * gespeicherten Score zurueck oder null wenn der Name frei ist. Network-
   * Fehler werden als `null` behandelt — im Zweifel lieber submit erlauben
   * als User blockieren.
   */
  async lookupName(name: string): Promise<number | null> {
    try {
      const url = `${this.config.root}highscore/lookup?name=${encodeURIComponent(name)}`;
      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) return null;
      const body = (await res.json()) as { exists: boolean; score: number | null };
      return body.exists && typeof body.score === 'number' ? body.score : null;
    } catch (err) {
      console.warn('[jumpnrun] lookup failed', err);
      return null;
    }
  }

  /** Laed die aktuelle Top-Liste, bei Fehler leeres Array. */
  async getHighscores(limit = 10): Promise<HighscoreEntry[]> {
    try {
      const url = `${this.config.root}highscores?limit=${limit}`;
      const res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) return [];
      const body = (await res.json()) as { data?: HighscoreEntry[] };
      return body.data ?? [];
    } catch (err) {
      console.warn('[jumpnrun] highscores failed', err);
      return [];
    }
  }

  private async post<T>(path: string, body: unknown): Promise<T | null> {
    try {
      const res = await fetch(this.config.root + path, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': this.config.nonce,
        },
        body: JSON.stringify(body),
      });
      if (!res.ok) {
        console.warn(`[jumpnrun] ${path} → ${res.status}`);
        return null;
      }
      return (await res.json()) as T;
    } catch (err) {
      console.warn(`[jumpnrun] ${path} failed`, err);
      return null;
    }
  }
}
