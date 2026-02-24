/**
 * Оптимизация и генерация изображений по ключам из config/image-sizes.json.
 * Выходы: WebP в подпапках 800/, 1600/, raw/; манифест data/img/image-dimensions.json.
 * Запуск: npm run build:images
 */

const path = require('path');
const fs = require('fs');
const { glob } = require('glob');
const sharp = require('sharp');

const projectRoot = path.resolve(__dirname, '../..');
const configPath = path.join(projectRoot, 'config/image-sizes.json');
const imgDir = path.join(projectRoot, 'data/img');
const manifestPath = path.join(imgDir, 'image-dimensions.json');

function loadConfig() {
  const raw = fs.readFileSync(configPath, 'utf8');
  const data = JSON.parse(raw);
  const keys = Array.isArray(data.keys) ? data.keys : ['800', '1600', 'raw'];
  const widths = data.widths && typeof data.widths === 'object' ? data.widths : { 800: 800, 1600: 1600, raw: null };
  return { keys, widths };
}

function findRasterFiles() {
  const pattern = path.join(imgDir, '**/*.{jpg,jpeg,png}').replace(/\\/g, '/');
  return glob.sync(pattern, { nodir: true });
}

/**
 * Для пути data/img/restaurants/bear/covers/raw/1.jpg возвращает:
 * - relPath: restaurants/bear/covers/raw/1.jpg
 * - baseDir: restaurants/bear/covers
 * - baseName: 1
 */
function parseImagePath(fullPath) {
  const rel = path.relative(imgDir, fullPath).replace(/\\/g, '/');
  const dir = path.dirname(rel);
  const baseName = path.basename(fullPath, path.extname(fullPath));
  const baseDir = dir.includes('/') ? dir.split('/').slice(0, -1).join('/') : dir || '';
  return { relPath: rel, baseDir, baseName };
}

async function processImage(inputPath, keys, widths, manifest) {
  const { baseDir, baseName } = parseImagePath(inputPath);
  const meta = await sharp(inputPath).metadata();
  const origW = meta.width || 0;

  for (const key of keys) {
    const targetW = widths[key] != null ? Number(widths[key]) : null;
    const outDir = path.join(imgDir, baseDir, key);
    const relDir = path.join(baseDir, key).replace(/\\/g, '/');
    if (!fs.existsSync(outDir)) {
      fs.mkdirSync(outDir, { recursive: true });
    }

    const ext = '.webp';
    const outPath = path.join(outDir, baseName + ext);
    const relKey = (relDir + '/' + baseName + ext).replace(/\\/g, '/');

    let pipeline = sharp(inputPath);
    if (targetW != null && targetW > 0 && origW > targetW) {
      pipeline = pipeline.resize(targetW, null, { withoutEnlargement: true });
    }
    pipeline = pipeline.webp({ quality: 85, effort: 4 });
    await pipeline.toFile(outPath);

    const outMeta = await sharp(outPath).metadata();
    const w = outMeta.width || 0;
    const h = outMeta.height || 0;
    if (w && h) {
      manifest[relKey] = { width: w, height: h };
    }
  }
}

async function main() {
  if (!fs.existsSync(imgDir)) {
    console.log('data/img не найден, пропуск build:images');
    return;
  }

  let keys = ['800', '1600', 'raw'];
  let widths = { 800: 800, 1600: 1600, raw: null };
  if (fs.existsSync(configPath)) {
    const config = loadConfig();
    keys = config.keys;
    widths = config.widths;
  }

  const files = findRasterFiles();
  const manifest = {};

  for (const file of files) {
    try {
      await processImage(file, keys, widths, manifest);
    } catch (err) {
      console.error('Ошибка:', file, err.message);
    }
  }

  fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2), 'utf8');
  console.log('build:images: обработано файлов:', files.length, ', записей в манифесте:', Object.keys(manifest).length);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
