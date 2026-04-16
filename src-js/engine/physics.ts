import type { Rect } from './types.ts';

function rectsOverlap(a: Rect, b: Rect, paddingX: number, paddingY: number): boolean {
  return (
    a.x + paddingX < b.x + b.width &&
    a.x + a.width - paddingX > b.x &&
    a.y + paddingY < b.y + b.height &&
    a.y + a.height > b.y - 30
  );
}

/** Shrunken hitbox — forgiving margins so near-misses feel fair */
export function playerHitsObstacle(player: Rect, obstacle: Rect): boolean {
  return rectsOverlap(player, obstacle, 12, 8);
}

export function playerHitsCoin(player: Rect, coin: Rect): boolean {
  return rectsOverlap(player, coin, 6, 4);
}

export function playerLandsOnPlatform(player: Rect, velocityY: number, platform: Rect): boolean {
  const playerBottom = player.y + player.height;
  return (
    player.x + player.width > platform.x &&
    player.x < platform.x + platform.width &&
    playerBottom >= platform.y &&
    playerBottom <= platform.y + 20 &&
    velocityY >= 0
  );
}
