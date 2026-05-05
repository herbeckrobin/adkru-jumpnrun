import { ApiClient, type ApiConfig, type ScoreResult } from './api-client.ts';
import './client.css';
import type { BackgroundPool, GameConfig, ObstacleSpec, PlatformSpec } from './engine/index.ts';
import { GameConfigSchema, GameLoop, GameWorld } from './engine/index.ts';
import { attachFullscreenButton } from './fullscreen-button.ts';
import { createLoadingScreen } from './loading-screen.ts';
import type { ImageMap } from './renderer/index.ts';
import { CanvasRenderer, loadImages } from './renderer/index.ts';
import { Scoreboard, type ScoreboardConfig } from './scoreboard.ts';
import { installViewportGuard, type ViewportConfig } from './viewport-guard.ts';

// ── Public config interface (set via window.JumpnrunConfig in WP) ─────────

/** Aus den CPT-Pools eingelesene Assets — pro Kategorie eine Liste oder Map. */
export interface AssetPools {
  backgrounds: BackgroundPool;
  obstacles: readonly ObstacleSpec[];
  platforms: readonly PlatformSpec[];
}

/** Bootstrap-Konfiguration fuer das Plugin, kommt via window.JumpnrunConfig aus PHP. */
export interface JumpnrunConfig {
  /** Key → absolute URL for each sprite. Missing keys fall back to solid colors. */
  images?: Record<string, string>;
  /** Engine-Config (wird via Zod validiert, alle Felder optional). */
  engine?: Partial<GameConfig>;
  /** REST-API für Session-Start und Score-Submit. Fehlt wenn Plugin standalone läuft. */
  api?: ApiConfig;
  /** Sichtbarkeit + Laenge der Scoreboard-Sidebar neben dem Canvas. */
  scoreboard?: ScoreboardConfig;
  /** Asset-Pools aus dem WP-Admin (CPTs). Fehlt → Engine nutzt Default-Sprites. */
  assets?: AssetPools;
  /** Mindestgroesse + Landscape-Pflicht. Fehlt → Defaults aus viewport-guard. */
  viewport?: ViewportConfig;
  /** Show collision hitboxes + sprite masks on load. Toggle at runtime with `Shift+Ctrl+D`. */
  debug?: boolean;
}

// ── Minimal DOM UI (created programmatically, no PHP template required) ───

interface GameOverHandlers {
  /**
   * Pre-Flight: prueft ob der Name schon einen Score in der DB hat.
   * Optional — wenn nicht gesetzt, wird ohne Warnung direkt gespeichert.
   */
  onLookup?: (name: string) => Promise<number | null>;
  /**
   * Holt den vorausichtlichen Rang fuer den aktuellen Score (ohne zu
   * speichern). Optional — fehlt nur wenn keine API verfuegbar ist.
   */
  onPreviewRank?: () => Promise<{ rank: number; totalEntries: number } | null>;
  /** Submittet den Score mit dem eingegebenen Namen. */
  onSave: (name: string) => Promise<ScoreResult | null>;
  /** User waehlt "Nochmal spielen" ohne zu speichern (Restart). */
  onSkip: () => void;
  /** Nach dem Saved-Screen "Nochmal spielen". */
  onRestart: () => void;
  /** Optional: vorgeschlagener Name (z.B. aus localStorage). */
  suggestedName?: string;
}

const LAST_NAME_KEY = 'jumpnrun:lastName';

/** Baut das DOM-UI um den Canvas herum — Start-, Game-Over- und Discount-Overlays. */
class GameUI {
  readonly canvas: HTMLCanvasElement;
  readonly wrap: HTMLElement;

  private readonly startOverlay: HTMLElement;
  private readonly startBtn: HTMLElement;
  private readonly gameOverOverlay: HTMLElement;
  private readonly gameOverContent: HTMLElement;

  private readonly discountPopup: HTMLElement;
  private readonly discountCodeEl: HTMLElement;
  private readonly closeDiscountBtn: HTMLElement;
  private readonly discountCountdownEl: HTMLElement;

  constructor(root: HTMLElement, width: number, height: number) {
    const wrap = el('div', 'jnr-wrap');
    // Aspect-Ratio aus Engine-Konfiguration in CSS spiegeln — sonst nutzt
    // das Default 16/9. Tomy kann via Engine-Settings andere Spielfeld-Massen
    // konfigurieren ohne CSS anfassen zu muessen. Zwei separate Variablen
    // damit das CSS sie auch in calc() fuer den Hoehen-Constraint nutzen kann.
    wrap.style.setProperty('--jnr-aspect-w', String(width));
    wrap.style.setProperty('--jnr-aspect-h', String(height));
    root.appendChild(wrap);
    this.wrap = wrap;

    this.canvas = document.createElement('canvas');
    // Initiale Attribute — werden gleich von CanvasRenderer.setSize() ueber-
    // schrieben sobald wir die echte Container-Groesse aus dem Layout kennen.
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

    // ── Game-over overlay: linke Spalte = State-Inhalt, rechte Spalte
    // (optional) = Scoreboard. Der gameOverContent-Wrapper haelt die
    // State-Kinder damit wir sie per replaceChildren austauschen koennen,
    // ohne das Scoreboard daneben zu entfernen.
    this.gameOverOverlay = overlay(wrap, 'jnr-gameover jnr-hidden');
    this.gameOverContent = el('div', 'jnr-gameover-content');
    this.gameOverOverlay.appendChild(this.gameOverContent);

    // ── Discount popup ────────────────────────────────────────────────────
    this.discountPopup = overlay(wrap, 'jnr-discount jnr-hidden');
    this.discountCodeEl = el('span', 'jnr-code', '');
    this.closeDiscountBtn = el('button', 'jnr-btn', 'Weiterspielen');
    this.discountCountdownEl = el('p', 'jnr-countdown jnr-hidden', '');
    this.discountPopup.append(
      el('h2', 'jnr-title', 'Bonus freigeschaltet!'),
      el('p', 'jnr-subtitle', 'Dein persönlicher Rabattcode:'),
      this.discountCodeEl,
      el('p', 'jnr-subtitle', 'Code kopieren und beim Checkout einlösen.'),
      this.closeDiscountBtn,
      this.discountCountdownEl,
    );
  }

  /** Zeigt das Start-Overlay und ruft onStart beim Klick auf den Start-Button. */
  showStart(onStart: () => void): void {
    this.startOverlay.classList.remove('jnr-hidden');
    this.startBtn.onclick = () => {
      this.startOverlay.classList.add('jnr-hidden');
      onStart();
    };
  }

  /**
   * Haengt das Scoreboard als rechte Spalte ins Game-Over-Overlay. Wird nur
   * einmal beim Bootstrap aufgerufen; Inhalte werden vom Scoreboard selbst
   * refreshed.
   */
  attachScoreboard(element: HTMLElement): void {
    this.gameOverOverlay.appendChild(element);
    this.gameOverOverlay.classList.add('jnr-gameover-with-toplist');
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

  /** Versteckt das Game-Over-Overlay. */
  hideGameOver(): void {
    this.gameOverOverlay.classList.add('jnr-hidden');
  }

  /** Zeigt das Rabattcode-Popup und startet nach Bestaetigung den Countdown zurueck ins Spiel. */
  showDiscount(code: string, onClose: () => void): void {
    this.discountCodeEl.textContent = code;
    this.discountPopup.classList.remove('jnr-hidden');
    this.discountPopup.classList.remove('jnr-overlay-transparent');
    this.closeDiscountBtn.classList.remove('jnr-hidden');
    this.discountCountdownEl.classList.add('jnr-hidden');
    this.closeDiscountBtn.onclick = () => {
      this.closeDiscountBtn.classList.add('jnr-hidden');
      this.runDiscountCountdown(onClose);
    };
  }

  /**
   * 3-2-1-Los!-Sequenz nachdem der Spieler den Rabattcode bestaetigt hat.
   * Haelt das Spiel weiter pausiert (engine-status bleibt "paused"), bis der
   * Countdown durchgelaufen ist — sonst stirbt der Spieler sofort weil die
   * Hand noch nicht am Input ist.
   */
  private runDiscountCountdown(onClose: () => void): void {
    const steps = ['3', '2', '1', 'Los!'];
    const el = this.discountCountdownEl;
    el.classList.remove('jnr-hidden');
    // Dunklen Overlay-Hintergrund waehrend des Countdowns wegnehmen, damit der
    // Spieler seine Figur sieht und sich auf den Start vorbereiten kann.
    this.discountPopup.classList.add('jnr-overlay-transparent');
    let i = 0;
    const tick = (): void => {
      if (i >= steps.length) {
        this.discountPopup.classList.add('jnr-hidden');
        this.discountPopup.classList.remove('jnr-overlay-transparent');
        el.classList.add('jnr-hidden');
        onClose();
        return;
      }
      el.textContent = steps[i] ?? '';
      // Reflow erzwingen, damit CSS-Animation pro Schritt neu startet.
      el.classList.remove('jnr-countdown-pulse');
      void el.offsetWidth;
      el.classList.add('jnr-countdown-pulse');
      i += 1;
      // "Los!" darf etwas kuerzer bleiben als die Zahlen.
      setTimeout(tick, i === steps.length ? 500 : 1000);
    };
    tick();
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

    // Rank-Zeile als Platzhalter — wird async befuellt sobald die Preview-
    // API antwortet. Bei Fehler / fehlender API bleibt sie versteckt damit
    // wir nicht "Platz —" zeigen.
    const rankLine = el('p', 'jnr-rank-line jnr-hidden', '');

    const saveBtn = el('button', 'jnr-btn', 'Highscore speichern');
    saveBtn.onclick = () => this.renderNameInput(score, handlers, suggestedName);

    const skipBtn = el('button', 'jnr-btn jnr-btn-secondary', 'Nochmal spielen');
    skipBtn.onclick = () => handlers.onSkip();

    this.render(el('h2', 'jnr-title', title), scoreGroup, rankLine, divider(), saveBtn, skipBtn);

    // Rank async nachladen — zeigt "Platz X von Y" auch wenn der Spieler
    // unter den Top 10 landet. Adco-Anforderung: immer Rang anzeigen.
    if (handlers.onPreviewRank) {
      void handlers.onPreviewRank().then((preview) => {
        if (!preview) return;
        const denom = preview.totalEntries > 0 ? ` von ${preview.totalEntries + 1}` : '';
        // +1 weil der eigene Score noch nicht gespeichert ist — er ist Teil
        // der Gesamtmenge sobald gespeichert wird.
        rankLine.textContent = `Du bist Platz ${preview.rank}${denom}`;
        rankLine.classList.remove('jnr-hidden');
      });
    }
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

    const commit = async (finalName: string): Promise<void> => {
      writeLastName(finalName);
      const result = await handlers.onSave(finalName);
      if (result) {
        this.renderSaved(result, handlers);
      } else {
        handlers.onRestart();
      }
    };

    const submit = async (): Promise<void> => {
      const name = sanitizeName(input.value);
      if (name === '') {
        input.focus();
        return;
      }
      submitBtn.disabled = true;

      // Pre-Flight: Name schon vergeben? Dann warnen statt blind zu speichern —
      // der Spieler wusste evtl. nicht, dass der Name schon einem anderen
      // Highscore-Eintrag gehoert. Erst nach Bestaetigung wird die Session
      // durch den Submit verbraucht.
      if (handlers.onLookup) {
        submitBtn.textContent = 'Prüfe…';
        const existing = await handlers.onLookup(name);
        if (existing !== null) {
          this.renderNameTaken(score, name, existing, handlers, commit);
          return;
        }
      }

      submitBtn.textContent = 'Wird gespeichert…';
      await commit(name);
    };

    submitBtn.onclick = submit;
    backBtn.onclick = () => this.renderGameOverInitial(score, handlers, input.value);
    input.onkeydown = (e) => {
      // Keystrokes im Namen-Input nicht an die globalen Listener durchreichen:
      // sonst triggert "Space" einen Jump und "D" das Debug-Overlay.
      e.stopPropagation();
      if (e.key === 'Enter') {
        e.preventDefault();
        void submit();
      }
    };

    this.render(
      el('h2', 'jnr-title', 'Dein Name?'),
      el('p', 'jnr-subtitle', `${score} Punkte — so erscheinst du in der Liste`),
      divider(),
      input,
      submitBtn,
      backBtn,
    );
    setTimeout(() => input.focus(), 0);
  }

  /**
   * Warn-Screen wenn der gewaehlte Name schon in der Liste steht. Zwei Faelle:
   *
   *  - Neuer Score > gespeicherter Score: Saven wuerde ueberschreiben.
   *    Buttons: "Score ersetzen" (primary) und "Anderen Namen" (secondary).
   *  - Neuer Score <= gespeicherter Score: Saven ist sinnlos (GREATEST haelt
   *    den alten Wert) und wuerde die Session verbrauchen. Der "Trotzdem
   *    speichern"-Weg fehlt bewusst — Buttons: "Anderen Namen" und
   *    "Verwerfen" (startet neue Runde ohne Save).
   *
   * Die Session wird erst beim `commit` verbraucht — hier kann noch
   * abgebrochen werden.
   */
  private renderNameTaken(
    score: number,
    name: string,
    existingScore: number,
    handlers: GameOverHandlers,
    commit: (name: string) => Promise<void>,
  ): void {
    const beatsExisting = score > existingScore;

    const group = el('div', 'jnr-score-group');
    group.append(
      el('p', 'jnr-score-label', `"${name}" hat bereits`),
      el('p', 'jnr-score-display', String(existingScore)),
    );

    const subtitleText = beatsExisting
      ? `Dein Score (${score}) würde den alten ersetzen.`
      : `Dein Score (${score}) reicht nicht — Save würde nichts ändern.`;

    const pickOtherBtn = el('button', 'jnr-btn', 'Anderen Namen') as HTMLButtonElement;
    pickOtherBtn.onclick = () => this.renderNameInput(score, handlers, name);

    const secondBtn = beatsExisting
      ? (() => {
          const btn = el(
            'button',
            'jnr-btn jnr-btn-secondary',
            'Score ersetzen',
          ) as HTMLButtonElement;
          btn.onclick = async () => {
            btn.disabled = true;
            pickOtherBtn.disabled = true;
            btn.textContent = 'Wird gespeichert…';
            await commit(name);
          };
          return btn;
        })()
      : (() => {
          const btn = el('button', 'jnr-btn jnr-btn-secondary', 'Verwerfen') as HTMLButtonElement;
          btn.onclick = () => handlers.onSkip();
          return btn;
        })();

    this.render(
      el('h2', 'jnr-title', 'Name vergeben'),
      group,
      el('p', 'jnr-subtitle', subtitleText),
      divider(),
      pickOtherBtn,
      secondBtn,
    );
  }

  private renderSaved(result: ScoreResult, handlers: GameOverHandlers): void {
    // Kurze Titel — die Display-Font ist 3rem fett; laengere Texte brechen
    // optisch auseinander und zerren die Content-Spalte in die Breite.
    const titleText = result.personalBest ? 'Neuer Bestwert!' : 'Kein Bestwert';

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
      : el('p', 'jnr-subtitle', `Diesmal ${result.submittedScore} Punkte`);

    const restartBtn = el('button', 'jnr-btn', 'Nochmal spielen');
    restartBtn.onclick = handlers.onRestart;

    this.render(el('h2', 'jnr-title', titleText), group, summary, divider(), restartBtn);
  }

  private render(...children: HTMLElement[]): void {
    // Nur den linken Content-Slot austauschen — das Scoreboard (falls vorhanden)
    // lebt als Geschwister-Element im Overlay und bleibt bestehen.
    this.gameOverContent.replaceChildren(...children);
    this.gameOverOverlay.classList.remove('jnr-hidden');
  }
}

function sanitizeName(raw: string): string {
  // Spiegelbild der Server-Regel in ScoreEndpoint::sanitizeName — Unicode-Buchstaben
  // und -Zahlen erlaubt, damit Umlaute, ß und internationale Schreibweisen durchkommen.
  return raw
    .replace(/[^\p{L}\p{N} _-]/gu, '')
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

function hasDebugUrlParam(): boolean {
  try {
    return new URLSearchParams(window.location.search).get('jnr-debug') === '1';
  } catch {
    return false;
  }
}

// ── Bootstrap ─────────────────────────────────────────────────────────────

/** Initialisiert Engine, Renderer, UI und API-Client im uebergebenen Root-Container. */
export function bootstrap(root: HTMLElement, rawConfig: JumpnrunConfig = {}): void {
  if (root.dataset.jnrBooted) return;
  root.dataset.jnrBooted = '1';

  // Zod parst und clampt: unbekannte Felder werden ignoriert, fehlende Felder
  // fallen auf Defaults zurueck. Der Server hat schon sanitized, Zod ist hier
  // Second-Gate gegen kaputte window.JumpnrunConfig Manipulation.
  const engineCfg = GameConfigSchema.parse(rawConfig.engine ?? {});

  const ui = new GameUI(root, engineCfg.canvasWidth, engineCfg.canvasHeight);

  // Viewport-Guard liegt visuell ueber dem Spiel — blockiert nichts im
  // Bootstrap-Pfad, zeigt nur Pflicht-Hinweise (Querformat, Mindestgroesse).
  // Auf root statt wrap installieren: der Hinweis soll auch zu sehen sein
  // wenn der Wrap selbst zu klein ist.
  installViewportGuard(root, rawConfig.viewport ?? {});

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
    enabled: rawConfig.debug === true || hasDebugUrlParam(),
    hitboxBuffer: engineCfg.hitboxBuffer,
    coinMagnet: engineCfg.coinMagnet,
  };

  // Responsive Canvas: ResizeObserver synchronisiert physische Canvas-Pixel
  // mit der CSS-Container-Groesse. Engine bleibt in logischen 960x540
  // Koordinaten — der Renderer macht das Mapping via Transform-Matrix.
  // Debounce ist nicht noetig: Resize feuert per RAF, setSize ist O(1).
  const syncCanvasSize = (): void => {
    const rect = ui.wrap.getBoundingClientRect();
    if (rect.width > 0 && rect.height > 0) {
      renderer.setSize(rect.width, rect.height);
    }
  };
  // Initial: einmal direkt nach DOM-Append damit der erste Frame schon
  // korrekt skaliert wird.
  syncCanvasSize();
  const resizeObserver = new ResizeObserver(syncCanvasSize);
  resizeObserver.observe(ui.wrap);

  // Vollbild-Button oben rechts. Wenn der Spieler in Vollbild wechselt,
  // triggert der ResizeObserver oben automatisch ein neues setSize().
  attachFullscreenButton(ui.wrap, ui.wrap);

  const obstaclePool = rawConfig.assets?.obstacles ?? [];
  const platformPool = rawConfig.assets?.platforms ?? [];
  const backgroundPool = rawConfig.assets?.backgrounds ?? {};
  renderer.setBackgroundPool(backgroundPool);
  const world = new GameWorld(engineCfg, obstaclePool, platformPool);

  let images: ImageMap = new Map();
  if (rawConfig.images && Object.keys(rawConfig.images).length > 0) {
    // Loading-Screen liegt ueber dem Wrap (Start-Overlay, Discount, etc.).
    // Verschwindet automatisch sobald 100% erreicht — der Spieler kann den
    // Start-Button erst dann anklicken weil bis dahin der Loading-Overlay
    // pointer-events blockt.
    const loading = createLoadingScreen(ui.wrap);
    loadImages(rawConfig.images, (loaded, total) => loading.update(loaded, total))
      .then(({ images: loaded, masks }) => {
        images = loaded;
        renderer.setMasks(masks);
        world.setMasks(masks);
      })
      .catch((err: unknown) => {
        // Defensive: loadImages selbst wirft nicht (Promise.allSettled), aber
        // falls doch — Loading-Screen muss weg sonst bleibt der Spieler stuck.
        console.error('[jumpnrun] loadImages fehlgeschlagen', err);
        loading.done();
      });
  }
  const loop = new GameLoop(
    (dt) => world.update(dt),
    () => renderer.render(world.state, images),
  );

  const apiClient = rawConfig.api ? new ApiClient(rawConfig.api) : null;
  let sessionId: string | null = null;
  let lastLevel = 1;

  // Scoreboard: erst auf dem Game-Over-Screen sichtbar. Wird beim Bootstrap
  // gebaut und lazy beim ersten Game-Over gefetched.
  const scoreboard =
    apiClient && rawConfig.scoreboard?.enabled
      ? new Scoreboard(apiClient, rawConfig.scoreboard.limit)
      : null;
  if (scoreboard) {
    ui.attachScoreboard(scoreboard.element);
  }

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

    // Scoreboard erst beim Game-Over fetchen — spart einen Request bevor er
    // sichtbar waere und zeigt gleich den aktuellen Stand inkl. Mitspielern.
    if (scoreboard) {
      void scoreboard.refresh();
    }

    ui.showGameOver(score, {
      ...(apiClient
        ? {
            onLookup: (name: string) => apiClient.lookupName(name),
            onPreviewRank: () => apiClient.previewRank(score),
          }
        : {}),
      onSave: async (name: string) => {
        if (!apiClient || !savedSession || score <= 0) {
          return null;
        }
        const result = await apiClient.submitScore(savedSession, name, score, savedLevel);
        if (result && scoreboard) {
          void scoreboard.refresh(result.name);
        }
        return result;
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
    } else if (e.code === 'KeyD' && e.shiftKey && e.ctrlKey) {
      // Shift+Ctrl+D statt nacktem D: verhindert versehentliche Aktivierung
      // beim Tippen (vor allem im Namens-Input). URL-Param ?jnr-debug=1 bleibt
      // als reproduzierbare Alternative erhalten.
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
