import './client.css';
import type { GameConfig } from './engine/index.ts';
import { GameConfigSchema, GameLoop, GameWorld } from './engine/index.ts';
import type { ImageMap } from './renderer/index.ts';
import { CanvasRenderer, loadImages } from './renderer/index.ts';

// ── Public config interface (set via window.JumpnrunConfig in WP) ─────────

export interface JumpnrunConfig {
  /** Key → absolute URL for each sprite. Missing keys fall back to solid colors. */
  images?: Record<string, string>;
  width?: number;
  height?: number;
  discountCode?: string;
}

// ── Minimal DOM UI (created programmatically, no PHP template required) ───

class GameUI {
  readonly canvas: HTMLCanvasElement;

  private readonly startOverlay: HTMLElement;
  private readonly nameInput: HTMLInputElement;
  private readonly startBtn: HTMLElement;

  private readonly gameOverOverlay: HTMLElement;
  private readonly scoreDisplay: HTMLElement;
  private readonly restartBtn: HTMLElement;

  private readonly discountPopup: HTMLElement;
  private readonly discountCodeEl: HTMLElement;
  private readonly closeDiscountBtn: HTMLElement;

  constructor(root: HTMLElement, width: number, height: number) {
    // Wrapper keeps overlays positioned over the canvas
    const wrap = el('div', 'jnr-wrap');
    root.appendChild(wrap);

    this.canvas = document.createElement('canvas');
    this.canvas.width = width;
    this.canvas.height = height;
    wrap.appendChild(this.canvas);

    // ── Start overlay ─────────────────────────────────────────────────────
    this.startOverlay = overlay(wrap, 'jnr-start');
    this.nameInput = document.createElement('input');
    this.nameInput.type = 'text';
    this.nameInput.maxLength = 15;
    this.nameInput.placeholder = 'Dein Name (max. 15 Zeichen)';
    this.nameInput.className = 'jnr-input';
    this.startBtn = el('button', 'jnr-btn', 'Spiel starten');
    this.startOverlay.append(
      el('h2', 'jnr-title', 'Jump & Run'),
      el('p', 'jnr-subtitle', 'Leertaste · Klick · Touch → Springen'),
      this.nameInput,
      this.startBtn,
    );

    // ── Game-over overlay ─────────────────────────────────────────────────
    this.gameOverOverlay = overlay(wrap, 'jnr-gameover jnr-hidden');
    this.scoreDisplay = el('p', 'jnr-score-display', '');
    this.restartBtn = el('button', 'jnr-btn', 'Nochmal spielen');
    this.gameOverOverlay.append(
      el('h2', 'jnr-title', 'Game Over'),
      this.scoreDisplay,
      this.restartBtn,
    );

    // ── Discount popup ────────────────────────────────────────────────────
    this.discountPopup = overlay(wrap, 'jnr-discount jnr-hidden');
    this.discountCodeEl = el('span', 'jnr-code', '');
    this.closeDiscountBtn = el('button', 'jnr-btn', 'Weiterspielen');
    this.discountPopup.append(
      el('h2', 'jnr-title', '🎉 Bonus-Level!'),
      el('p', 'jnr-subtitle', 'Dein Rabattcode:'),
      this.discountCodeEl,
      this.closeDiscountBtn,
    );
  }

  get playerName(): string {
    return this.nameInput.value
      .replace(/[^a-zA-Z0-9 ]/g, '')
      .trim()
      .slice(0, 15);
  }

  showStart(onStart: (name: string) => void): void {
    this.startOverlay.classList.remove('jnr-hidden');
    this.nameInput.focus();

    const go = (): void => {
      const name = this.playerName || 'Spieler';
      this.startOverlay.classList.add('jnr-hidden');
      onStart(name);
    };
    this.startBtn.onclick = go;
    this.nameInput.onkeydown = (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        go();
      }
    };
  }

  showGameOver(score: number, onRestart: () => void): void {
    this.scoreDisplay.textContent = `Score: ${score}`;
    this.gameOverOverlay.classList.remove('jnr-hidden');
    this.restartBtn.onclick = () => {
      this.gameOverOverlay.classList.add('jnr-hidden');
      onRestart();
    };
  }

  showDiscount(code: string, onClose: () => void): void {
    this.discountCodeEl.textContent = code;
    this.discountPopup.classList.remove('jnr-hidden');
    this.closeDiscountBtn.onclick = () => {
      this.discountPopup.classList.add('jnr-hidden');
      onClose();
    };
  }
}

// ── Bootstrap ─────────────────────────────────────────────────────────────

export async function bootstrap(root: HTMLElement, rawConfig: JumpnrunConfig = {}): Promise<void> {
  if (root.dataset.jnrBooted) return;
  root.dataset.jnrBooted = '1';

  const engineCfg = GameConfigSchema.parse({
    ...(rawConfig.width != null ? { canvasWidth: rawConfig.width } : {}),
    ...(rawConfig.height != null ? { canvasHeight: rawConfig.height } : {}),
    ...(rawConfig.discountCode != null ? { discountCode: rawConfig.discountCode } : {}),
  } satisfies Partial<GameConfig>);

  const ui = new GameUI(root, engineCfg.canvasWidth, engineCfg.canvasHeight);

  // Load sprites — non-blocking, game works fine with fallback colors
  const images: ImageMap = rawConfig.images ? await loadImages(rawConfig.images) : new Map();

  const ctx = ui.canvas.getContext('2d');
  if (!ctx) {
    console.error('jumpnrun: canvas 2d context unavailable');
    return;
  }

  const renderer = new CanvasRenderer(ctx, engineCfg.canvasWidth, engineCfg.canvasHeight);
  const world = new GameWorld(engineCfg);
  const loop = new GameLoop(
    (dt) => world.update(dt),
    () => renderer.render(world.state, images),
  );

  // ── Engine events ──────────────────────────────────────────────────────
  world.events.on('game-over', ({ score }) => {
    loop.stop();
    ui.showGameOver(score, () => {
      world.restart();
      loop.start();
    });
  });

  world.events.on('discount', ({ code }) => {
    ui.showDiscount(code, () => world.resume());
  });

  // ── Input ──────────────────────────────────────────────────────────────
  const jump = (): void => world.jump();

  document.addEventListener('keydown', (e) => {
    if (e.code === 'Space') {
      e.preventDefault();
      jump();
    }
  });
  ui.canvas.addEventListener('click', jump);
  ui.canvas.addEventListener(
    'touchstart',
    (e) => {
      e.preventDefault();
      jump();
    },
    { passive: false },
  );

  // ── Start screen ───────────────────────────────────────────────────────
  ui.showStart((_name) => {
    world.start();
    loop.start();
  });
}

// ── Auto-init (WordPress integration) ────────────────────────────────────

declare global {
  interface Window {
    JumpnrunConfig?: JumpnrunConfig;
    Jumpnrun?: { bootstrap: typeof bootstrap };
  }
}

function autoInit(): void {
  const root = document.getElementById('jumpnrun-root');
  if (root) {
    bootstrap(root, window.JumpnrunConfig ?? {}).catch(console.error);
  }
  window.Jumpnrun = { bootstrap };
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', autoInit);
} else {
  autoInit();
}

// ── DOM helpers ───────────────────────────────────────────────────────────

function el(tag: string, cls: string, text?: string): HTMLElement {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (text) e.textContent = text;
  return e;
}

function overlay(parent: HTMLElement, cls: string): HTMLElement {
  const e = el('div', `jnr-overlay ${cls}`);
  parent.appendChild(e);
  return e;
}
