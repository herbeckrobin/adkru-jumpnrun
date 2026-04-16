import { resolve } from 'node:path';
import { defineConfig } from 'vite';

// Production-Build fuers WordPress-Plugin: buendelt src-js/client.ts → assets/game/client.mjs
export default defineConfig({
  build: {
    outDir: 'assets/game',
    emptyOutDir: true,
    target: 'es2022',
    sourcemap: true,
    lib: {
      entry: resolve(__dirname, 'src-js/client.ts'),
      name: 'Jumpnrun',
      formats: ['es'],
      fileName: () => 'client.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: (info) =>
          info.name?.endsWith('.css') === true ? 'client.css' : '[name][extname]',
      },
    },
  },
});
