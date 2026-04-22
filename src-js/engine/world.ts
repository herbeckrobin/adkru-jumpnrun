import { DEFAULT_CONFIG, type GameConfig } from './config/index.ts';
import { EventBus } from './events/index.ts';
import { playerHitsCoin, playerHitsObstacle, playerLandsOnPlatform } from './physics.ts';
import { Spawner } from './spawner.ts';
import type {
  Coin,
  GameState,
  GameStatus,
  Obstacle,
  ObstacleSpec,
  Platform,
  PlatformSpec,
  Player,
  SpriteMask,
} from './types.ts';

/** Frames the "Level X" banner stays visible (2 s at 60 Hz). */
const LEVEL_TEXT_FRAMES = 120;

export class GameWorld {
  readonly events = new EventBus();

  private readonly cfg: GameConfig;
  private readonly spawner: Spawner;

  private _status: GameStatus = 'idle';
  private _player!: Player;
  private _obstacles: Obstacle[] = [];
  private _coins: Coin[] = [];
  private _platforms: Platform[] = [];
  private _score = 0;
  private _level = 1;
  private _backgroundX = 0;
  private _showLevelText = false;
  private _levelTextFrames = 0;
  private _discountShown = false;

  private _obstacleTimer = 0; // ms accumulated since last spawn
  private _coinTimer = 0;
  private _nextObstacleDelay = 1500;
  private _nextCoinDelay = 800;

  constructor(
    config: Partial<GameConfig> = {},
    obstaclePool: readonly ObstacleSpec[] = [],
    platformPool: readonly PlatformSpec[] = [],
  ) {
    this.cfg = { ...DEFAULT_CONFIG, ...config };
    this.spawner = new Spawner(this.cfg, obstaclePool, platformPool);
    this._player = this.buildPlayer();
  }

  // ── Public read ──────────────────────────────────────────────────────────

  get state(): GameState {
    return {
      status: this._status,
      player: { ...this._player },
      obstacles: this._obstacles.map((o) => ({ ...o })),
      coins: this._coins.map((c) => ({ ...c })),
      platforms: this._platforms.map((p) => ({ ...p })),
      score: this._score,
      level: this._level,
      backgroundX: this._backgroundX,
      showLevelText: this._showLevelText,
    };
  }

  // ── Public commands ──────────────────────────────────────────────────────

  start(): void {
    this.reset();
    this._status = 'running';
  }

  restart(): void {
    this.reset();
    this._status = 'running';
  }

  jump(): void {
    if (this._status !== 'running') return;
    if (this._player.jumpCount >= this.cfg.maxJumps) return;
    this._player.velocityY = this.cfg.jumpVelocity;
    this._player.jumpCount++;
    this._player.state = 'jumping';
    this.events.emit('jump', undefined);
  }

  pause(): void {
    if (this._status === 'running') this._status = 'paused';
  }

  resume(): void {
    if (this._status === 'paused') this._status = 'running';
  }

  /**
   * Hand over sprite masks built by the renderer. Spawned entities from this
   * point on get pixel-perfect hitboxes; the current player mask is also
   * patched in place. Safe to call before or after `start()`.
   */
  setMasks(masks: ReadonlyMap<string, SpriteMask>): void {
    this.spawner.setMasks(masks);
    this._player.mask = this.spawner.playerMask();
  }

  // ── Fixed-step update (called by GameLoop at dt = 1/60) ──────────────────

  update(_dt: number): void {
    if (this._status !== 'running') return;

    // Background parallax scroll
    this._backgroundX -= this.cfg.bgScrollBase + this._level * this.cfg.bgScrollPerLevel;
    if (this._backgroundX <= -this.cfg.canvasWidth) this._backgroundX = 0;

    // Level-text countdown
    if (this._showLevelText && --this._levelTextFrames <= 0) {
      this._showLevelText = false;
    }

    // Spawn timers (ms, since dt ≈ 16.67 ms per step)
    const dtMs = (1 / 60) * 1000;
    this._obstacleTimer += dtMs;
    this._coinTimer += dtMs;

    if (this._obstacleTimer >= this._nextObstacleDelay) {
      this._obstacles.push(this.spawner.obstacle(this._level));
      this._obstacleTimer = 0;
      this._nextObstacleDelay = this.spawner.obstacleDelay(this._level);
    }

    if (this._coinTimer >= this._nextCoinDelay) {
      const coin = this.spawner.coin(this._obstacles);
      if (coin !== null) this._coins.push(coin);
      this._coinTimer = 0;
      this._nextCoinDelay = this.spawner.coinDelay();
    }

    // Gravity + position
    this._player.velocityY += this.cfg.gravity;
    this._player.y += this._player.velocityY;

    // Platform landing
    let onPlatform = false;
    for (const p of this._platforms) {
      if (playerLandsOnPlatform(this._player, this._player.velocityY, p)) {
        this._player.y = p.y - this._player.height;
        this._player.velocityY = 0;
        this._player.jumpCount = 0;
        this._player.state = 'idle';
        onPlatform = true;
        break;
      }
    }

    // Ground
    const groundY = this.cfg.canvasHeight - this.cfg.groundOffset;
    if (!onPlatform && this._player.y + this._player.height >= groundY) {
      this._player.y = groundY - this._player.height;
      this._player.velocityY = 0;
      this._player.jumpCount = 0;
      this._player.state = 'idle';
    }

    const speed = this.cfg.baseSpeed + this._level * this.cfg.speedPerLevel;

    // Move obstacles + collision
    for (let i = this._obstacles.length - 1; i >= 0; i--) {
      // biome-ignore lint/style/noNonNullAssertion: index-based loop
      const o = this._obstacles[i]!;
      o.x -= speed;
      if (o.x + o.width < 0) {
        this._obstacles.splice(i, 1);
        continue;
      }
      if (playerHitsObstacle(this._player, o, this.cfg.hitboxBuffer)) {
        this.triggerGameOver();
        return;
      }
    }

    // Move coins + collect
    for (let i = this._coins.length - 1; i >= 0; i--) {
      // biome-ignore lint/style/noNonNullAssertion: index-based loop
      const c = this._coins[i]!;
      c.x -= speed;
      if (c.x + c.width < 0) {
        this._coins.splice(i, 1);
        continue;
      }
      if (!c.collected && playerHitsCoin(this._player, c, this.cfg.coinMagnet)) {
        c.collected = true;
        this._coins.splice(i, 1);
        this._score++;
        this.events.emit('score', { score: this._score, level: this._level });
        this.checkLevelUp();
      }
    }

    // Move platforms
    const platformSpeed = this.cfg.baseSpeed + this._level * 0.2;
    for (let i = this._platforms.length - 1; i >= 0; i--) {
      // biome-ignore lint/style/noNonNullAssertion: index-based loop
      const p = this._platforms[i]!;
      p.x -= platformSpeed;
      if (p.x + p.width < 0) this._platforms.splice(i, 1);
    }
  }

  // ── Private helpers ──────────────────────────────────────────────────────

  private reset(): void {
    this._score = 0;
    this._level = 1;
    this._backgroundX = 0;
    this._showLevelText = false;
    this._discountShown = false;
    this._obstacleTimer = 0;
    this._coinTimer = 0;
    this._nextObstacleDelay = 1500;
    this._nextCoinDelay = 800;
    this._obstacles = [];
    this._coins = [];
    this._player = this.buildPlayer();
    this._platforms = this.spawner.platforms();
    for (const p of this._platforms) {
      this._coins.push(this.spawner.platformCoin(p));
    }
  }

  private buildPlayer(): Player {
    return {
      x: this.cfg.playerStartX,
      y: this.cfg.canvasHeight - this.cfg.groundOffset - this.cfg.playerHeight,
      width: this.cfg.playerWidth,
      height: this.cfg.playerHeight,
      velocityY: 0,
      jumpCount: 0,
      state: 'idle',
      mask: this.spawner.playerMask(),
    };
  }

  private checkLevelUp(): void {
    const newLevel = Math.min(
      this.cfg.maxLevels,
      Math.floor(this._score / this.cfg.coinsPerLevel) + 1,
    );
    if (newLevel === this._level) return;

    this._level = newLevel;
    this._showLevelText = true;
    this._levelTextFrames = LEVEL_TEXT_FRAMES;
    this.events.emit('level-change', { level: this._level });

    if (!this._discountShown && this._level === this.cfg.discountLevel) {
      this._discountShown = true;
      this._status = 'paused';
      this.events.emit('discount', { code: this.cfg.discountCode, level: this._level });
    }
  }

  private triggerGameOver(): void {
    this._status = 'gameover';
    this.events.emit('game-over', { score: this._score });
  }
}
