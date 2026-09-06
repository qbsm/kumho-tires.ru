// Пересчёт assets/img/build/image-dimensions.json по УЖЕ сгенерированным вариантам —
// без перекодирования (build:images переписывает бинарники и годится только для ручной
// пересборки; в деплое нужен быстрый идемпотентный шаг, иначе манифест протухает и
// фаза 2 разносит его на прод — инцидент с пропавшей обложкой новости 2026-08-03).
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');
const { glob } = require('glob');

const projectRoot = path.resolve(__dirname, '../..');
const imgDir = path.join(projectRoot, 'data/img');
const configPath = path.join(projectRoot, 'config/image-sizes.json');
const manifestDir = path.join(projectRoot, 'assets/img/build');
const manifestPath = path.join(manifestDir, 'image-dimensions.json');

async function main() {
  if (!fs.existsSync(imgDir)) {
    console.log('data/img не найден, пропуск build:image-manifest');
    return;
  }

  let keys = ['400', '800', '1280', '1600', '1920', '2560'];
  if (fs.existsSync(configPath)) {
    const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
    if (Array.isArray(config.keys)) {
      keys = config.keys;
    }
  }

  const pattern = path.join(imgDir, `**/{${keys.join(',')}}/*.{webp,avif}`).replace(/\\/g, '/');
  const notOgTwin = (f) => {
    const base = path.basename(f, path.extname(f));
    return base !== 'og' && !base.endsWith('-og');
  };
  const files = glob.sync(pattern, { nodir: true }).filter(notOgTwin);

  const manifest = {};
  for (const file of files) {
    const rel = path.relative(imgDir, file).replace(/\\/g, '/');
    try {
      const meta = await sharp(file).metadata();
      if (meta.width && meta.height) {
        manifest[rel] = { width: meta.width, height: meta.height };
      }
    } catch (err) {
      console.error('Ошибка:', rel, err.message);
    }
  }

  fs.mkdirSync(manifestDir, { recursive: true });
  fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2), 'utf8');
  console.log('build:image-manifest: файлов:', files.length, ', записей в манифесте:', Object.keys(manifest).length);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
