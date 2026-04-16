import { ENGINE_VERSION, hello } from './engine/index.ts';
import { RENDERER_VERSION } from './renderer/index.ts';

export function bootstrap(canvas: HTMLCanvasElement): void {
  const ctx = canvas.getContext('2d');
  if (!ctx) return;
  ctx.fillStyle = '#eee';
  ctx.font = '16px system-ui, sans-serif';
  ctx.fillText(`${hello()} — engine ${ENGINE_VERSION}, renderer ${RENDERER_VERSION}`, 20, 40);
}

declare global {
  interface Window {
    Jumpnrun?: { bootstrap: typeof bootstrap };
  }
}

if (typeof window !== 'undefined') {
  window.Jumpnrun = { bootstrap };
}
