import { describe, expect, it } from 'vitest';
import { ENGINE_VERSION, GameWorld } from '../../src-js/engine/index.ts';
import { RENDERER_VERSION } from '../../src-js/renderer/index.ts';

describe('smoke', () => {
  it('engine exports a version string', () => {
    expect(ENGINE_VERSION).toMatch(/^\d+\.\d+\.\d+$/);
  });

  it('renderer exports a version string', () => {
    expect(RENDERER_VERSION).toMatch(/^\d+\.\d+\.\d+$/);
  });

  it('GameWorld starts in idle status', () => {
    const world = new GameWorld();
    expect(world.state.status).toBe('idle');
  });

  it('GameWorld transitions to running after start()', () => {
    const world = new GameWorld();
    world.start();
    expect(world.state.status).toBe('running');
  });

  it('GameWorld emits game-over event', () => {
    const world = new GameWorld();
    world.start();
    let fired = false;
    world.events.on('game-over', () => {
      fired = true;
    });
    // Force a hit by placing an obstacle directly on the player
    const state = world.state;
    // Access internal via cast — white-box test acceptable for smoke
    (world as unknown as { _obstacles: unknown[] })._obstacles.push({
      x: state.player.x,
      y: state.player.y,
      width: state.player.width,
      height: state.player.height,
      imageKey: 'obstacle-0',
    });
    world.update(1 / 60);
    expect(fired).toBe(true);
    expect(world.state.status).toBe('gameover');
  });
});
