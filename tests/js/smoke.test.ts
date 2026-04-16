import { describe, expect, it } from 'vitest';
import { ENGINE_VERSION, hello } from '../../src-js/engine/index.ts';
import { RENDERER_VERSION } from '../../src-js/renderer/index.ts';

describe('smoke', () => {
  it('engine exports a version string', () => {
    expect(ENGINE_VERSION).toMatch(/^\d+\.\d+\.\d+$/);
  });

  it('renderer exports a version string', () => {
    expect(RENDERER_VERSION).toMatch(/^\d+\.\d+\.\d+$/);
  });

  it('engine responds with a ready message', () => {
    expect(hello()).toContain('ready');
  });
});
