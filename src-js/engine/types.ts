export interface Rect {
  x: number;
  y: number;
  width: number;
  height: number;
}

/**
 * Per-pixel alpha mask of a sprite in its *native* resolution.
 * `mask` has `width * height` bytes, 1 = opaque, 0 = transparent.
 * `tight*` describes the bounding box of opaque pixels in native coords.
 */
export interface SpriteMask {
  readonly width: number;
  readonly height: number;
  readonly tightX: number;
  readonly tightY: number;
  readonly tightW: number;
  readonly tightH: number;
  readonly mask: Uint8Array;
}

export interface Player extends Rect {
  velocityY: number;
  jumpCount: number;
  state: 'idle' | 'jumping';
  mask?: SpriteMask | undefined;
}

export interface Obstacle extends Rect {
  imageKey: string;
  mask?: SpriteMask | undefined;
}

export interface Coin extends Rect {
  collected: boolean;
  mask?: SpriteMask | undefined;
}

export interface Platform extends Rect {
  imageKey: string;
}

/**
 * Ein Hindernis-Bauplan aus dem Asset-Pool (via WordPress-CPT gepflegt).
 * Wird vom Spawner beim `obstacle()` gewichtet gezogen und in konkrete
 * Obstacle-Instanzen materialisiert.
 */
export interface ObstacleSpec {
  imageKey: string;
  width: number;
  height: number;
  minLevel: number;
  weight: number;
}

/** Hintergrund-Bauplan pro Level (mehrere moeglich, werden gewichtet gezogen). */
export interface BackgroundSpec {
  imageKey: string;
  weight: number;
}

/** Level-Nummer → Pool moeglicher Hintergruende. */
export type BackgroundPool = Readonly<Record<string, readonly BackgroundSpec[]>>;

/** Plattform-Bauplan aus dem CPT-Pool (jnr_platform). */
export interface PlatformSpec {
  imageKey: string;
  width: number;
  height: number;
  weight: number;
}

export type GameStatus = 'idle' | 'running' | 'paused' | 'gameover';

export interface GameState {
  readonly status: GameStatus;
  readonly player: Readonly<Player>;
  readonly obstacles: readonly Readonly<Obstacle>[];
  readonly coins: readonly Readonly<Coin>[];
  readonly platforms: readonly Readonly<Platform>[];
  readonly score: number;
  readonly level: number;
  readonly backgroundX: number;
  readonly showLevelText: boolean;
}
