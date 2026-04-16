import { bootstrap } from '../../src-js/client.ts';

const root = document.getElementById('jumpnrun-root');
if (root) {
  bootstrap(root, {
    // Sprites served from assets/sprites/ via Vite publicDir → flat paths
    images: {
      'bg-0': '/bg-0.png',
      'bg-1': '/bg-1.png',
      'bg-2': '/bg-2.png',
      'bg-3': '/bg-3.png',
      'bg-4': '/bg-4.png',
      'bg-5': '/bg-5.png',
      'bg-6': '/bg-6.png',
      'bg-7': '/bg-7.png',
      'bg-8': '/bg-8.png',
      'bg-9': '/bg-9.png',
      'player-idle': '/player-idle.png',
      'player-jump': '/player-jump.png',
      'obstacle-0': '/obstacle-0.png',
      'obstacle-1': '/obstacle-1.png',
      'obstacle-2': '/obstacle-2.png',
      coin: '/coin.png',
      'platform-0': '/platform-0.png',
      'platform-1': '/platform-1.png',
    },
  });
}
