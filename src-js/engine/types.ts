/** Axis-aligned Bounding Box — Basis fuer alle sichtbaren Objekte. */
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

/** Die Spielfigur mit Sprung-State und optionaler Pixelmaske. */
export interface Player extends Rect {
  velocityY: number;
  jumpCount: number;
  state: 'idle' | 'jumping';
  mask?: SpriteMask | undefined;
}

/** Ein laufendes Hindernis im Spielfeld. */
export interface Obstacle extends Rect {
  imageKey: string;
  mask?: SpriteMask | undefined;
}

/** Ein einzelner Coin im Spielfeld. */
export interface Coin extends Rect {
  collected: boolean;
  mask?: SpriteMask | undefined;
}

/** Eine begehbare Plattform im Spielfeld. */
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

/** Status-Machine des Spiels — idle bis gameover. */
export type GameStatus = 'idle' | 'running' | 'paused' | 'gameover';

/**
 * CSS-Pixelmasse + Skalierung des Canvas-Containers.
 * Engine bleibt in logischen Koordinaten (canvasWidth/Height aus Config),
 * der Renderer rechnet diese ueber `scale` + `offset` auf die physische
 * Canvas-Flaeche um (contain-fit, Letterboxing wenn Aspect abweicht).
 */
export interface GameDimensions {
  /** Container-Breite in CSS-Pixeln (vor DPR-Multiplikation). */
  cssWidth: number;
  /** Container-Hoehe in CSS-Pixeln. */
  cssHeight: number;
  /** Logisch → CSS-Pixel-Faktor (Aspect-preservierend). */
  scale: number;
  /** Letterbox-Offset links in CSS-Pixeln. */
  offsetX: number;
  /** Letterbox-Offset oben in CSS-Pixeln. */
  offsetY: number;
  /** Device Pixel Ratio fuer scharfe Darstellung auf Retina. */
  dpr: number;
}

/** Immutable Snapshot des aktuellen Spielstands fuer den Renderer. */
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
