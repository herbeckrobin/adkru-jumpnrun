#!/usr/bin/env node
// Packt das WordPress-Plugin zu release/jumpnrun-<version>.zip.
// Nutzung: node scripts/package-plugin.mjs --version=0.0.1
//
// Das Repo IST das Plugin (flach). Dieses Script stagt nur die WordPress-relevanten Dateien
// in release/jumpnrun/ und zippt sie. Build-Artefakte (assets/game/*) und composer vendor/
// muessen vor dem Lauf vorhanden sein.

import { spawnSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = fileURLToPath(new URL('.', import.meta.url));
const repoRoot = resolve(__dirname, '..');

const versionArg = process.argv.find((a) => a.startsWith('--version='));
const version = versionArg ? versionArg.split('=')[1] : '0.0.0-dev';

const releaseDir = join(repoRoot, 'release');
const stage = join(releaseDir, 'jumpnrun');
const zipPath = join(releaseDir, `jumpnrun-${version}.zip`);

rmSync(releaseDir, { recursive: true, force: true });
mkdirSync(stage, { recursive: true });

// Nur diese Top-Level-Eintraege kommen ins ZIP:
const include = [
  'jumpnrun.php',
  'readme.txt',
  'composer.json',
  'src-php',
  'assets',
  'views',
  'languages',
  'vendor',
];

for (const name of include) {
  const src = join(repoRoot, name);
  if (!existsSync(src)) {
    console.warn(`Warnung: ${name} fehlt — wird uebersprungen.`);
    continue;
  }
  cpSync(src, join(stage, name), { recursive: true });
}

if (!existsSync(join(stage, 'vendor'))) {
  console.warn(
    'Warnung: vendor/ fehlt — bitte `composer install --no-dev` vor dem Packaging laufen lassen.',
  );
}

if (!existsSync(join(stage, 'assets', 'game', 'client.js'))) {
  console.warn(
    'Warnung: assets/game/client.js fehlt — bitte `bun run build` vor dem Packaging laufen lassen.',
  );
}

const result = spawnSync('zip', ['-r', '-q', zipPath, 'jumpnrun'], {
  cwd: releaseDir,
  stdio: 'inherit',
});
if (result.status !== 0) {
  console.error('zip fehlgeschlagen — ist `zip` installiert?');
  process.exit(result.status ?? 1);
}

rmSync(stage, { recursive: true, force: true });

console.log(`\u2713 Erzeugt: ${zipPath}`);
