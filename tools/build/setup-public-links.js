/**
 * Создаёт в public/ симлинки: assets → ../assets, data → ../data, robots.txt → ../robots.txt.
 * Запуск: npm run setup:public-links или при сборке (build/build:dev).
 */
const path = require('path');
const fs = require('fs');

const projectRoot = path.resolve(__dirname, '../..');
const publicDir = path.join(projectRoot, 'public');

const LINKS = [
  { link: 'assets', target: '../assets', type: 'dir' },
  { link: 'data', target: '../data', type: 'dir' },
  { link: 'robots.txt', target: '../robots.txt', type: 'file' },
];

function ensurePublicDir() {
  if (!fs.existsSync(publicDir)) {
    fs.mkdirSync(publicDir, { recursive: true });
  }
}

function createSymlink(linkPath, targetRel, type) {
  const targetAbs = path.resolve(path.dirname(linkPath), targetRel);
  if (!fs.existsSync(targetAbs)) {
    console.warn(`setup-public-links: цель не найдена, пропуск: ${targetRel} → ${linkPath}`);
    return;
  }
  if (fs.existsSync(linkPath)) {
    const stat = fs.lstatSync(linkPath);
    if (stat.isSymbolicLink()) {
      const current = fs.readlinkSync(linkPath);
      if (path.resolve(path.dirname(linkPath), current) === targetAbs) {
        return;
      }
    }
    fs.unlinkSync(linkPath);
  }
  fs.symlinkSync(targetRel, linkPath, type === 'dir' ? 'dir' : 'file');
  console.log(`  ${path.relative(projectRoot, linkPath)} → ${targetRel}`);
}

function main() {
  ensurePublicDir();
  console.log('Симлинки в public/:');
  for (const { link, target, type } of LINKS) {
    const linkPath = path.join(publicDir, link);
    try {
      createSymlink(linkPath, target, type);
    } catch (err) {
      console.error(`Ошибка при создании ${link}:`, err.message);
      process.exitCode = 1;
    }
  }
}

main();
