import { fitRect } from './fit.ts';
import type { Rect, SpriteMask } from './types.ts';

/** Sample step in screen pixels for the per-pixel overlap scan. 2 = every other px. */
const PIXEL_SCAN_STEP = 2;

interface Maskable extends Rect {
  mask?: SpriteMask | undefined;
}

/** AABB overlap with a symmetric inset (positive = shrink, negative = grow). */
function aabbOverlap(a: Rect, b: Rect, insetA: number, insetB: number): boolean {
  return (
    a.x + insetA < b.x + b.width - insetB &&
    a.x + a.width - insetA > b.x + insetB &&
    a.y + insetA < b.y + b.height - insetB &&
    a.y + a.height - insetA > b.y + insetB
  );
}

/**
 * Sample a sprite mask at a world point. The mask is stored in the sprite's
 * native resolution; the rect is the on-screen AABB, so we map back into
 * mask coords through the tight bbox ratio.
 */
function maskSolidAt(rect: Rect, mask: SpriteMask, worldX: number, worldY: number): boolean {
  // Renderer crops the sprite to its tight bbox and scales that into the rect,
  // so the rect = visible sprite. We map world coords back through that crop.
  const scaleX = rect.width / mask.tightW;
  const scaleY = rect.height / mask.tightH;
  const mx = Math.floor((worldX - rect.x) / scaleX) + mask.tightX;
  const my = Math.floor((worldY - rect.y) / scaleY) + mask.tightY;
  if (mx < 0 || my < 0 || mx >= mask.width || my >= mask.height) return false;
  return mask.mask[my * mask.width + mx] === 1;
}

/**
 * Per-pixel overlap: walks the rectangle where both AABBs overlap and returns
 * true as soon as one sampled pixel is solid in *both* masks. Early-exits fast.
 * If either side has no mask, falls back to plain AABB (already checked by caller).
 */
function masksOverlap(a: Maskable, b: Maskable): boolean {
  if (!a.mask || !b.mask) return true;
  const left = Math.max(a.x, b.x);
  const right = Math.min(a.x + a.width, b.x + b.width);
  const top = Math.max(a.y, b.y);
  const bottom = Math.min(a.y + a.height, b.y + b.height);
  if (right <= left || bottom <= top) return false;

  for (let y = top; y < bottom; y += PIXEL_SCAN_STEP) {
    for (let x = left; x < right; x += PIXEL_SCAN_STEP) {
      if (maskSolidAt(a, a.mask, x, y) && maskSolidAt(b, b.mask, x, y)) {
        return true;
      }
    }
  }
  return false;
}

/**
 * Player vs. obstacle. Broadphase: AABB with `buffer` px inset on both rects
 * (forgiving). Narrowphase: per-pixel mask overlap if both sprites have masks.
 */
export function playerHitsObstacle(
  player: Maskable,
  obstacle: Maskable,
  buffer: number,
): boolean {
  const p: Maskable = { ...fitRect(player, player.mask), mask: player.mask };
  const o: Maskable = { ...fitRect(obstacle, obstacle.mask), mask: obstacle.mask };
  if (!aabbOverlap(p, o, buffer, buffer)) return false;
  return masksOverlap(p, o);
}

/**
 * Player vs. coin. Coins are small and roughly circular — pure AABB with a
 * `magnet` inset (negative = grows coin rect, easier to pick up) is plenty.
 */
export function playerHitsCoin(player: Maskable, coin: Maskable, magnet: number): boolean {
  const p = fitRect(player, player.mask);
  const c = fitRect(coin, coin.mask);
  return aabbOverlap(p, c, 0, -magnet);
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
