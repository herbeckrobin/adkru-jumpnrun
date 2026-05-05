import { fitRect } from '../engine/fit.ts';
import { weightedPick } from '../engine/spawner.ts';
import type { BackgroundPool, GameState, SpriteMask } from '../engine/types.ts';
import type { ImageMap, MaskMap } from './assets.ts';

/** Schaltet Hitbox-Visualisierung zur Laufzeit ein und spiegelt die Engine-Puffer. */
export interface DebugOptions {
  /** Draw collision hitboxes + solid mask pixels over everything. */
  enabled: boolean;
  /** Obstacle AABB inset (positive = shrink). Must mirror `cfg.hitboxBuffer`. */
  hitboxBuffer: number;
  /** Coin AABB inset (positive = grow). Must mirror `cfg.coinMagnet`. */
  coinMagnet: number;
}

/**
 * Solid-color fallbacks wenn keine Image-URL fuer einen Sprite-Key in der
 * `images`-Map liegt. Konsistente Regel: keine Mediathek-Zuweisung im Admin
 * → das Spiel zeigt eine Farbflaeche statt zu crashen.
 */
const FALLBACK: Record<string, string> = {
  bg: '#1a3a4a',
  'player-idle': '#4fc3f7',
  'player-jump': '#81d4fa',
  'obstacle-0': '#ef5350',
  'obstacle-1': '#ff7043',
  'obstacle-2': '#ffa726',
  'obstacle-fallback': '#ef5350',
  coin: '#ffd54f',
  'platform-0': '#78909c',
  'platform-1': '#546e7a',
  'platform-fallback': '#78909c',
};

/** Zeichnet den Game-State auf ein 2D-Canvas inkl. HUD, Banner und Debug-Overlay. */
export class CanvasRenderer {
  debug: DebugOptions = { enabled: false, hitboxBuffer: 0, coinMagnet: 0 };
  private masks: MaskMap = new Map();
  private backgroundPool: BackgroundPool = {};
  private chosenBg: Map<number, string> = new Map();

  // Physische Render-Geometrie. `width`/`height` bleiben die logischen
  // Spielfeld-Masse (z.B. 960x540), die die Engine kennt. `scale` + `offset`
  // mappen logische Koordinaten contain-fit auf den physischen Canvas.
  private scale = 1;
  private offsetX = 0;
  private offsetY = 0;
  private dpr = 1;

  constructor(
    private readonly ctx: CanvasRenderingContext2D,
    private readonly width: number,
    private readonly height: number,
  ) {}

  /**
   * Passt die physische Canvas-Groesse + Transform-Matrix an einen neuen
   * Container-Abmasse an. Aspect-preservierender contain-fit: das Spielfeld
   * fuellt soviel wie moeglich, ohne verzerrt zu werden — verbleibende
   * Flaechen werden Letterbox (schwarz nach `clearRect`).
   */
  setSize(cssWidth: number, cssHeight: number, dpr: number = window.devicePixelRatio || 1): void {
    if (cssWidth <= 0 || cssHeight <= 0) return;
    this.dpr = dpr;

    const canvas = this.ctx.canvas;
    canvas.width = Math.round(cssWidth * dpr);
    canvas.height = Math.round(cssHeight * dpr);

    // Contain-fit: kleinere Skala bestimmt, damit nichts abgeschnitten wird.
    const sx = cssWidth / this.width;
    const sy = cssHeight / this.height;
    this.scale = Math.min(sx, sy);
    this.offsetX = (cssWidth - this.width * this.scale) / 2;
    this.offsetY = (cssHeight - this.height * this.scale) / 2;
  }

  /** Aktualisiert die Pixelmasken — wird nach dem asynchronen Bild-Preload gesetzt. */
  setMasks(masks: MaskMap): void {
    this.masks = masks;
  }

  /**
   * Setzt den Level→Background-Pool (via CPTs im WP-Admin konfiguriert).
   * Leeres Objekt → Renderer faellt auf den Legacy-Schluessel `bg-{level-1}` zurueck.
   * Der Cache `chosenBg` wird geleert, damit ein neu geladener Pool ab sofort greift.
   */
  setBackgroundPool(pool: BackgroundPool): void {
    this.backgroundPool = pool;
    this.chosenBg.clear();
  }

  /**
   * Entscheidet pro Level einmalig welches Bild aus dem Pool gezogen wird
   * und cached das Ergebnis — sonst wuerde jedes Frame neu gelost und der
   * Hintergrund flackerte. Liefert `null` wenn fuer das Level kein Background
   * im CPT-Pool gepflegt ist; der Renderer faellt dann auf den Sky-Gradient zurueck.
   */
  private bgKeyForLevel(level: number): string | null {
    const cached = this.chosenBg.get(level);
    if (cached !== undefined) return cached === '' ? null : cached;
    const pool = this.backgroundPool[String(level)];
    if (pool && pool.length > 0) {
      const chosen = weightedPick(pool);
      this.chosenBg.set(level, chosen.imageKey);
      return chosen.imageKey;
    }
    // Pool fuer dieses Level leer — Sky-Gradient-Fallback signalisieren.
    this.chosenBg.set(level, '');
    return null;
  }

  /** Zeichnet einen Frame: Hintergrund, Plattformen, Spieler, Hindernisse, Coins, HUD, Overlays. */
  render(state: GameState, images: ImageMap): void {
    const { ctx, width, height } = this;

    // Erst die komplette physische Flaeche clearen (Letterbox-Bereiche werden
    // schwarz, weil das CSS-Background des Canvas-Wraps schwarz ist). Dann
    // Transform setzen damit alle weiteren Draw-Calls in logischen Koordinaten
    // laufen — Engine + Renderer-Code muss nichts ueber Skalierung wissen.
    const canvas = ctx.canvas;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.setTransform(
      this.scale * this.dpr,
      0,
      0,
      this.scale * this.dpr,
      this.offsetX * this.dpr,
      this.offsetY * this.dpr,
    );

    ctx.clearRect(0, 0, width, height);

    // ── Background ───────────────────────────────────────────────────────────
    const bgKey = this.bgKeyForLevel(state.level);
    const bg = bgKey !== null ? images.get(bgKey) : undefined;
    if (bg && bg.naturalHeight > 0) {
      this.drawTiledBackground(bg, state.backgroundX);
    } else {
      this.drawFallbackBackground();
    }

    // ── Platforms ────────────────────────────────────────────────────────────
    for (const p of state.platforms) {
      this.sprite(images, p.imageKey, p.x, p.y, p.width, p.height);
    }

    // ── Player ───────────────────────────────────────────────────────────────
    const playerKey = state.player.state === 'jumping' ? 'player-jump' : 'player-idle';
    this.sprite(
      images,
      playerKey,
      state.player.x,
      state.player.y,
      state.player.width,
      state.player.height,
    );

    // ── Obstacles ────────────────────────────────────────────────────────────
    for (const o of state.obstacles) {
      this.sprite(images, o.imageKey, o.x, o.y, o.width, o.height);
    }

    // ── Coins ────────────────────────────────────────────────────────────────
    for (const c of state.coins) {
      this.drawCoin(images, c.x, c.y, c.width, c.height);
    }

    // ── HUD ──────────────────────────────────────────────────────────────────
    this.drawHUD(state);

    // ── Level-up banner ──────────────────────────────────────────────────────
    if (state.showLevelText) {
      this.drawLevelBanner(state.level);
    }

    // ── Debug overlay ────────────────────────────────────────────────────────
    if (this.debug.enabled) {
      this.drawDebugOverlay(state);
    }
  }

  private drawDebugOverlay(state: GameState): void {
    const buf = this.debug.hitboxBuffer;
    const mag = this.debug.coinMagnet;

    // Player: fit-rect (= actual hitbox) + pixel mask
    const playerFit = fitRect(state.player, state.player.mask);
    this.drawMaskOverlay(playerFit, state.player.mask, 'rgba(0,200,255,0.4)');
    this.drawHitbox(
      playerFit.x + buf,
      playerFit.y + buf,
      playerFit.width - 2 * buf,
      playerFit.height - 2 * buf,
      '#00e5ff',
      'Player',
    );

    for (const o of state.obstacles) {
      const fit = fitRect(o, o.mask);
      this.drawMaskOverlay(fit, o.mask, 'rgba(255,70,70,0.4)');
      this.drawHitbox(
        fit.x + buf,
        fit.y + buf,
        fit.width - 2 * buf,
        fit.height - 2 * buf,
        '#ff3b3b',
      );
    }

    for (const c of state.coins) {
      const fit = fitRect(c, c.mask);
      this.drawHitbox(
        fit.x - mag,
        fit.y - mag,
        fit.width + 2 * mag,
        fit.height + 2 * mag,
        '#ffeb3b',
      );
    }

    // Legend
    const { ctx } = this;
    ctx.save();
    ctx.fillStyle = 'rgba(0,0,0,0.7)';
    ctx.fillRect(this.width - 220, 12, 208, 64);
    ctx.font = 'bold 12px system-ui, sans-serif';
    ctx.fillStyle = '#00e5ff';
    ctx.fillText(`■ Player hitbox (buffer ${buf}px)`, this.width - 212, 30);
    ctx.fillStyle = '#ff3b3b';
    ctx.fillText(`■ Obstacle hitbox (buffer ${buf}px)`, this.width - 212, 48);
    ctx.fillStyle = '#ffeb3b';
    ctx.fillText(`■ Coin magnet (${mag}px)`, this.width - 212, 66);
    ctx.restore();
  }

  private drawHitbox(
    x: number,
    y: number,
    w: number,
    h: number,
    color: string,
    label?: string,
  ): void {
    const { ctx } = this;
    ctx.save();
    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.setLineDash([4, 3]);
    ctx.strokeRect(x, y, w, h);
    if (label) {
      ctx.setLineDash([]);
      ctx.fillStyle = color;
      ctx.font = 'bold 11px system-ui, sans-serif';
      ctx.fillText(label, x, y - 4);
    }
    ctx.restore();
  }

  /**
   * Paints the solid (opaque) pixels of the sprite mask in `color`, using the
   * same image → rect scale as the renderer, so it lines up with what you see.
   */
  private drawMaskOverlay(
    rect: { x: number; y: number; width: number; height: number },
    mask: SpriteMask | undefined,
    color: string,
  ): void {
    if (!mask) return;
    const { ctx } = this;
    // Match the tight-crop renderer: tight bbox → rect.
    const scaleX = rect.width / mask.tightW;
    const scaleY = rect.height / mask.tightH;
    ctx.save();
    ctx.fillStyle = color;
    for (let my = mask.tightY; my < mask.tightY + mask.tightH; my += 2) {
      for (let mx = mask.tightX; mx < mask.tightX + mask.tightW; mx += 2) {
        if (mask.mask[my * mask.width + mx] === 1) {
          ctx.fillRect(
            rect.x + (mx - mask.tightX) * scaleX,
            rect.y + (my - mask.tightY) * scaleY,
            2 * scaleX,
            2 * scaleY,
          );
        }
      }
    }
    ctx.restore();
  }

  // ── Private helpers ──────────────────────────────────────────────────────

  /**
   * Skaliert das Hintergrundbild auf die Spielfeld-Hoehe (Aspect bleibt
   * erhalten) und kachelt es horizontal endlos. Erlaubt beliebige Bild-
   * breiten — von 200 px (sichtbares Tiling) bis 12000 px (kacheln nur
   * selten). Tile-Mode ist global, kein Per-Background-Schema.
   */
  private drawTiledBackground(bg: HTMLImageElement, scrollX: number): void {
    const { ctx, width, height } = this;
    const scale = height / bg.naturalHeight;
    const drawW = bg.naturalWidth * scale;
    if (drawW <= 0) {
      this.drawFallbackBackground();
      return;
    }

    // Start-X auf den Bereich (-drawW, 0] normieren. Engine zaehlt scrollX
    // monoton runter (-100, -200, …) — dieser Versatz ist die "rest-Pixel"
    // bis zum naechsten ganzzahligen Tile, mit dem Vorzeichen das nach
    // links zeigt. Bug-Falle: das einfache `((x % d) + d) % d` Pattern
    // gibt einen positiven Offset zurueck und laesst den Hintergrund
    // optisch rueckwaerts laufen, weil der Tile-Anker dann nach RECHTS
    // wandert sobald scrollX sinkt.
    let xStart = scrollX % drawW;
    if (xStart > 0) xStart -= drawW;

    for (let x = xStart; x < width; x += drawW) {
      ctx.drawImage(bg, x, 0, drawW, height);
    }
  }

  private drawFallbackBackground(): void {
    const { ctx, width, height } = this;

    // Sky gradient
    const sky = ctx.createLinearGradient(0, 0, 0, height * 0.75);
    sky.addColorStop(0, '#0d1b2a');
    sky.addColorStop(1, '#1a3a4a');
    ctx.fillStyle = sky;
    ctx.fillRect(0, 0, width, height);

    // Ground strip
    const groundY = height - 60;
    const ground = ctx.createLinearGradient(0, groundY, 0, height);
    ground.addColorStop(0, '#2d6a20');
    ground.addColorStop(1, '#1a4012');
    ctx.fillStyle = ground;
    ctx.fillRect(0, groundY, width, height - groundY);

    // Ground top edge highlight
    ctx.fillStyle = '#4caf50';
    ctx.fillRect(0, groundY, width, 3);
  }

  private drawHUD(state: GameState): void {
    const { ctx } = this;

    // Background pill
    const padX = 14;
    const padY = 10;
    const boxW = 160;
    const boxH = 56;
    const x = 12;
    const y = 12;

    ctx.fillStyle = 'rgba(0,0,0,0.55)';
    this.roundRect(x, y, boxW, boxH, 10);
    ctx.fill();

    // Score
    ctx.fillStyle = '#ffd54f';
    ctx.font = 'bold 15px system-ui, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('Score', x + padX, y + padY + 13);
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 20px system-ui, sans-serif';
    ctx.fillText(String(state.score), x + padX + 50, y + padY + 13);

    // Level
    ctx.fillStyle = '#81d4fa';
    ctx.font = 'bold 15px system-ui, sans-serif';
    ctx.fillText('Level', x + padX, y + padY + 35);
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 20px system-ui, sans-serif';
    ctx.fillText(String(state.level), x + padX + 50, y + padY + 35);

    ctx.textAlign = 'left';
  }

  private drawLevelBanner(level: number): void {
    const { ctx, width, height } = this;
    const cy = height / 2 - 80;

    // Semi-transparent bar
    ctx.fillStyle = 'rgba(0,0,0,0.6)';
    ctx.fillRect(0, cy - 14, width, 72);

    // Accent line top & bottom
    ctx.fillStyle = '#ffd54f';
    ctx.fillRect(0, cy - 14, width, 3);
    ctx.fillRect(0, cy + 58, width, 3);

    // Text
    ctx.fillStyle = '#ffd54f';
    ctx.font = 'bold 48px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(`Level ${level}`, width / 2, cy + 42);
    ctx.textAlign = 'left';
  }

  private drawCoin(images: ImageMap, x: number, y: number, w: number, h: number): void {
    const img = images.get('coin');
    if (img) {
      this.sprite(images, 'coin', x, y, w, h);
      return;
    }
    {
      // Draw a glowing gold circle as fallback
      const cx = x + w / 2;
      const cy = y + h / 2;
      const r = w / 2;

      this.ctx.save();
      this.ctx.shadowColor = '#ffd54f';
      this.ctx.shadowBlur = 8;
      this.ctx.fillStyle = '#ffd54f';
      this.ctx.beginPath();
      this.ctx.arc(cx, cy, r, 0, Math.PI * 2);
      this.ctx.fill();
      this.ctx.shadowBlur = 0;
      // Inner shine
      this.ctx.fillStyle = '#fff9c4';
      this.ctx.beginPath();
      this.ctx.arc(cx - r * 0.25, cy - r * 0.25, r * 0.35, 0, Math.PI * 2);
      this.ctx.fill();
      this.ctx.restore();
    }
  }

  private sprite(images: ImageMap, key: string, x: number, y: number, w: number, h: number): void {
    const img = images.get(key);
    if (img) {
      const mask = this.masks.get(key);
      if (mask) {
        // Contain-fit: preserve aspect, crop to tight bbox, centered in slot.
        const fit = fitRect({ x, y, width: w, height: h }, mask);
        this.ctx.drawImage(
          img,
          mask.tightX,
          mask.tightY,
          mask.tightW,
          mask.tightH,
          fit.x,
          fit.y,
          fit.width,
          fit.height,
        );
      } else {
        this.ctx.drawImage(img, x, y, w, h);
      }
    } else {
      this.ctx.fillStyle = FALLBACK[key] ?? '#888';
      this.ctx.fillRect(x, y, w, h);
      // Subtle top highlight for depth
      this.ctx.fillStyle = 'rgba(255,255,255,0.15)';
      this.ctx.fillRect(x, y, w, Math.min(4, h));
    }
  }

  /** Canvas roundRect helper (works in all browsers, no Path2D needed). */
  private roundRect(x: number, y: number, w: number, h: number, r: number): void {
    const { ctx } = this;
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.arcTo(x + w, y, x + w, y + r, r);
    ctx.lineTo(x + w, y + h - r);
    ctx.arcTo(x + w, y + h, x + w - r, y + h, r);
    ctx.lineTo(x + r, y + h);
    ctx.arcTo(x, y + h, x, y + h - r, r);
    ctx.lineTo(x, y + r);
    ctx.arcTo(x, y, x + r, y, r);
    ctx.closePath();
  }
}
