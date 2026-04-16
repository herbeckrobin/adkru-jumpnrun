export type GameEventMap = {
  score: { score: number; level: number };
  'level-change': { level: number };
  'game-over': { score: number };
  discount: { code: string; level: number };
  jump: undefined;
};

type Listener<T> = (payload: T) => void;

export class EventBus {
  private readonly listeners = new Map<string, Set<Listener<unknown>>>();

  on<K extends keyof GameEventMap>(event: K, listener: Listener<GameEventMap[K]>): () => void {
    if (!this.listeners.has(event)) this.listeners.set(event, new Set());
    // biome-ignore lint/style/noNonNullAssertion: set in the line above
    const set = this.listeners.get(event)!;
    set.add(listener as Listener<unknown>);
    return () => set.delete(listener as Listener<unknown>);
  }

  emit<K extends keyof GameEventMap>(event: K, payload: GameEventMap[K]): void {
    for (const fn of this.listeners.get(event) ?? []) fn(payload);
  }

  clear(): void {
    this.listeners.clear();
  }
}
