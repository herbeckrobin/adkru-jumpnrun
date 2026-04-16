import { bootstrap } from '../../src-js/client.ts';

const root = document.getElementById('jumpnrun-root');
if (root) {
  bootstrap(root, {
    // Sprites are served from legacy/images/ via Vite publicDir
    images: {
      // Backgrounds (10 levels)
      'bg-0': '/background/hintergrund_level_1.png',
      'bg-1': '/background/hintergrund_level_2.png',
      'bg-2': '/background/hintergrund_level_3.png',
      'bg-3': '/background/hintergrund_level_4.png',
      'bg-4': '/background/hintergrund_level_5.png',
      'bg-5': '/background/hintergrund_level_6.png',
      'bg-6': '/background/hintergrund_level_7.png',
      'bg-7': '/background/hintergrund_level_8.png',
      'bg-8': '/background/hintergrund_level_9.png',
      'bg-9': '/background/hintergrund_level_10.png',
      // Player
      'player-idle': '/carry/logo-1.png',
      'player-jump': '/carry/logo-1-jumpneuneu.png',
      // Obstacles
      'obstacle-0': '/Obstacles/obstacle1.png',
      'obstacle-1': '/Obstacles/obstacle2.png',
      'obstacle-2': '/Obstacles/obstacle3.png',
      // Collectible
      coin: '/coin/coin.png',
      // Platforms
      'platform-0': '/schwebeebene/schwebeebene.png',
      'platform-1': '/schwebeebene/dritteebene.png',
    },
  }).catch(console.error);
}
