/**
 * Reaktiver Guard fuer Mobile-Pflichtkriterien (Querformat + Mindestgroesse).
 * Liegt visuell ueber dem Spiel und blockiert Pointer-Events solange ein
 * Kriterium nicht erfuellt ist. Bootstrap laeuft trotzdem sofort durch —
 * der Guard ist reine Anzeigeschicht, kein Async-Gate.
 *
 * Reagiert reaktiv auf `resize` und `orientationchange`. Bei Geraet-Drehung
 * waehrend laufendem Spiel taucht der Hinweis erneut auf.
 */

export interface ViewportConfig {
  /** Minimale Container-Breite in CSS-Pixeln. Default 568 = iPhone SE Landscape. */
  minWidth?: number;
  /** Minimale Container-Hoehe in CSS-Pixeln. Default 320. */
  minHeight?: number;
  /** Auf Touch-Geraeten Querformat erzwingen. Default true. */
  requireLandscape?: boolean;
}

const DEFAULT_MIN_WIDTH = 568;
const DEFAULT_MIN_HEIGHT = 320;

interface GuardElements {
  overlay: HTMLElement;
  title: HTMLElement;
  message: HTMLElement;
}

/**
 * Installiert den Guard und liefert eine `dispose()`-Funktion. Im
 * Normalbetrieb laeuft der Guard die gesamte Page-Lifetime mit —
 * verschwindet visuell sobald die Kriterien greifen, bleibt aber im DOM,
 * um bei Geraet-Drehung wieder einspringen zu koennen.
 */
export function installViewportGuard(root: HTMLElement, cfg: ViewportConfig): () => void {
  const minWidth = cfg.minWidth ?? DEFAULT_MIN_WIDTH;
  const minHeight = cfg.minHeight ?? DEFAULT_MIN_HEIGHT;
  const requireLandscape = cfg.requireLandscape ?? true;

  const els = createOverlay(root);

  const evaluate = (): void => {
    const reason = checkBlockReason({ minWidth, minHeight, requireLandscape });
    if (reason === null) {
      hide(els.overlay);
      tryLockLandscape();
      return;
    }
    showReason(els, reason);
  };

  evaluate();

  const onResize = (): void => evaluate();
  window.addEventListener('resize', onResize);
  window.addEventListener('orientationchange', onResize);
  // Bei manchen Browsern feuert `orientationchange` ohne `resize`, oder
  // umgekehrt — beide Events abonnieren ist die robusteste Loesung.

  return (): void => {
    window.removeEventListener('resize', onResize);
    window.removeEventListener('orientationchange', onResize);
    els.overlay.remove();
  };
}

interface BlockArgs {
  minWidth: number;
  minHeight: number;
  requireLandscape: boolean;
}

type BlockReason =
  | { kind: 'portrait' }
  | { kind: 'too-small'; minWidth: number; minHeight: number };

function checkBlockReason(cfg: BlockArgs): BlockReason | null {
  // Touch-Geraete im Hochformat: bevor wir die Groessenpruefung machen,
  // soll der Spieler erst drehen — sonst blinkt das Overlay zwischen zwei
  // Hinweisen wenn der Spieler dreht.
  if (cfg.requireLandscape && isTouchDevice() && isPortrait()) {
    return { kind: 'portrait' };
  }
  const w = window.innerWidth;
  const h = window.innerHeight;
  if (w < cfg.minWidth || h < cfg.minHeight) {
    return { kind: 'too-small', minWidth: cfg.minWidth, minHeight: cfg.minHeight };
  }
  return null;
}

function isTouchDevice(): boolean {
  // `matchMedia('(hover: none)')` plus Touch-Points ist die zuverlaessigste
  // Heuristik gegen Hybrid-Geraete (Surface Pro etc.) die Maus + Touch haben.
  const noHover = window.matchMedia?.('(hover: none) and (pointer: coarse)')?.matches ?? false;
  const hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
  return noHover && hasTouch;
}

function isPortrait(): boolean {
  return window.matchMedia?.('(orientation: portrait)')?.matches ?? false;
}

function tryLockLandscape(): void {
  // Funktioniert nur in Fullscreen + auf wenigen Browsern (Chrome Android,
  // Edge Android). iOS Safari ignoriert es. Silent fail ist OK — der
  // Hinweis-Overlay greift als Fallback.
  const orientation = screen.orientation as ScreenOrientation & {
    lock?: (orientation: 'landscape') => Promise<void>;
  };
  if (typeof orientation?.lock === 'function') {
    orientation.lock('landscape').catch(() => {
      // ignore
    });
  }
}

function createOverlay(root: HTMLElement): GuardElements {
  const overlay = document.createElement('div');
  overlay.className = 'jnr-viewport-guard jnr-hidden';

  const inner = document.createElement('div');
  inner.className = 'jnr-viewport-guard-inner';

  const icon = document.createElement('div');
  icon.className = 'jnr-viewport-guard-icon';
  icon.textContent = '↻'; // Rotate-Glyph als Default; per State austauschbar

  const title = document.createElement('h2');
  title.className = 'jnr-viewport-guard-title';

  const message = document.createElement('p');
  message.className = 'jnr-viewport-guard-message';

  inner.append(icon, title, message);
  overlay.appendChild(inner);
  root.appendChild(overlay);

  return { overlay, title, message };
}

function showReason(els: GuardElements, reason: BlockReason): void {
  if (reason.kind === 'portrait') {
    els.title.textContent = 'Bitte ins Querformat drehen';
    els.message.textContent = 'Das Spiel läuft auf dem Handy nur im Querformat.';
  } else {
    els.title.textContent = 'Gerät zu klein';
    els.message.textContent = `Das Spielfeld braucht mindestens ${reason.minWidth} × ${reason.minHeight} Pixel.`;
  }
  els.overlay.classList.remove('jnr-hidden');
}

function hide(overlay: HTMLElement): void {
  overlay.classList.add('jnr-hidden');
}
