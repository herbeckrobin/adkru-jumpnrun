import type { Rect, SpriteMask } from './types.ts';

/**
 * Contain-fit: scale the sprite's tight bbox uniformly into the slot rect,
 * centered, preserving aspect ratio. Returns the rect where the sprite
 * actually ends up on screen — this is the real hitbox.
 *
 * No mask → returns the slot unchanged (AABB fallback, no aspect info).
 * Mask with zero dimensions (fully transparent) → slot unchanged.
 */
export function fitRect(slot: Rect, mask: SpriteMask | undefined): Rect {
  if (!mask || mask.tightW === 0 || mask.tightH === 0) return slot;
  const scale = Math.min(slot.width / mask.tightW, slot.height / mask.tightH);
  const width = mask.tightW * scale;
  const height = mask.tightH * scale;
  return {
    x: slot.x + (slot.width - width) / 2,
    y: slot.y + (slot.height - height) / 2,
    width,
    height,
  };
}
