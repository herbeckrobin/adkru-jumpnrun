#!/usr/bin/env node
// Packt das WordPress-Plugin zu release/jumpnrun-<version>.zip.
// Nutzung: node scripts/package-plugin.mjs --version=0.0.1
//
// Das Repo IST das Plugin (flach). Dieses Script stagt nur die WordPress-relevanten Dateien
// in release/jumpnrun/ und zippt sie. Build-Artefakte (assets/game/*) und composer vendor/
// muessen vor dem Lauf vorhanden sein.

import { spawnSync } from 'node:child_process';
import {
  cpSync,
  existsSync,
  mkdirSync,
  readdirSync,
  readFileSync,
  rmSync,
  statSync,
  unlinkSync,
} from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = fileURLToPath(new URL('.', import.meta.url));
const repoRoot = resolve(__dirname, '..');

const versionArg = process.argv.find((a) => a.startsWith('--version='));
const version = versionArg ? versionArg.split('=')[1] : '0.0.0-dev';

/**
 * Die Versionsnummer steht an vier Stellen. Laufen sie auseinander, meldet
 * WordPress dem Kunden ein Update an, das keins ist, oder gar keins, obwohl
 * eines da waere. Genau das ist hier schon passiert: readme.txt stand auf
 * 0.5.0, waehrend das Plugin bei 0.6.4 war.
 */
function assertVersionsInSync(expected) {
  const read = (file) => readFileSync(join(repoRoot, file), 'utf8');

  const found = {
    'jumpnrun.php (Header)': read('jumpnrun.php')
      .match(/^\s*\*\s*Version:\s*(.+)$/m)?.[1]
      ?.trim(),
    'jumpnrun.php (Konstante)': read('jumpnrun.php').match(/JUMPNRUN_VERSION',\s*'([^']+)'/)?.[1],
    'package.json': JSON.parse(read('package.json')).version,
    'readme.txt (Stable tag)': read('readme.txt')
      .match(/^Stable tag:\s*(.+)$/m)?.[1]
      ?.trim(),
  };

  const mismatched = Object.entries(found).filter(([, value]) => value !== expected);
  if (mismatched.length === 0) {
    console.log(`Versionen konsistent: ${expected}`);
    return;
  }

  console.error(`Versions-Mismatch. Erwartet ueberall "${expected}":`);
  for (const [where, value] of Object.entries(found)) {
    console.error(
      `  ${value === expected ? 'ok  ' : 'FEHL'} ${where}: ${value ?? '(nicht gefunden)'}`,
    );
  }
  process.exit(1);
}

if (version !== '0.0.0-dev') {
  assertVersionsInSync(version);
}

/** Loescht Sourcemaps rekursiv, die gehoeren nicht ins Kunden-ZIP. */
function stripSourcemaps(dir) {
  if (!existsSync(dir)) return 0;
  let removed = 0;
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      removed += stripSourcemaps(full);
    } else if (entry.endsWith('.map')) {
      unlinkSync(full);
      removed++;
    }
  }
  return removed;
}

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

const strippedMaps = stripSourcemaps(stage);
if (strippedMaps > 0) {
  console.log(`${strippedMaps} Sourcemap(s) aus dem ZIP entfernt.`);
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
