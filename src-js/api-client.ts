export interface ApiConfig {
  root: string;
  nonce: string;
}

export interface ScoreResult {
  rank: number;
  personalBest: boolean;
  previousBest: number | null;
  storedScore: number;
  submittedScore: number;
  name: string;
}

export interface HighscoreEntry {
  id: number;
  name: string;
  score: number;
  level: number;
}

export class ApiClient {
  constructor(private readonly config: ApiConfig) {}

  async startSession(): Promise<string | null> {
    const res = await this.post<{ sessionId: string }>('session', {});
    return res?.sessionId ?? null;
  }

  async submitScore(
    sessionId: string,
    name: string,
    score: number,
    level: number,
  ): Promise<ScoreResult | null> {
    return this.post<ScoreResult>('score', { sessionId, name, score, level });
  }

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
