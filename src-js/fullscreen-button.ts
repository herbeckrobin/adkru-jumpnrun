/**
 * Vollbild-Toggle Button. Liegt visuell oben rechts im Game-Wrap.
 *
 * Strategie:
 *  - Erst native Fullscreen API versuchen (Desktop, Android Chrome, iPad iOS 26+)
 *  - Fallback: CSS-Pseudo-Fullscreen via Klasse `jnr-pseudo-fullscreen` —
 *    `position: fixed; inset: 0; height: 100dvh` deckt iOS Safari ab,
 *    wo `requestFullscreen` auf <div> nicht funktioniert.
 *  - Pseudo-Fullscreen wird per ESC oder zweitem Klick beendet.
 */

const PSEUDO_CLASS = 'jnr-pseudo-fullscreen';
const SCROLL_LOCK_CLASS = 'jnr-fullscreen-lock';

interface FullscreenButtonHandle {
  /** Entfernt Button + Listener — fuer Tests / Hot-Reload. */
  dispose(): void;
}

/**
 * Erzeugt den Button und haengt ihn ans `target`. `host` ist das Element
 * dessen Vollbild-Status getoggelt wird (typisch `ui.wrap`).
 */
export function attachFullscreenButton(
  target: HTMLElement,
  host: HTMLElement,
): FullscreenButtonHandle {
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'jnr-fullscreen-btn';
  btn.setAttribute('aria-label', 'Vollbild umschalten');
  btn.title = 'Vollbild umschalten';
  btn.innerHTML = svgIcon('expand');
  target.appendChild(btn);

  const setIcon = (kind: 'expand' | 'shrink'): void => {
    btn.innerHTML = svgIcon(kind);
  };

  const isPseudoActive = (): boolean => host.classList.contains(PSEUDO_CLASS);
  const isNativeActive = (): boolean => document.fullscreenElement === host;
  const isFullscreen = (): boolean => isNativeActive() || isPseudoActive();

  // Body- und html-Scroll-Lock — verhindert dass der Spieler hinter dem Spiel
  // scrollt oder Pull-to-Refresh triggert. Wird sowohl im Pseudo-Fullscreen
  // als auch im native Fullscreen gesetzt; der Browser entfernt im echten
  // Fullscreen zwar die Page-Scroll automatisch, aber bei Toolbar-Resize auf
  // iOS Safari ist die Klasse trotzdem hilfreich.
  const lockScroll = (): void => {
    document.documentElement.classList.add(SCROLL_LOCK_CLASS);
  };
  const unlockScroll = (): void => {
    document.documentElement.classList.remove(SCROLL_LOCK_CLASS);
  };

  const enterPseudo = (): void => {
    host.classList.add(PSEUDO_CLASS);
    lockScroll();
    setIcon('shrink');
  };
  const exitPseudo = (): void => {
    host.classList.remove(PSEUDO_CLASS);
    unlockScroll();
    setIcon('expand');
  };

  const onClick = async (): Promise<void> => {
    // ESC-Key beendet native Fullscreen automatisch — die fullscreenchange-
    // Listener unten halten unser Icon dann synchron.
    if (isNativeActive()) {
      await document.exitFullscreen();
      return;
    }
    if (isPseudoActive()) {
      exitPseudo();
      return;
    }
    if (typeof host.requestFullscreen === 'function') {
      try {
        await host.requestFullscreen();
        return;
      } catch {
        // requestFullscreen darf rejecten (z.B. user activation fehlt).
        // Pseudo-Fallback uebernimmt dann — der Wrap deckt trotzdem das
        // Viewport ab, wenn auch ohne ESC-Beendigung.
      }
    }
    enterPseudo();
  };

  btn.addEventListener('click', () => void onClick());

  const onFullscreenChange = (): void => {
    if (isNativeActive()) {
      lockScroll();
    } else if (!isPseudoActive()) {
      // Native Fullscreen verlassen (ESC oder API) — Lock nur entfernen
      // wenn auch kein Pseudo-Fullscreen mehr aktiv ist.
      unlockScroll();
    }
    setIcon(isFullscreen() ? 'shrink' : 'expand');
  };
  document.addEventListener('fullscreenchange', onFullscreenChange);

  // Pseudo-Fullscreen mit ESC verlassen — das nativeFullscreen wird vom
  // Browser bereits per ESC beendet. Wir spiegeln nur das Verhalten fuer
  // den Pseudo-Modus damit es konsistent ist.
  const onKey = (e: KeyboardEvent): void => {
    if (e.key === 'Escape' && isPseudoActive()) {
      exitPseudo();
    }
  };
  document.addEventListener('keydown', onKey);

  return {
    dispose(): void {
      btn.remove();
      document.removeEventListener('fullscreenchange', onFullscreenChange);
      document.removeEventListener('keydown', onKey);
      if (isPseudoActive()) exitPseudo();
      // Defensive: Lock auch entfernen wenn aus irgendeinem Grund noch aktiv
      // ist (z.B. nach Hot-Reload waehrend Fullscreen).
      unlockScroll();
    },
  };
}

function svgIcon(kind: 'expand' | 'shrink'): string {
  // Inline SVG damit kein Asset-Request noetig ist und der Button auch ohne
  // Sprite-Pool funktioniert. Pfeile zeigen Aktion: expand = nach aussen,
  // shrink = nach innen.
  if (kind === 'expand') {
    return `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M2 6V2h4M14 6V2h-4M2 10v4h4M14 10v4h-4" />
    </svg>`;
  }
  return `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M6 2v4H2M10 2v4h4M6 14v-4H2M10 14v-4h4" />
  </svg>`;
}
