import { ApiClient, type ApiConfig, type ScoreResult } from './api-client.ts';
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
  /** REST-API für Session-Start und Score-Submit. Fehlt wenn Plugin standalone läuft. */
  api?: ApiConfig;
  /** Show collision hitboxes + sprite masks on load. Toggle at runtime with `D`. */
  debug?: boolean;
}

// ── Minimal DOM UI (created programmatically, no PHP template required) ───

interface GameOverHandlers {
  /** Submittet den Score mit dem eingegebenen Namen. */
  onSave: (name: string) => Promise<ScoreResult | null>;
  /** User waehlt "Weiter spielen" ohne zu speichern. */
  onSkip: () => void;
  /** Nach dem Saved-Screen "Nochmal spielen". */
  onRestart: () => void;
  /** Optional: vorgeschlagener Name (z.B. aus localStorage). */
  suggestedName?: string;
}

const LAST_NAME_KEY = 'jumpnrun:lastName';

class GameUI {
  readonly canvas: HTMLCanvasElement;

  private readonly startOverlay: HTMLElement;
  private readonly startBtn: HTMLElement;
  private readonly gameOverOverlay: HTMLElement;

  private readonly discountPopup: HTMLElement;
  private readonly discountCodeEl: HTMLElement;
  private readonly closeDiscountBtn: HTMLElement;

  constructor(root: HTMLElement, width: number, height: number) {
    const wrap = el('div', 'jnr-wrap');
    root.appendChild(wrap);

    this.canvas = document.createElement('canvas');
    this.canvas.width = width;
    this.canvas.height = height;
    wrap.appendChild(this.canvas);

    // ── Start overlay (kein Name — der kommt erst beim Speichern) ─────────
    this.startOverlay = overlay(wrap, 'jnr-start');
    this.startBtn = el('button', 'jnr-btn', 'Spiel starten');
    this.startOverlay.append(
      el('h2', 'jnr-title', 'Jump & Run'),
      el('p', 'jnr-subtitle', 'Leertaste · Klick · Touch — Doppelsprung möglich'),
      divider(),
      this.startBtn,
    );

    // ── Game-over overlay (Inhalt je Screen dynamisch) ────────────────────
    this.gameOverOverlay = overlay(wrap, 'jnr-gameover jnr-hidden');

    // ── Discount popup ────────────────────────────────────────────────────
    this.discountPopup = overlay(wrap, 'jnr-discount jnr-hidden');
    this.discountCodeEl = el('span', 'jnr-code', '');
    this.closeDiscountBtn = el('button', 'jnr-btn', 'Weiterspielen');
    this.discountPopup.append(
      el('h2', 'jnr-title', 'Bonus freigeschaltet!'),
      el('p', 'jnr-subtitle', 'Dein persönlicher Rabattcode:'),
      this.discountCodeEl,
      el('p', 'jnr-subtitle', 'Code kopieren und beim Checkout einlösen.'),
      this.closeDiscountBtn,
    );
  }

  showStart(onStart: () => void): void {
    this.startOverlay.classList.remove('jnr-hidden');
    this.startBtn.onclick = () => {
      this.startOverlay.classList.add('jnr-hidden');
      onStart();
    };
  }

  /**
   * Komplette Game-Over-Sequenz: zeigt Score, Auswahl speichern/weiter,
   * (optional) Name-Eingabe, Ergebnis. Ruecksprung zwischen Name-Input und
   * Auswahl ist eingebaut. Client liefert nur 3 Callbacks.
   */
  showGameOver(score: number, handlers: GameOverHandlers): void {
    const suggested = handlers.suggestedName ?? readLastName();
    this.renderGameOverInitial(score, handlers, suggested);
  }

  hideGameOver(): void {
    this.gameOverOverlay.classList.add('jnr-hidden');
  }

  showDiscount(code: string, onClose: () => void): void {
    this.discountCodeEl.textContent = code;
    this.discountPopup.classList.remove('jnr-hidden');
    this.closeDiscountBtn.onclick = () => {
      this.discountPopup.classList.add('jnr-hidden');
      onClose();
    };
  }

  private renderGameOverInitial(
    score: number,
    handlers: GameOverHandlers,
    suggestedName: string,
  ): void {
    const title = score > 20 ? 'Stark!' : score > 10 ? 'Gut gespielt!' : 'Game Over';

    const scoreGroup = el('div', 'jnr-score-group');
    scoreGroup.append(
      el('p', 'jnr-score-label', 'Dein Score'),
      el('p', 'jnr-score-display', String(score)),
    );

    const saveBtn = el('button', 'jnr-btn', 'In Highscore speichern');
    saveBtn.onclick = () => this.renderNameInput(score, handlers, suggestedName);

    const skipBtn = el('button', 'jnr-btn jnr-btn-secondary', 'Weiter spielen');
    skipBtn.onclick = () => handlers.onSkip();

    this.render(el('h2', 'jnr-title', title), scoreGroup, divider(), saveBtn, skipBtn);
  }

  private renderNameInput(score: number, handlers: GameOverHandlers, defaultName: string): void {
    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = 15;
    input.placeholder = 'Dein Name';
    input.className = 'jnr-input';
    input.autocomplete = 'off';
    input.value = defaultName;

    const submitBtn = el('button', 'jnr-btn', 'Speichern') as HTMLButtonElement;
    const backBtn = el('button', 'jnr-btn jnr-btn-secondary', 'Zurück');

    const submit = async (): Promise<void> => {
      const name = sanitizeName(input.value);
      if (name === '') {
        input.focus();
        return;
      }
      submitBtn.disabled = true;
      submitBtn.textContent = 'Wird gespeichert…';
      writeLastName(name);
      const result = await handlers.onSave(name);
      if (result) {
        this.renderSaved(result, handlers);
      } else {
        handlers.onRestart();
      }
    };

    submitBtn.onclick = submit;
    backBtn.onclick = () => this.renderGameOverInitial(score, handlers, input.value);
    input.onkeydown = (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        void submit();
      }
    };

    this.render(
      el('h2', 'jnr-title', 'Dein Name?'),
      el('p', 'jnr-subtitle', `${score} Punkte in die Highscore-Liste`),
      divider(),
      input,
      submitBtn,
      backBtn,
    );
    setTimeout(() => input.focus(), 0);
  }

  private renderSaved(result: ScoreResult, handlers: GameOverHandlers): void {
    const titleText = result.personalBest ? 'Neuer Bestwert!' : 'Noch nicht dein Bestwert';

    const group = el('div', 'jnr-score-group');
    if (result.personalBest) {
      group.append(
        el('p', 'jnr-score-label', 'Dein Platz'),
        el('p', 'jnr-score-display', `#${result.rank}`),
      );
    } else {
      group.append(
        el('p', 'jnr-score-label', 'Dein Bestwert bleibt'),
        el('p', 'jnr-score-display', `${result.storedScore}`),
      );
    }

    const summary = result.personalBest
      ? el('p', 'jnr-subtitle', `${result.name} · ${result.submittedScore} Punkte`)
      : el(
          'p',
          'jnr-subtitle',
          `Diesmal ${result.submittedScore} Punkte — der alte Score bleibt oben in der Liste.`,
        );

    const restartBtn = el('button', 'jnr-btn', 'Nochmal spielen');
    restartBtn.onclick = handlers.onRestart;

    this.render(el('h2', 'jnr-title', titleText), group, summary, divider(), restartBtn);
  }

  private render(...children: HTMLElement[]): void {
    this.gameOverOverlay.replaceChildren(...children);
    this.gameOverOverlay.classList.remove('jnr-hidden');
  }
}

function sanitizeName(raw: string): string {
  return raw
    .replace(/[^a-zA-Z0-9 _-]/g, '')
    .trim()
    .slice(0, 15);
}

function readLastName(): string {
  try {
    return localStorage.getItem(LAST_NAME_KEY) ?? '';
  } catch {
    return '';
  }
}

function writeLastName(name: string): void {
  try {
    localStorage.setItem(LAST_NAME_KEY, name);
  } catch {
    // localStorage kann blockiert sein (Privacy-Mode) — ignorieren.
  }
}

// ── Bootstrap ─────────────────────────────────────────────────────────────

export function bootstrap(root: HTMLElement, rawConfig: JumpnrunConfig = {}): void {
  if (root.dataset.jnrBooted) return;
  root.dataset.jnrBooted = '1';

  const engineCfg = GameConfigSchema.parse({
    ...(rawConfig.width != null ? { canvasWidth: rawConfig.width } : {}),
    ...(rawConfig.height != null ? { canvasHeight: rawConfig.height } : {}),
    ...(rawConfig.discountCode != null ? { discountCode: rawConfig.discountCode } : {}),
  } satisfies Partial<GameConfig>);

  const ui = new GameUI(root, engineCfg.canvasWidth, engineCfg.canvasHeight);

  // Start loading sprites non-blocking — game shows start screen immediately.
  // The closure in the render loop captures the `images` variable by reference,
  // so reassigning it when the promise resolves is picked up on the next frame.
  const ctx = ui.canvas.getContext('2d');
  if (!ctx) {
    console.error('jumpnrun: canvas 2d context unavailable');
    return;
  }

  const renderer = new CanvasRenderer(ctx, engineCfg.canvasWidth, engineCfg.canvasHeight);
  renderer.debug = {
    enabled: rawConfig.debug === true,
    hitboxBuffer: engineCfg.hitboxBuffer,
    coinMagnet: engineCfg.coinMagnet,
  };
  const world = new GameWorld(engineCfg);

  let images: ImageMap = new Map();
  if (rawConfig.images) {
    loadImages(rawConfig.images).then(({ images: loaded, masks }) => {
      images = loaded;
      renderer.setMasks(masks);
      world.setMasks(masks);
    });
  }
  const loop = new GameLoop(
    (dt) => world.update(dt),
    () => renderer.render(world.state, images),
  );

  const apiClient = rawConfig.api ? new ApiClient(rawConfig.api) : null;
  let sessionId: string | null = null;
  let lastLevel = 1;

  // ── Engine events ──────────────────────────────────────────────────────
  world.events.on('score', ({ level }) => {
    lastLevel = level;
  });
  world.events.on('level-change', ({ level }) => {
    lastLevel = level;
  });

  const openSession = (): void => {
    if (apiClient) {
      apiClient.startSession().then((id) => {
        sessionId = id;
      });
    }
  };

  const startNewRun = (): void => {
    ui.hideGameOver();
    world.restart();
    lastLevel = 1;
    openSession();
    loop.start();
  };

  world.events.on('game-over', ({ score }) => {
    loop.stop();
    const savedSession = sessionId;
    const savedLevel = lastLevel;
    sessionId = null;

    ui.showGameOver(score, {
      onSave: async (name: string) => {
        if (!apiClient || !savedSession || score <= 0) {
          return null;
        }
        return apiClient.submitScore(savedSession, name, score, savedLevel);
      },
      onSkip: startNewRun,
      onRestart: startNewRun,
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
    } else if (e.code === 'KeyD') {
      renderer.debug = { ...renderer.debug, enabled: !renderer.debug.enabled };
      console.info(`[jumpnrun] Debug ${renderer.debug.enabled ? 'ON' : 'OFF'}`);
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
  ui.showStart(() => {
    openSession();
    world.start();
    loop.start();
  });
}

// ── Auto-init (WordPress integration) ────────────────────────────────────

declare global {
  interface Window {
    JumpnrunConfig?: JumpnrunConfig;
    Jumpnrun?: { bootstrap: (root: HTMLElement, config?: JumpnrunConfig) => void };
  }
}

function autoInit(): void {
  const root = document.getElementById('jumpnrun-root');
  if (root && window.JumpnrunConfig) {
    bootstrap(root, window.JumpnrunConfig);
  }
}

window.Jumpnrun = { bootstrap };

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

function divider(): HTMLElement {
  const hr = document.createElement('hr');
  hr.className = 'jnr-divider';
  return hr;
}
