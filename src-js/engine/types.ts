export interface Rect {
  x: number;
  y: number;
  width: number;
  height: number;
}

export interface Player extends Rect {
  velocityY: number;
  jumpCount: number;
  state: 'idle' | 'jumping';
}

export interface Obstacle extends Rect {
  imageKey: string;
}

export interface Coin extends Rect {
  collected: boolean;
}

export interface Platform extends Rect {
  imageKey: string;
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
