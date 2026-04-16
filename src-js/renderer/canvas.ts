import type { GameState } from '../engine/types.ts';
import type { ImageMap } from './assets.ts';

/** Solid-color fallbacks when sprite images are missing. */
const FALLBACK: Record<string, string> = {
  bg: '#1a3a4a',
  'player-idle': '#4fc3f7',
  'player-jump': '#b3e5fc',
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
      // Fallback: sky + ground strip
      // biome-ignore lint/style/noNonNullAssertion: key 'bg' always exists in FALLBACK
      ctx.fillStyle = FALLBACK.bg!;
      ctx.fillRect(0, 0, width, height);
      ctx.fillStyle = '#2d5a27';
      ctx.fillRect(0, height - 50, width, 50);
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
      this.sprite(images, 'coin', c.x, c.y, c.width, c.height);
    }

    // ── HUD ──────────────────────────────────────────────────────────────────
    ctx.fillStyle = 'rgba(0,0,0,0.45)';
    ctx.fillRect(4, 4, 150, 68);
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 18px system-ui, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(`Score: ${state.score}`, 14, 29);
    ctx.fillText(`Level: ${state.level}`, 14, 57);

    // ── Level-up banner ──────────────────────────────────────────────────────
    if (state.showLevelText) {
      ctx.fillStyle = 'rgba(0,0,0,0.5)';
      ctx.fillRect(0, height / 2 - 135, width, 80);
      ctx.fillStyle = '#ffd54f';
      ctx.font = 'bold 52px system-ui, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(`Level ${state.level}`, width / 2, height / 2 - 75);
      ctx.textAlign = 'left';
    }
  }

  private sprite(images: ImageMap, key: string, x: number, y: number, w: number, h: number): void {
    const img = images.get(key);
    if (img) {
      this.ctx.drawImage(img, x, y, w, h);
    } else {
      this.ctx.fillStyle = FALLBACK[key] ?? '#888';
      this.ctx.fillRect(x, y, w, h);
    }
  }
}
