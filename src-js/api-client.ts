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
