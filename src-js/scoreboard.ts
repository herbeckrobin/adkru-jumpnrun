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

  private readonly titleEl: HTMLElement;
  /** Aktuell sichtbare Anzahl — kann je nach Container-Hoehe < limit sein. */
  private visibleCount: number;

  constructor(api: ApiClient, limit: number) {
    this.api = api;
    this.limit = Math.max(3, Math.min(limit, 25));
    this.visibleCount = this.limit;

    this.element = document.createElement('aside');
    this.element.className = 'jnr-sidebar';

    this.titleEl = document.createElement('h3');
    this.titleEl.className = 'jnr-sidebar-title';
    this.titleEl.textContent = `Top ${this.limit}`;

    this.listEl = document.createElement('ol');
    this.listEl.className = 'jnr-toplist jnr-hidden';
    this.listEl.setAttribute('aria-live', 'polite');

    this.emptyEl = document.createElement('p');
    this.emptyEl.className = 'jnr-toplist-empty';
    this.emptyEl.textContent = 'Noch keine Highscores.';

    this.element.append(this.titleEl, this.listEl, this.emptyEl);

    // Sichtbare Anzahl an Container-Hoehe koppeln. Auf kleinen Geraeten
    // (Landscape Smartphone) waere Top 10 abgeschnitten — dann lieber nur
    // Top 3 oder 5 zeigen. Schwellen sind grob an die Pro-Eintrag-Hoehe
    // (~32px) angepasst, mit Puffer fuer Title und Padding.
    const updateVisibility = (): void => {
      const h = this.element.clientHeight;
      let next: number;
      if (h <= 0) next = this.limit;
      else if (h < 240) next = 3;
      else if (h < 400) next = 5;
      else next = this.limit;
      if (next !== this.visibleCount) {
        this.visibleCount = next;
        this.applyVisibility();
        this.titleEl.textContent = `Top ${next}`;
      }
    };
    updateVisibility();
    new ResizeObserver(updateVisibility).observe(this.element);
  }

  /**
   * Versteckt Listen-Eintraege jenseits der `visibleCount`-Grenze. Wir
   * rendern immer alle bekannten Eintraege ins DOM und verstecken nur per
   * CSS — verhindert Re-Render-Flicker beim Resize.
   */
  private applyVisibility(): void {
    const rows = Array.from(this.listEl.children) as HTMLElement[];
    rows.forEach((row, idx) => {
      row.classList.toggle('jnr-hidden', idx >= this.visibleCount);
    });
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

    // Sichtbarkeit nach Render anwenden — neue DOM-Elemente bekommen sonst
    // die `jnr-hidden`-Klasse erst beim naechsten Resize-Tick.
    this.applyVisibility();
  }
}
