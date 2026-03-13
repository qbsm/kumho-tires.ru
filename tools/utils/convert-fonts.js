// TTF → WOFF2 conversion with Cyrillic + Latin subset.
// Manual script — NOT part of build pipeline.
// Run: node tools/utils/convert-fonts.js
// Requires: npm install -D fonttools (or Python fonttools with pyftsubset)
//
// This script uses sharp's dependency on wasm-based woff2 compression
// via the 'wawoff2' package, or falls back to shell `woff2_compress`.

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { glob } = require('glob');

const projectRoot = path.resolve(__dirname, '../..');
const fontsDir = path.join(projectRoot, 'assets/fonts');

// Unicode ranges for subset
const UNICODE_RANGES = [
  'U+0000-00FF', // Basic Latin
  'U+0100-024F', // Latin Extended-A/B
  'U+0400-04FF', // Cyrillic
  'U+0500-052F', // Cyrillic Supplement
  'U+2000-206F', // General Punctuation
  'U+2070-209F', // Superscripts/Subscripts
  'U+20A0-20CF', // Currency Symbols
  'U+2100-214F', // Letterlike Symbols
  'U+2200-22FF', // Mathematical Operators
].join(',');

function checkDependencies() {
  try {
    execSync('which pyftsubset', { stdio: 'pipe' });
    return 'pyftsubset';
  } catch {
    // fallback: try woff2_compress without subset
    try {
      execSync('which woff2_compress', { stdio: 'pipe' });
      return 'woff2_compress';
    } catch {
      console.error(
        'Требуется pyftsubset (pip install fonttools brotli) или woff2_compress.\n' +
          'Рекомендуется: pip install fonttools brotli',
      );
      process.exit(1);
    }
  }
}

async function convertWithPyftsubset(ttfPath, woff2Path) {
  const cmd = [
    'pyftsubset',
    `"${ttfPath}"`,
    `--output-file="${woff2Path}"`,
    '--flavor=woff2',
    `--unicodes="${UNICODE_RANGES}"`,
    '--layout-features=*',
    '--desubroutinize',
  ].join(' ');

  execSync(cmd, { stdio: 'pipe' });
}

async function convertWithWoff2Compress(ttfPath, woff2Path) {
  // woff2_compress creates .woff2 next to original
  execSync(`woff2_compress "${ttfPath}"`, { stdio: 'pipe' });
  const autoOutput = ttfPath.replace(/\.ttf$/, '.woff2');
  if (autoOutput !== woff2Path && fs.existsSync(autoOutput)) {
    fs.renameSync(autoOutput, woff2Path);
  }
}

async function main() {
  const tool = checkDependencies();
  console.log(`Используется: ${tool}`);
  if (tool !== 'pyftsubset') {
    console.log('Внимание: без pyftsubset subset (Cyrillic+Latin) не будет применен.');
  }

  const ttfFiles = glob.sync(path.join(fontsDir, '**/*.ttf').replace(/\\/g, '/'), { nodir: true });

  if (ttfFiles.length === 0) {
    console.log('TTF файлы не найдены в assets/fonts/');
    return;
  }

  console.log(`Найдено TTF файлов: ${ttfFiles.length}\n`);

  let converted = 0;
  let skipped = 0;

  for (const ttfPath of ttfFiles) {
    const woff2Path = ttfPath.replace(/\.ttf$/, '.woff2');
    const relPath = path.relative(projectRoot, ttfPath);
    const relWoff2 = path.relative(projectRoot, woff2Path);

    // Skip if woff2 already exists and is newer than ttf
    if (fs.existsSync(woff2Path)) {
      const ttfStat = fs.statSync(ttfPath);
      const woff2Stat = fs.statSync(woff2Path);
      if (woff2Stat.mtimeMs >= ttfStat.mtimeMs) {
        console.log(`  Пропуск (уже есть): ${relWoff2}`);
        skipped++;
        continue;
      }
    }

    try {
      const ttfSize = fs.statSync(ttfPath).size;

      if (tool === 'pyftsubset') {
        await convertWithPyftsubset(ttfPath, woff2Path);
      } else {
        await convertWithWoff2Compress(ttfPath, woff2Path);
      }

      const woff2Size = fs.statSync(woff2Path).size;
      const ratio = ((1 - woff2Size / ttfSize) * 100).toFixed(1);
      console.log(
        `  ${relPath} → ${relWoff2}` +
          `  (${(ttfSize / 1024).toFixed(0)} KB → ${(woff2Size / 1024).toFixed(0)} KB, -${ratio}%)`,
      );
      converted++;
    } catch (err) {
      console.error(`  Ошибка: ${relPath} — ${err.message}`);
    }
  }

  console.log(`\nГотово: конвертировано ${converted}, пропущено ${skipped}`);
  console.log(
    '\nСледующий шаг: обновите assets/css/base/fonts.css:' +
      "\n  .ttf → .woff2, format('truetype') → format('woff2')",
  );
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
