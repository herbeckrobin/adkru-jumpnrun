import type { SpriteMask } from '../engine/types.ts';

/** Sprite-Key → geladenes Image-Element. */
export type ImageMap = Map<string, HTMLImageElement>;
/** Sprite-Key → zugehoerige Pixel-Alpha-Maske fuer praezise Hitboxen. */
export type MaskMap = Map<string, SpriteMask>;

/** Alpha threshold (0-255) for a pixel to count as solid in the hitbox mask. */
const ALPHA_THRESHOLD = 128;

/**
 * Load once with `crossOrigin='anonymous'` so the canvas can read pixels.
 * If the server refuses CORS the request errors — we retry without the
 * attribute so the image at least *displays*, even though the mask build
 * will fail (tainted canvas → `getImageData` throws, caught below).
 */
function loadImage(url: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img);
    img.onerror = () => {
      const retry = new Image();
      retry.onload = () => resolve(retry);
      retry.onerror = () => reject(new Error(`Image load failed: ${url}`));
      retry.src = url;
    };
    img.src = url;
  });
}

/**
 * Reads the image into an offscreen canvas and builds a 1-byte-per-pixel
 * alpha mask plus the tight bounding box of opaque pixels. Returns null if
 * the canvas is tainted (CORS) — caller then falls back to AABB-only.
 */
function buildMask(img: HTMLImageElement): SpriteMask | null {
  const w = img.naturalWidth;
  const h = img.naturalHeight;
  if (w === 0 || h === 0) return null;

  const canvas = document.createElement('canvas');
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext('2d');
  if (!ctx) return null;
  ctx.drawImage(img, 0, 0);

  let data: Uint8ClampedArray;
  try {
    data = ctx.getImageData(0, 0, w, h).data;
  } catch (err) {
    console.warn(`[jumpnrun] Mask-Build fehlgeschlagen (Canvas tainted? CORS?): ${img.src}`, err);
    return null;
  }

  const mask = new Uint8Array(w * h);
  let minX = w;
  let minY = h;
  let maxX = -1;
  let maxY = -1;

  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const alpha = data[(y * w + x) * 4 + 3] ?? 0;
      if (alpha >= ALPHA_THRESHOLD) {
        mask[y * w + x] = 1;
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
      }
    }
  }

  if (maxX < 0) return null; // fully transparent

  return {
    width: w,
    height: h,
    tightX: minX,
    tightY: minY,
    tightW: maxX - minX + 1,
    tightH: maxY - minY + 1,
    mask,
  };
}

/**
 * Loads all provided image URLs in parallel and builds alpha masks for each.
 * Images that fail to load are silently skipped; sprites whose masks can't be
 * built (CORS-tainted etc.) just won't get a mask and fall back to AABB.
 */
/** Laed alle Bilder parallel und baut pro Bild eine Alpha-Maske fuer die pixelgenaue Hitbox. */
export async function loadImages(
  entries: Record<string, string>,
): Promise<{ images: ImageMap; masks: MaskMap }> {
  const images: ImageMap = new Map();
  const masks: MaskMap = new Map();
  await Promise.allSettled(
    Object.entries(entries).map(async ([key, url]) => {
      try {
        const img = await loadImage(url);
        images.set(key, img);
        const m = buildMask(img);
        if (m) masks.set(key, m);
      } catch (err) {
        console.warn(`[jumpnrun] Sprite nicht geladen: ${key} → ${url}`, err);
      }
    }),
  );
  console.info(`[jumpnrun] Sprites geladen: ${images.size} | Masken gebaut: ${masks.size}`, {
    maskKeys: Array.from(masks.keys()),
  });
  return { images, masks };
}
