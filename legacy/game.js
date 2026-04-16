// game.js – angepasst für MySQL-Highscore über save_score.php & get_highscores.php

let player,
  obstacles = [],
  coins = [],
  platforms = [];
let score = 0,
  level = 1,
  gameOver = false;
let playerName = '',
  obstacleTimeout,
  coinTimeout;
let showLevelText = false;
let levelTextTimer = null;
let paused = false;
let sessionBestScore = 0;
let highscoreList = [];

const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');

const overlay = document.getElementById('overlay');
const gameOverOverlay = document.getElementById('gameOverOverlay');
const gameOverText = document.getElementById('gameOverText');
const gameOverDetails = document.getElementById('gameOverDetails');
const restartButton = document.getElementById('restartButton');
const discountPopup = document.getElementById('discountPopup');
const playerNameInput = document.getElementById('playerName');
const startButton = document.getElementById('startButton');
const closeDiscountPopupBtn = document.getElementById('closeDiscountPopup');
const jumpButton = document.getElementById('jumpButton');
const touchHint = document.getElementById('touchHint');

function sanitizeInput(str) {
  let sanitized = str.replace(/[^a-zA-Z0-9 ]/g, '').trim();
  if (sanitized.length > 15) {
    sanitized = sanitized.substring(0, 15);
  }
  return sanitized;
}

function createFallbackImage(color, width, height) {
  const tempCanvas = document.createElement('canvas');
  tempCanvas.width = width;
  tempCanvas.height = height;
  const tempCtx = tempCanvas.getContext('2d');
  tempCtx.fillStyle = color;
  tempCtx.fillRect(0, 0, width, height);
  const img = new Image();
  img.src = tempCanvas.toDataURL();
  return img;
}

const playerIdleImage = new Image();
playerIdleImage.src = 'http://localhost:8888/images/carry/logo-1.png';

const playerJumpImage = new Image();
playerJumpImage.src = 'http://localhost:8888/images/carry/logo-1-jumpneuneu.png';

const obstacleImageNames = ['obstacle1.png', 'obstacle2.png', 'obstacle3.png'];
const obstacleImages = obstacleImageNames.map((name) => {
  const img = new Image();
  img.src = `http://localhost:8888/images/obstacles/${name}`;
  img.onerror = () => {
    img.src = createFallbackImage('#ff0000', 50, 50).src;
  };
  return img;
});

const coinImage = new Image();
coinImage.src = 'http://localhost:8888/images/coin/coin.png';
coinImage.onerror = () => {
  coinImage.src = createFallbackImage('#ffff00', 40, 40).src;
};

const platformImage1 = new Image();
platformImage1.src = 'http://localhost:8888/images/schwebeebene/schwebeebene.png';
platformImage1.onerror = () => {
  platformImage1.src = createFallbackImage('#888888', 80, 20).src;
};

const platformImage2 = new Image();
platformImage2.src = 'http://localhost:8888/images/schwebeebene/dritteebene.png';
platformImage2.onerror = () => {
  platformImage2.src = createFallbackImage('#555555', 80, 20).src;
};

const levelBackgrounds = Array.from(
  { length: 10 },
  (_, i) => `http://localhost:8888/images/background/hintergrund_level_${i + 1}.png`,
);
const backgroundImage = new Image();
let backgroundX = 0;

const imagesToLoad = [
  playerIdleImage,
  playerJumpImage,
  ...obstacleImages,
  coinImage,
  platformImage1,
  platformImage2,
];

let imagesLoadedCount = 0;
imagesToLoad.forEach((img) => {
  img.onload = () => {
    imagesLoadedCount++;
    if (imagesLoadedCount === imagesToLoad.length) {
      backgroundImage.src = levelBackgrounds[0];
      if (!playerName) {
        overlay.style.display = 'flex';
      }
    }
  };
  img.onerror = () => {
    img.src = createFallbackImage('#ff0000', 50, 50).src;
  };
});

function initPlayer() {
  player = {
    x: 100,
    y: canvas.height - 250,
    width: 180,
    height: 200,
    image: playerIdleImage,
    velocityY: 0,
    jumpCount: 0,
  };
}

function createObstacle() {
  if (gameOver) return;
  const image = obstacleImages[Math.floor(Math.random() * obstacleImages.length)];
  const width = 110 + Math.random() * 50;
  const height = 110 + Math.random() * 50;

  const obstacle = {
    x: canvas.width + 50,
    y: canvas.height - 18 - height,
    width,
    height,
    image,
  };
  obstacles.push(obstacle);

  const speed = 3 + level * 0.3;
  const minPixelDistance = 300;
  const minTime = (minPixelDistance / speed) * (1000 / 60);
  const nextTime = minTime + Math.random() * 1500;

  obstacleTimeout = setTimeout(createObstacle, nextTime);
}

function createCoin() {
  if (gameOver) return;
  const coin = {
    x: canvas.width + Math.random() * 300,
    y: canvas.height - 200 - Math.random() * 150,
    width: 50,
    height: 50,
    image: coinImage,
    collected: false,
  };
  coins.push(coin);
  coinTimeout = setTimeout(createCoin, 800 + Math.random() * 1500);
}

function createPlatforms() {
  platforms = [];
  coins = coins.filter((c) => !c.collected);
  for (let i = 0; i < 4; i++) {
    const x = canvas.width + i * 1400;
    const y = canvas.height - 200 - Math.random() * 200;
    const platformImage = i % 2 === 0 ? platformImage1 : platformImage2;
    platforms.push({ x, y, width: 80, height: 20, image: platformImage });
    coins.push({
      x: x + 50,
      y: y - 40,
      width: 50,
      height: 50,
      image: coinImage,
      collected: false,
    });
  }
}

function collision(a, b) {
  const paddingX = 80; // horizontal weniger empfindlich
  const paddingY = 20; // vertikal bleibt etwas enger
  const aBottom = a.y + a.height;
  const bBottom = b.y + b.height;

  return (
    a.x + paddingX < b.x + b.width &&
    a.x + a.width - paddingX > b.x &&
    a.y + paddingY < bBottom &&
    aBottom > b.y - 30
  );
}

function moveObstacles() {
  const speed = 3 + level * 0.3;
  for (let i = obstacles.length - 1; i >= 0; i--) {
    const o = obstacles[i];
    o.x -= speed;
    ctx.drawImage(o.image, o.x, o.y, o.width, o.height);
    if (o.x + o.width < 0) obstacles.splice(i, 1);
    if (collision(player, o)) showGameOver();
  }
}

function moveCoins() {
  const speed = 3 + level * 0.3;
  for (let i = coins.length - 1; i >= 0; i--) {
    const c = coins[i];
    if (c.collected) continue;
    c.x -= speed;
    ctx.drawImage(c.image, c.x, c.y, c.width, c.height);
    if (c.x + c.width < 0) coins.splice(i, 1);
    else if (collision(player, c)) {
      score++;
      c.collected = true;
      coins.splice(i, 1);

      if (score > sessionBestScore) {
        sessionBestScore = score;
        saveHighscore(playerName, sessionBestScore);
      }

      // Levelwechsel nach 5 Punkten
      const newLevel = Math.min(10, Math.floor(score / 5) + 1);
      if (newLevel !== level) {
        level = newLevel;
        backgroundImage.src = levelBackgrounds[level - 1];
        showLevelText = true;
        if (levelTextTimer) clearTimeout(levelTextTimer);
        levelTextTimer = setTimeout(() => {
          showLevelText = false;
        }, 2000);

        // Pop-up anzeigen und Spiel pausieren bei Level 3
        if (level === 3 && discountPopup) {
          paused = true;
          discountPopup.style.display = 'block';
        }
      }
    }
  }
}

function drawPlatforms() {
  const speed = 3 + level * 0.2;
  platforms.forEach((p) => {
    p.x -= speed;
    ctx.drawImage(p.image, p.x, p.y, p.width, p.height);
  });
  platforms = platforms.filter((p) => p.x + p.width > 0);
}

function draw() {
  if (gameOver || paused) return;
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  backgroundX -= 1.5 + level * 0.5;
  if (backgroundX <= -canvas.width) backgroundX = 0;

  ctx.drawImage(backgroundImage, backgroundX, 0, canvas.width, canvas.height);
  ctx.drawImage(backgroundImage, backgroundX + canvas.width, 0, canvas.width, canvas.height);
  drawPlatforms();

  player.velocityY += 1.2;
  player.y += player.velocityY;

  let onPlatform = false;
  for (const p of platforms) {
    const playerBottom = player.y + player.height;
    if (
      player.x + player.width > p.x &&
      player.x < p.x + p.width &&
      playerBottom >= p.y &&
      playerBottom <= p.y + 20 &&
      player.velocityY >= 0
    ) {
      player.y = p.y - player.height;
      player.velocityY = 0;
      player.jumpCount = 0;
      onPlatform = true;
      break;
    }
  }

  const groundY = canvas.height - 50;
  if (!onPlatform && player.y + player.height >= groundY) {
    player.y = groundY - player.height;
    player.velocityY = 0;
    player.jumpCount = 0;
  }

  ctx.drawImage(player.image, player.x, player.y, player.width, player.height);
  moveObstacles();
  moveCoins();

  ctx.fillStyle = '#fff';
  ctx.font = '20px Arial';
  ctx.fillText(`Score: ${score}`, 10, 30);
  ctx.fillText(`Level: ${level}`, 10, 60);
  ctx.textAlign = 'right';

  ctx.textAlign = 'left';

  if (showLevelText) {
    ctx.fillStyle = 'yellow';
    ctx.font = '40px Arial';
    ctx.textAlign = 'center';
    ctx.fillText(`Level ${level}`, canvas.width / 2, canvas.height / 2 - 100);
    ctx.textAlign = 'left';
  }

  requestAnimationFrame(draw);
}

function jump() {
  if (gameOver || paused || !player) return;
  player.velocityY = -30;
  player.jumpCount++;
  player.image = playerJumpImage;
  setTimeout(() => {
    player.image = playerIdleImage;
  }, 300);
}

playerNameInput.addEventListener('input', () => {
  const sanitized = sanitizeInput(playerNameInput.value);
  if (playerNameInput.value !== sanitized) {
    playerNameInput.value = sanitized;
  }
});

startButton.onclick = () => {
  if (!playerName) {
    playerName = sanitizeInput(playerNameInput.value);
    if (!playerName) {
      alert('Bitte gib einen gültigen Spielernamen ein.');
      return;
    }
  }

  overlay.style.display = 'none';
  startNewGame();
  if (isMobile()) showTouchHint();
};

// 👇 Hier hinzufügen:
playerNameInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    startButton.click();
  }
});

restartButton.onclick = () => {
  overlay.style.display = 'none';
  startNewGame();
};

// ✅ Pop-up schließen + 3,2,1 Countdown
closeDiscountPopupBtn.onclick = () => {
  discountPopup.style.display = 'none';
  paused = true;
  let countdown = 3;

  const countdownInterval = setInterval(() => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Hintergrund komplett zeichnen
    ctx.drawImage(backgroundImage, backgroundX, 0, canvas.width, canvas.height);
    ctx.drawImage(backgroundImage, backgroundX + canvas.width, 0, canvas.width, canvas.height);

    // Plattformen & Spieler
    drawPlatforms();
    ctx.drawImage(player.image, player.x, player.y, player.width, player.height);

    // Score & Level
    ctx.fillStyle = '#fff';
    ctx.font = '20px Arial';
    ctx.textAlign = 'left';
    ctx.fillText(`Score: ${score}`, 10, 30);
    ctx.fillText(`Level: ${level}`, 10, 60);

    // Countdown-Zahl in der Mitte
    ctx.fillStyle = 'yellow';
    ctx.font = '80px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(countdown, canvas.width / 2, canvas.height / 2);

    // Nach dem Countdown-Zeichnen Textausrichtung zurücksetzen
    ctx.textAlign = 'left';
    ctx.textBaseline = 'alphabetic';

    countdown--;

    if (countdown < 0) {
      clearInterval(countdownInterval);
      paused = false;
      draw();
    }
  }, 1000);
};

jumpButton.addEventListener('click', jump);

document.addEventListener('keydown', (e) => {
  if (e.code === 'Space') jump();
});

function isMobile() {
  return /Android|iPhone|iPad|iPod|Opera Mini|IEMobile|WPDesktop/i.test(navigator.userAgent);
}

function showTouchHint() {
  if (!isMobile() || !touchHint) return;
  touchHint.textContent = 'Touch to Jump';
  touchHint.classList.add('visible');
  setTimeout(() => touchHint.classList.remove('visible'), 4000);
}

function addTouchControls() {
  document.addEventListener('touchstart', touchJumpHandler, { passive: false });
}
function removeTouchControls() {
  document.removeEventListener('touchstart', touchJumpHandler);
}
function touchJumpHandler(e) {
  e.preventDefault();
  jump();
}

function typeGameOverText(callback) {
  const text = 'Game Over';
  gameOverText.innerHTML = '';
  gameOverText.style.display = 'block';
  let i = 0;
  const interval = setInterval(() => {
    gameOverText.textContent += text.charAt(i);
    i++;
    if (i >= text.length) {
      clearInterval(interval);
      if (callback) callback();
    }
  }, 150);
}

function showGameOver() {
  gameOver = true;
  clearTimeout(obstacleTimeout);
  clearTimeout(coinTimeout);
  gameOverOverlay.classList.remove('hidden');
  removeTouchControls();

  gameOverText.style.display = 'none';
  gameOverDetails.style.display = 'none';
  restartButton.style.display = 'none';

  document.getElementById('finalHighscoreText').textContent = `${sessionBestScore} (${playerName})`;

  typeGameOverText(() => {
    gameOverDetails.style.display = 'block';
    restartButton.style.display = 'inline-block';
    loadHighscores();
  });
}

function startNewGame() {
  gameOver = false;
  score = 0;
  level = 1;
  obstacles = [];
  coins = [];
  platforms = [];
  backgroundImage.src = levelBackgrounds[0];
  initPlayer();
  createObstacle();
  createCoin();
  createPlatforms();
  gameOverOverlay.classList.add('hidden');
  restartButton.style.display = 'none';
  addTouchControls();
  draw();
}

// ---------------------------------------------------------
// Highscore-Funktionen (Serverkommunikation über PHP)
// ---------------------------------------------------------

async function saveHighscore(name, score) {
  try {
    const formData = new URLSearchParams();
    formData.append('name', name);
    formData.append('score', score);

    const response = await fetch('save_score.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString(),
    });

    const result = await response.json();

    if (result.status === 'ok') {
      console.log('✅ Highscore gespeichert:', score);
      loadHighscores();
    } else {
      console.warn('⚠️ Highscore konnte nicht gespeichert werden:', result.message);
    }
  } catch (error) {
    console.error('❌ Fehler beim Speichern des Highscores:', error);
  }
}

async function loadHighscores() {
  try {
    const response = await fetch('get_highscores.php');
    const result = await response.json();

    if (result.status === 'ok' && Array.isArray(result.data)) {
      highscoreList = result.data;
      displayHighscores(result.data);
    } else {
      console.error('⚠️ Ungültige Serverantwort:', result);
    }
  } catch (error) {
    console.error('❌ Fehler beim Laden der Highscores:', error);
  }
}

function displayHighscores(highscores) {
  const container = document.getElementById('highscoreList');
  if (!container) return;

  let html = '<ol>';
  highscores.forEach((entry, index) => {
    html += `<li>
      <span class="rank">${index + 1}.</span>
      <span class="name">${entry.name}</span>
      <span class="score">${entry.score}</span>
    </li>`;
  });
  html += '</ol>';
  container.innerHTML = html;
}
