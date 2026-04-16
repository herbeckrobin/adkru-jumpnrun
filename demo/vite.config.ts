import { resolve } from 'node:path';
import { defineConfig } from 'vite';

// Dev-Server fuer die Standalone-Demo (ohne WordPress)
export default defineConfig({
  root: resolve(__dirname),
  // Serve assets/sprites/ as static files at root: /bg-0.png, /player-idle.png etc.
  publicDir: resolve(__dirname, '..', 'assets', 'sprites'),
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
