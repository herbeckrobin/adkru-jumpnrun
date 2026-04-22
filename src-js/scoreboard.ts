import type { ApiClient, HighscoreEntry } from './api-client.ts';

export interface ScoreboardConfig {
  enabled: boolean;
  limit: number;
}

/**
 * Scoreboard-Sidebar: rendert die Top-N Eintraege aus dem REST-Endpoint.
 * Wird beim Boot einmal gefetched und nach jedem eigenen Save refreshed.
 * Optionales `highlightName` hebt den frisch gespeicherten Eintrag visuell hervor.
 */
export class Scoreboard {
  private readonly listEl: HTMLOListElement;
  private readonly emptyEl: HTMLElement;
  private readonly api: ApiClient;
  private readonly limit: number;

  constructor(root: HTMLElement, api: ApiClient, limit: number) {
    this.api = api;
    this.limit = Math.max(3, Math.min(limit, 25));

    const list = root.querySelector<HTMLOListElement>('ol.jnr-toplist');
    const empty = root.querySelector<HTMLElement>('.jnr-toplist-empty');
    if (!list || !empty) {
      throw new Error('jumpnrun: scoreboard elements missing');
    }
    this.listEl = list;
    this.emptyEl = empty;
  }

  async refresh(highlightName?: string): Promise<void> {
    const entries = await this.api.getHighscores(this.limit);
    this.render(entries, highlightName);
  }

  private render(entries: HighscoreEntry[], highlightName?: string): void {
    this.listEl.replaceChildren();

    if (entries.length === 0) {
      this.emptyEl.classList.remove('jnr-hidden');
      this.listEl.classList.add('jnr-hidden');
      return;
    }

    this.emptyEl.classList.add('jnr-hidden');
    this.listEl.classList.remove('jnr-hidden');

    // Nur den ersten passenden Eintrag highlighten (gleiche Namen sind durch
    // Upsert eh zusammengefuehrt, aber safe is safe).
    let highlighted = false;

    for (const entry of entries) {
      const li = document.createElement('li');
      li.className = 'jnr-toplist-row';
      if (!highlighted && highlightName && entry.name === highlightName) {
        li.classList.add('jnr-toplist-row-me');
        highlighted = true;
      }

      const name = document.createElement('span');
      name.className = 'jnr-toplist-name';
      name.textContent = entry.name;

      const score = document.createElement('span');
      score.className = 'jnr-toplist-score';
      score.textContent = String(entry.score);

      li.append(name, score);
      this.listEl.append(li);
    }
  }
}
