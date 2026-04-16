import type { GameConfig } from './config/index.ts';
import type { Coin, Obstacle, Platform, SpriteMask } from './types.ts';

export class Spawner {
  private masks: ReadonlyMap<string, SpriteMask> = new Map();

  constructor(private readonly cfg: GameConfig) {}

  setMasks(masks: ReadonlyMap<string, SpriteMask>): void {
    this.masks = masks;
  }

  obstacle(_level: number): Obstacle {
    const width = 60 + Math.random() * 50;
    const height = 80 + Math.random() * 60;
    const imageKey = `obstacle-${Math.floor(Math.random() * 3)}`;
    return {
      x: this.cfg.canvasWidth + 50,
      y: this.cfg.canvasHeight - this.cfg.groundOffset - height,
      width,
      height,
      imageKey,
      mask: this.masks.get(imageKey),
    };
  }

  obstacleDelay(level: number): number {
    const speed = this.cfg.baseSpeed + level * this.cfg.speedPerLevel;
    const minTime = (220 / speed) * (1000 / 60); // ms to travel 220 px
    return minTime + Math.random() * 1200;
  }

  coin(): Coin {
    const groundY = this.cfg.canvasHeight - this.cfg.groundOffset;
    return {
      x: this.cfg.canvasWidth + Math.random() * 250,
      y: groundY - 80 - Math.random() * 180,
      width: 32,
      height: 32,
      collected: false,
      mask: this.masks.get('coin'),
    };
  }

  coinDelay(): number {
    return 600 + Math.random() * 1200;
  }

  platforms(): Platform[] {
    return Array.from({ length: 4 }, (_, i) => ({
      x: this.cfg.canvasWidth + i * 1200,
      y: this.cfg.canvasHeight - 180 - Math.random() * 180,
      width: 120,
      height: 18,
      imageKey: `platform-${i % 2}`,
    }));
  }

  platformCoin(platform: Platform): Coin {
    return {
      x: platform.x + platform.width / 2 - 16,
      y: platform.y - 48,
      width: 32,
      height: 32,
      collected: false,
      mask: this.masks.get('coin'),
    };
  }

  playerMask(): SpriteMask | undefined {
    // Accept several key conventions so WP-side sprite naming stays flexible.
    return this.masks.get('player') ?? this.masks.get('player-idle') ?? this.masks.get('player-jump');
  }
}
