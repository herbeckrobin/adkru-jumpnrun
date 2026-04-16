export type ImageMap = Map<string, HTMLImageElement>;

function loadImage(url: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error(`Image load failed: ${url}`));
    img.src = url;
  });
}

/**
 * Loads all provided image URLs in parallel.
 * Images that fail to load are silently skipped;
 * the renderer will draw fallback colored rectangles instead.
 */
export async function loadImages(entries: Record<string, string>): Promise<ImageMap> {
  const map: ImageMap = new Map();
  await Promise.allSettled(
    Object.entries(entries).map(async ([key, url]) => {
      try {
        map.set(key, await loadImage(url));
      } catch (err) {
        console.warn(`[jumpnrun] Sprite nicht geladen: ${key} → ${url}`, err);
      }
    }),
  );
  return map;
}
