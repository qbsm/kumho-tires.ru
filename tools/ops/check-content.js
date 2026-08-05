#!/usr/bin/env node
/**
 * Проверка согласованности контента: ловит расхождения, которые накапливаются
 * при ручных правках текстов в разных местах.
 *
 * Что проверяется:
 *   1. FAQ: видимый текст (desc) совпадает с answerText — иначе FAQPage-разметка
 *      противоречит контенту страницы.
 *   2. Цифры дилерской сети (точки продаж, города, регионы) в текстах и llms-фидах
 *      совпадают с фактическими по dealers.json.
 *   3. Цифры каталога (модели, типоразмеры) совпадают с фактическими по data/json/{lang}/tires.
 *   4. Шаги конфигуратора одинаковы на всех страницах, где он стоит.
 *   5. Все страницы из sitemap_pages перечислены в llms.txt.
 *   6. llms-full.txt не протух: перегенерация даёт тот же файл.
 *
 * Использование: npm run check:content
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '../..');
const LANG = 'ru';
const DATA = path.join(ROOT, 'data/json');

const problems = [];
const notes = [];

const fail = (msg) => problems.push(msg);
const readJson = (file) => JSON.parse(fs.readFileSync(file, 'utf8'));
const stripTags = (html) =>
  String(html)
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const pagesDir = path.join(DATA, LANG, 'pages');
const pageFiles = fs.readdirSync(pagesDir).filter((f) => f.endsWith('.json'));

// --- 1. FAQ: desc и answerText должны совпадать ---------------------------------
pageFiles.forEach((file) => {
  const page = readJson(path.join(pagesDir, file));
  (page.sections || []).forEach((section) => {
    if (section.name !== 'faq') return;
    (section.data?.items || []).forEach((item) => {
      if (!item.answerText) return;
      if (stripTags(item.desc) !== stripTags(item.answerText)) {
        fail(
          `FAQ «${item.title}» (${file}): desc и answerText разошлись — разметка FAQPage не совпадёт с видимым текстом`
        );
      }
    });
  });
});

// --- 2. Цифры дилерской сети ----------------------------------------------------
const dealers = readJson(path.join(pagesDir, 'dealers.json')).items || [];
const visible = dealers.filter((d) => d.visible !== false);
const facts = {
  'точек продаж': visible.length,
  городов: new Set(visible.map((d) => (d.city || '').trim()).filter(Boolean)).size,
  регионов: new Set(visible.map((d) => (d.region || '').trim()).filter(Boolean)).size,
};

// --- 3. Цифры каталога ----------------------------------------------------------
const tiresDir = path.join(DATA, LANG, 'tires');
const tires = fs
  .readdirSync(tiresDir)
  .filter((f) => f.endsWith('.json'))
  .map((f) => readJson(path.join(tiresDir, f)))
  .filter((t) => t.visible);
facts['моделей'] = tires.length;
facts['типоразмеров'] = tires.reduce((sum, t) => sum + (t.sizes || []).length, 0);

const sources = [
  ...pageFiles.map((f) => ({ label: `pages/${f}`, text: fs.readFileSync(path.join(pagesDir, f), 'utf8') })),
  { label: 'public/llms.txt', text: fs.readFileSync(path.join(ROOT, 'public/llms.txt'), 'utf8') },
  { label: 'config/llms-full.php', text: fs.readFileSync(path.join(ROOT, 'config/llms-full.php'), 'utf8') },
];

// Сверяем только заявленные ИТОГИ. Частные цифры («9 моделей» про зимние,
// «136 типоразмеров» у WP52+) легитимны и под проверку не попадают.
const TOTAL_CLAIMS = [
  { re: /(\d[\d\s]*)\s+(?:авторизованн\w+\s+)?точ(?:ках|ек|ки)\s+продаж/g, unit: 'точек продаж' },
  { re: /в\s+(\d[\d\s]*)\s+городах/g, unit: 'городов' },
  { re: /(\d[\d\s]*)\s+регионах/g, unit: 'регионов' },
  { re: /(\d[\d\s]*)\s+моделей\s+и\s+[\d\s]+\s+типоразмер\w+/g, unit: 'моделей' },
  { re: /[\d\s]+\s+моделей\s+и\s+(\d[\d\s]*)\s+типоразмер\w+/g, unit: 'типоразмеров' },
  { re: /Каталог:\s*(\d[\d\s]*)\s+моделей/g, unit: 'моделей' },
  { re: /Каталог:\s*[\d\s]+\s+моделей,\s*(\d[\d\s]*)\s+типоразмер\w+/g, unit: 'типоразмеров' },
];

TOTAL_CLAIMS.forEach(({ re, unit }) => {
  const actual = facts[unit];
  sources.forEach(({ label, text }) => {
    re.lastIndex = 0;
    let match;
    while ((match = re.exec(text)) !== null) {
      const claimed = Number(match[1].replace(/\s/g, ''));
      if (claimed !== actual) {
        fail(`${label}: заявлено «${claimed} ${unit}», фактически ${actual}`);
      }
    }
  });
});

// --- 4. Шаги конфигуратора одинаковы на всех страницах ---------------------------
const selectorSteps = [];
pageFiles.forEach((file) => {
  const page = readJson(path.join(pagesDir, file));
  (page.sections || []).forEach((section) => {
    if (section.name !== 'tire-selector') return;
    const signature = (section.data?.steps || []).map((step) => ({
      key: step.key,
      options: (step.options || []).map((o) => `${o.value}:${o.label}`).join('|'),
    }));
    selectorSteps.push({ file, signature: JSON.stringify(signature) });
  });
});
if (selectorSteps.length > 1) {
  const [first, ...rest] = selectorSteps;
  rest.forEach((entry) => {
    if (entry.signature !== first.signature) {
      fail(`Конфигуратор: шаги в ${entry.file} отличаются от ${first.file} — варианты подбора должны совпадать`);
    }
  });
}

// --- 5. Страницы из sitemap_pages перечислены в llms.txt -------------------------
const projectConfig = fs.readFileSync(path.join(ROOT, 'config/project.php'), 'utf8');
const sitemapBlock = projectConfig.match(/'sitemap_pages'\s*=>\s*\[([\s\S]*?)\]/);
const llms = fs.readFileSync(path.join(ROOT, 'public/llms.txt'), 'utf8');
const ROUTE_BY_PAGE = { index: '/', 'tires-list': '/tires/' };
const SKIP = new Set(['404', 'policy', 'cookies-policy']);
if (sitemapBlock) {
  sitemapBlock[1]
    .split(',')
    .map((s) => s.trim().replace(/^'|'$/g, ''))
    .filter(Boolean)
    .forEach((pageId) => {
      if (SKIP.has(pageId)) return;
      const route = ROUTE_BY_PAGE[pageId] || `/${pageId}/`;
      if (!llms.includes(route)) {
        fail(`public/llms.txt: страница ${route} (${pageId}) не перечислена — LLM-краулеры её не увидят`);
      }
    });
}

// --- 5b. Страницы из sitemap_pages не осиротели ---------------------------------
// Страница может быть в sitemap и llms, но не иметь ни одной внутренней ссылки —
// так было с /contacts. Считаем ссылками навигацию и служебные ссылки из global.json.
const globalData = readJson(path.join(DATA, 'global.json'));
const linked = new Set();
Object.values(globalData.nav || {}).forEach((navLang) => {
  (navLang.items || []).forEach((item) => {
    if (item.visible === false) return;
    linked.add(String(item.href || '').replace(/\/$/, '') || '/');
  });
});
['policy', 'cookies-policy'].forEach((key) => {
  Object.values(globalData[key] || {}).forEach((entry) => {
    if (entry && entry.href) linked.add(String(entry.href).replace(/\/$/, ''));
  });
});

// Ссылка из контента другой страницы тоже считается: пункт меню можно скрыть,
// а переход оставить — так сделано для /contacts со страницы «О компании».
pageFiles.forEach((file) => {
  const raw = fs.readFileSync(path.join(pagesDir, file), 'utf8');
  const matches = raw.match(/["'](\/[a-z0-9-]+)["']/g) || [];
  matches.forEach((m) => linked.add(m.replace(/["']/g, '').replace(/\/$/, '')));
});

if (sitemapBlock) {
  sitemapBlock[1]
    .split(',')
    .map((s) => s.trim().replace(/^'|'$/g, ''))
    .filter(Boolean)
    .forEach((pageId) => {
      // index — сам сайт, news — раздел без пункта меню.
      // policy — HTML-страница есть в sitemap, но футер ведёт на PDF: решение юридическое,
      // до его пересмотра исключение явное, а не молчаливое.
      if (['index', 'news', 'policy'].includes(pageId)) return;
      const route = pageId === 'tires-list' ? '/tires' : `/${pageId}`;
      if (!linked.has(route)) {
        fail(
          `Страница ${route} (${pageId}) есть в sitemap_pages, но на неё нет ссылок в навигации — осиротевшая страница`
        );
      }
    });
}

// --- 6. llms-full.txt не протух --------------------------------------------------
try {
  const generated = execFileSync('php', [path.join(ROOT, 'tools/ops/generate-llms-full.php'), ROOT], {
    encoding: 'utf8',
    maxBuffer: 20 * 1024 * 1024,
  });
  const current = fs.readFileSync(path.join(ROOT, 'public/llms-full.txt'), 'utf8');
  if (generated.trim() !== current.trim()) {
    fail('public/llms-full.txt устарел — перегенерируйте: npm run generate-llms > public/llms-full.txt');
  }
} catch (error) {
  notes.push(`llms-full.txt не проверен: ${error.message.split('\n')[0]}`);
}

// --- Итог -----------------------------------------------------------------------
console.log('Факты по данным:');
Object.entries(facts).forEach(([unit, value]) => console.log(`  ${unit}: ${value}`));
notes.forEach((note) => console.log(`\nПримечание: ${note}`));

if (problems.length === 0) {
  console.log('\nРасхождений в контенте не найдено.');
  process.exit(0);
}

console.error(`\nНайдено расхождений: ${problems.length}`);
problems.forEach((p) => console.error(`  • ${p}`));
process.exit(1);
