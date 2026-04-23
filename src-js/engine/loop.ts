/** Fixed-timestep game loop (Glenn Fiedler pattern).
 *  Physics runs at exactly 60 Hz regardless of frame rate.
 *  Real frames may be faster or slower; the accumulator absorbs the difference.
 */
const FIXED_DT = 1 / 60;

/** Fixed-Timestep-Loop — trennt Physik (60 Hz) sauber vom Render-Rhythmus. */
export class GameLoop {
  private accumulator = 0;
  private lastTime = 0;
  private rafId: number | null = null;
  private running = false;

  constructor(
    private readonly onUpdate: (dt: number) => void,
    private readonly onRender: () => void,
  ) {}

  /** Startet den Loop via requestAnimationFrame — idempotent bei Doppelaufruf. */
  start(): void {
    if (this.running) return;
    this.running = true;
    this.lastTime = performance.now();
    this.accumulator = 0;
    this.rafId = requestAnimationFrame(this.tick);
  }

  /** Stoppt den Loop und cancelt ausstehende Frames. */
  stop(): void {
    this.running = false;
    if (this.rafId !== null) {
      cancelAnimationFrame(this.rafId);
      this.rafId = null;
    }
  }

  private readonly tick = (now: number): void => {
    if (!this.running) return;
    // Cap elapsed to avoid spiral-of-death on tab-blur / breakpoint pauses
    const elapsed = Math.min((now - this.lastTime) / 1000, 0.1);
    this.lastTime = now;
    this.accumulator += elapsed;

    while (this.accumulator >= FIXED_DT) {
      this.onUpdate(FIXED_DT);
      this.accumulator -= FIXED_DT;
    }

    this.onRender();
    this.rafId = requestAnimationFrame(this.tick);
  };
}
