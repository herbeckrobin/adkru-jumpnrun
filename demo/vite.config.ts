import { resolve } from 'node:path';
import { defineConfig } from 'vite';

// Dev-Server fuer die Standalone-Demo (ohne WordPress)
export default defineConfig({
  root: resolve(__dirname),
  // Serve legacy/images/ as static files: /carry/logo-1.png, /coin/coin.png, etc.
  publicDir: resolve(__dirname, '..', 'legacy', 'images'),
  resolve: {
    alias: {
      '@engine': resolve(__dirname, '..', 'src-js', 'engine'),
      '@renderer': resolve(__dirname, '..', 'src-js', 'renderer'),
    },
  },
  server: {
    port: 5173,
  },
});
