import { ENGINE_VERSION, hello } from '../../src-js/engine/index.ts';
import { RENDERER_VERSION } from '../../src-js/renderer/index.ts';

const canvas = document.getElementById('game') as HTMLCanvasElement | null;
if (canvas) {
  const ctx = canvas.getContext('2d');
  if (ctx) {
    ctx.fillStyle = '#eee';
    ctx.font = '16px system-ui, sans-serif';
    ctx.fillText(`${hello()} — engine ${ENGINE_VERSION}, renderer ${RENDERER_VERSION}`, 20, 40);
  }
}
