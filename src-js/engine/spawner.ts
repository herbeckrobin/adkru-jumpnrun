import type { GameConfig } from './config/index.ts';
import type { Coin, Obstacle, Platform } from './types.ts';

export class Spawner {
  constructor(private readonly cfg: GameConfig) {}

  obstacle(_level: number): Obstacle {
    const width = 110 + Math.random() * 50;
    const height = 110 + Math.random() * 50;
    return {
      x: this.cfg.canvasWidth + 50,
      y: this.cfg.canvasHeight - this.cfg.groundOffset - height,
      width,
      height,
      imageKey: `obstacle-${Math.floor(Math.random() * 3)}`,
    };
  }

  obstacleDelay(level: number): number {
    const speed = this.cfg.baseSpeed + level * this.cfg.speedPerLevel;
    const minTime = (300 / speed) * (1000 / 60); // ms to travel 300 px
    return minTime + Math.random() * 1500;
  }

  coin(): Coin {
    return {
      x: this.cfg.canvasWidth + Math.random() * 300,
      y: this.cfg.canvasHeight - 200 - Math.random() * 150,
      width: 50,
      height: 50,
      collected: false,
    };
  }

  coinDelay(): number {
    return 800 + Math.random() * 1500;
  }

  platforms(): Platform[] {
    return Array.from({ length: 4 }, (_, i) => ({
      x: this.cfg.canvasWidth + i * 1400,
      y: this.cfg.canvasHeight - 200 - Math.random() * 200,
      width: 80,
      height: 20,
      imageKey: `platform-${i % 2}`,
    }));
  }

  platformCoin(platform: Platform): Coin {
    return {
      x: platform.x + 50,
      y: platform.y - 40,
      width: 50,
      height: 50,
      collected: false,
    };
  }
}
