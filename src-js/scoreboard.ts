import type { ApiClient, HighscoreEntry } from './api-client.ts';

/** Steuert Sichtbarkeit und Laenge der Top-Liste neben dem Canvas. */
export interface ScoreboardConfig {
  enabled: boolean;
  limit: number;
}

/**
 * Scoreboard-Panel: baut seine eigene DOM auf und liefert sie ueber `element`.
 * Wird im Game-Over-Overlay als rechte Spalte eingehaengt und nach jedem Save
 * refreshed. Optionales `highlightName` hebt den eigenen Eintrag hervor.
 */
export class Scoreboard {
  readonly element: HTMLElement;
  private readonly listEl: HTMLOListElement;
  private readonly emptyEl: HTMLElement;
  private readonly api: ApiClient;
  private readonly limit: number;

  constructor(api: ApiClient, limit: number) {
    this.api = api;
    this.limit = Math.max(3, Math.min(limit, 25));

    this.element = document.createElement('aside');
    this.element.className = 'jnr-sidebar';

    const title = document.createElement('h3');
    title.className = 'jnr-sidebar-title';
    title.textContent = `Top ${this.limit}`;

    this.listEl = document.createElement('ol');
    this.listEl.className = 'jnr-toplist jnr-hidden';
    this.listEl.setAttribute('aria-live', 'polite');

    this.emptyEl = document.createElement('p');
    this.emptyEl.className = 'jnr-toplist-empty';
    this.emptyEl.textContent = 'Noch keine Highscores.';

    this.element.append(title, this.listEl, this.emptyEl);
  }

  /** Laed die aktuelle Liste via API und rendert sie neu, optional mit markiertem Eigennamen. */
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
