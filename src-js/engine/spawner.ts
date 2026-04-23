import type { GameConfig } from './config/index.ts';
import type { Coin, Obstacle, ObstacleSpec, Platform, PlatformSpec, SpriteMask } from './types.ts';

/** Erzeugt Hindernisse, Coins und Plattformen aus den Asset-Pools mit gewichtetem Random. */
export class Spawner {
  private masks: ReadonlyMap<string, SpriteMask> = new Map();

  constructor(
    private readonly cfg: GameConfig,
    private readonly obstaclePool: readonly ObstacleSpec[] = [],
    private readonly platformPool: readonly PlatformSpec[] = [],
  ) {}

  /** Reicht die geladenen Pixelmasken durch — fuer pixelgenaue Hitbox-Kollision. */
  setMasks(masks: ReadonlyMap<string, SpriteMask>): void {
    this.masks = masks;
  }

  /**
   * Liefert das naechste Hindernis aus dem CPT-Pool (gefiltert nach min_level,
   * gewichtet gezogen). Pool leer → Dummy-Hindernis mit zufaelliger Groesse,
   * das der Renderer als Solid-Color-Box zeichnet (analog zum Sky-Gradient
   * fuer fehlende Backgrounds). Konsistente Regel: keine Mediathek-Zuweisung
   * → Farbflaeche, aber das Gameplay laeuft weiter.
   */
  obstacle(level: number): Obstacle {
    const available = this.obstaclePool.filter((o) => o.minLevel <= level);
    if (available.length > 0) {
      const chosen = weightedPick(available);
      return {
        x: this.cfg.canvasWidth + 50,
        y: this.cfg.canvasHeight - this.cfg.groundOffset - chosen.height,
        width: chosen.width,
        height: chosen.height,
        imageKey: chosen.imageKey,
        mask: this.masks.get(chosen.imageKey),
      };
    }
    // Dummy mit zufaelliger Groesse — keine Mask, Renderer faellt auf
    // FALLBACK['obstacle-fallback'] (Solid-Color) zurueck.
    const width = 60 + Math.random() * 50;
    const height = 80 + Math.random() * 60;
    return {
      x: this.cfg.canvasWidth + 50,
      y: this.cfg.canvasHeight - this.cfg.groundOffset - height,
      width,
      height,
      imageKey: 'obstacle-fallback',
      mask: undefined,
    };
  }

  /** Zeit in ms bis zum naechsten Obstacle-Spawn, skaliert mit dem Level-Tempo. */
  obstacleDelay(level: number): number {
    const speed = this.cfg.baseSpeed + level * this.cfg.speedPerLevel;
    const minTime = (220 / speed) * (1000 / 60); // ms to travel 220 px
    return minTime + Math.random() * 1200;
  }

  /**
   * Erzeugt einen Coin. Optional koennen aktuell lebende Obstacles
   * uebergeben werden — wenn der Kandidat mit einem davon ueberlappt,
   * werden bis zu drei alternative Positionen probiert. Scheitert das,
   * wird der Spawn uebersprungen (`null`) — Coins regenerieren sich
   * eh kontinuierlich, ein gelegentliches Auslassen ist unsichtbar.
   */
  coin(obstacles: readonly Obstacle[] = []): Coin | null {
    for (let attempt = 0; attempt < 4; attempt++) {
      const candidate = this.makeCoinCandidate();
      if (!this.overlapsAnyObstacle(candidate, obstacles)) {
        return candidate;
      }
    }
    return null;
  }

  private makeCoinCandidate(): Coin {
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

  private overlapsAnyObstacle(coin: Coin, obstacles: readonly Obstacle[]): boolean {
    const pad = 8; // kleiner Puffer damit Coins nicht direkt an Kanten kleben
    for (const o of obstacles) {
      if (
        coin.x < o.x + o.width + pad &&
        coin.x + coin.width + pad > o.x &&
        coin.y < o.y + o.height + pad &&
        coin.y + coin.height + pad > o.y
      ) {
        return true;
      }
    }
    return false;
  }

  /** Zeit in ms bis zum naechsten Coin-Spawn (600 bis 1800 ms, random). */
  coinDelay(): number {
    return 600 + Math.random() * 1200;
  }

  /**
   * Setzt die initialen Plattformen — pro Slot wird ein Spec aus dem CPT-Pool
   * gezogen. Pool leer → Solid-Color-Dummy mit Standardgroesse, das Spiel
   * bleibt spielbar.
   */
  platforms(): Platform[] {
    return Array.from({ length: 4 }, (_, i) => {
      const x = this.cfg.canvasWidth + i * 1200;
      const y = this.cfg.canvasHeight - 180 - Math.random() * 180;
      if (this.platformPool.length > 0) {
        const chosen = weightedPick(this.platformPool);
        return {
          x,
          y,
          width: chosen.width,
          height: chosen.height,
          imageKey: chosen.imageKey,
        };
      }
      return {
        x,
        y,
        width: 120,
        height: 18,
        imageKey: 'platform-fallback',
      };
    });
  }

  /** Erzeugt einen Coin mittig ueber der uebergebenen Plattform. */
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

  /** Liefert die Maske der Spielfigur, akzeptiert drei Key-Varianten (player / player-idle / player-jump). */
  playerMask(): SpriteMask | undefined {
    // Accept several key conventions so WP-side sprite naming stays flexible.
    return (
      this.masks.get('player') ?? this.masks.get('player-idle') ?? this.masks.get('player-jump')
    );
  }
}

/**
 * Gewichtete Zufallsauswahl. Jeder Eintrag hat `weight >= 1`, die Summe
 * bestimmt die Ziehungs-Wahrscheinlichkeit. Leere Liste → Runtime-Error,
 * aber aufrufer pruefen vorher via `length > 0`.
 */
export function weightedPick<T extends { weight: number }>(items: readonly T[]): T {
  const total = items.reduce((s, i) => s + Math.max(1, i.weight), 0);
  let r = Math.random() * total;
  for (const item of items) {
    r -= Math.max(1, item.weight);
    if (r <= 0) return item;
  }
  // biome-ignore lint/style/noNonNullAssertion: length > 0 vom Aufrufer garantiert
  return items[items.length - 1]!;
}
