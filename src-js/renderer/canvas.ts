import type { GameState } from '../engine/types.ts';
import type { ImageMap } from './assets.ts';

/** Solid-color fallbacks when sprite images are missing. */
const FALLBACK: Record<string, string> = {
  bg: '#1a3a4a',
  'player-idle': '#4fc3f7',
  'player-jump': '#81d4fa',
  'obstacle-0': '#ef5350',
  'obstacle-1': '#ff7043',
  'obstacle-2': '#ffa726',
  coin: '#ffd54f',
  'platform-0': '#78909c',
  'platform-1': '#546e7a',
};

export class CanvasRenderer {
  constructor(
    private readonly ctx: CanvasRenderingContext2D,
    private readonly width: number,
    private readonly height: number,
  ) {}

  render(state: GameState, images: ImageMap): void {
    const { ctx, width, height } = this;
    ctx.clearRect(0, 0, width, height);

    // ── Background ───────────────────────────────────────────────────────────
    const bgKey = `bg-${state.level - 1}`;
    const bg = images.get(bgKey);
    if (bg) {
      ctx.drawImage(bg, state.backgroundX, 0, width, height);
      ctx.drawImage(bg, state.backgroundX + width, 0, width, height);
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
  }

  // ── Private helpers ──────────────────────────────────────────────────────

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
      this.ctx.drawImage(img, x, y, w, h);
    } else {
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
      this.ctx.drawImage(img, x, y, w, h);
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
