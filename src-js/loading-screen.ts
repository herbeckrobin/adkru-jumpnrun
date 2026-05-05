/**
 * Loading-Overlay mit Progress-Bar fuer den Asset-Preloader.
 * Liegt ueber dem Spiel waehrend Sprites laden — verschwindet sobald
 * `done()` gerufen wird oder die Progress-Bar 100% erreicht hat.
 */

interface LoadingScreenHandle {
  /** Setzt den Progress (loaded/total). Bei loaded === total wird automatisch ausgeblendet. */
  update(loaded: number, total: number): void;
  /** Sofort schliessen + DOM-Eintrag entfernen. */
  done(): void;
}

/**
 * Erstellt das Loading-Overlay als Kind von `host`. Style-Klassen leben in
 * client.css — der Wrapper-Container muss `position: relative` haben damit
 * sich das Overlay korrekt ueberlagert (gilt fuer .jnr-wrap).
 */
export function createLoadingScreen(host: HTMLElement): LoadingScreenHandle {
  const overlay = document.createElement('div');
  overlay.className = 'jnr-loading';

  const inner = document.createElement('div');
  inner.className = 'jnr-loading-inner';

  const label = document.createElement('p');
  label.className = 'jnr-loading-label';
  label.textContent = 'Lädt …';

  const barOuter = document.createElement('div');
  barOuter.className = 'jnr-loading-bar';
  const barFill = document.createElement('div');
  barFill.className = 'jnr-loading-bar-fill';
  barOuter.appendChild(barFill);

  const percent = document.createElement('p');
  percent.className = 'jnr-loading-percent';
  percent.textContent = '0 %';

  inner.append(label, barOuter, percent);
  overlay.appendChild(inner);
  host.appendChild(overlay);

  let removed = false;
  const remove = (): void => {
    if (removed) return;
    removed = true;
    // Kurzes Fade-Out via CSS-Klasse, dann aus DOM entfernen.
    overlay.classList.add('jnr-loading-fade');
    setTimeout(() => overlay.remove(), 280);
  };

  return {
    update(loaded: number, total: number): void {
      if (removed) return;
      // Gegen Division-by-zero (0 Assets ist Edge-Case fuer leere Asset-Map).
      const ratio = total > 0 ? loaded / total : 1;
      const pct = Math.round(ratio * 100);
      barFill.style.width = `${pct}%`;
      percent.textContent = `${pct} %`;
      if (loaded >= total) {
        remove();
      }
    },
    done(): void {
      remove();
    },
  };
}
