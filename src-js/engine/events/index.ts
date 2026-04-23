/** Event-Namen auf Payload-Typen — fuer type-sichere Listener. */
export type GameEventMap = {
  score: { score: number; level: number };
  'level-change': { level: number };
  'game-over': { score: number };
  discount: { code: string; level: number };
  jump: undefined;
};

type Listener<T> = (payload: T) => void;

/** Minimaler typisierter Event-Bus zwischen Engine und UI-Schicht. */
export class EventBus {
  private readonly listeners = new Map<string, Set<Listener<unknown>>>();

  /** Registriert einen Listener und liefert einen Unsubscribe-Callback zurueck. */
  on<K extends keyof GameEventMap>(event: K, listener: Listener<GameEventMap[K]>): () => void {
    if (!this.listeners.has(event)) this.listeners.set(event, new Set());
    // biome-ignore lint/style/noNonNullAssertion: set in the line above
    const set = this.listeners.get(event)!;
    set.add(listener as Listener<unknown>);
    return () => set.delete(listener as Listener<unknown>);
  }

  /** Feuert das Event an alle registrierten Listener. */
  emit<K extends keyof GameEventMap>(event: K, payload: GameEventMap[K]): void {
    for (const fn of this.listeners.get(event) ?? []) fn(payload);
  }

  /** Entfernt alle Listener — fuer Teardown und Tests. */
  clear(): void {
    this.listeners.clear();
  }
}
