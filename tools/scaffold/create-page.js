const fs = require('fs');
const path = require('path');

// Получаем аргументы командной строки
const args = process.argv.slice(2);
const page = args[0];

if (!page) {
  console.error('Укажите имя страницы в качестве аргумента');
  process.exit(1);
}

// Все страницы рендерятся через единый pages/page.twig (data-driven по sections из JSON).
// Twig для страницы не создаём — достаточно JSON в data/json/{lang}/pages/ и маршрута в config.

// Создаем JSON для страницы (если ещё нет) — по нему определяем, новая ли страница
const pagesDir = path.join('data', 'json', 'ru', 'pages');
const jsonPath = path.join(pagesDir, `${page}.json`);
if (!fs.existsSync(pagesDir)) {
  fs.mkdirSync(pagesDir, { recursive: true });
  console.log(`Создана директория ${pagesDir}`);
}
let isNewPage = false;
if (!fs.existsSync(jsonPath)) {
  try {
    const pageJsonData = {
      name: page,
      sections: [
        { name: 'header', data: {} },
        { name: 'intro', data: {} },
        { name: 'footer', data: {} },
      ],
    };
    fs.writeFileSync(jsonPath, JSON.stringify(pageJsonData, null, 2));
    console.log(`Создан файл JSON для страницы: ${jsonPath}`);
    isNewPage = true;
  } catch (err) {
    console.error(`Ошибка при создании файла JSON: ${err.message}`);
    process.exit(1);
  }
} else {
  console.log(`Файл JSON для страницы ${page} уже существует: ${jsonPath}`);
}

if (isNewPage) {
  // Создаем JS файл
  const jsDir = path.join('assets', 'js', 'pages');
  const jsPath = path.join(jsDir, `${page}.js`);

  if (!fs.existsSync(jsDir)) {
    fs.mkdirSync(jsDir, { recursive: true });
  }

  const jsContent = `// JavaScript для страницы ${page}
document.addEventListener('DOMContentLoaded', function() {
  console.log('Страница ${page} загружена');
  // Код для страницы ${page}
});
`;

  try {
    fs.writeFileSync(jsPath, jsContent);
    console.log(`Создан файл ${jsPath}`);

    // Добавляем импорт в main.js
    const mainJsPath = path.join('assets', 'js', 'main.js');
    if (fs.existsSync(mainJsPath)) {
      let mainJsContent = fs.readFileSync(mainJsPath, 'utf8');
      if (!mainJsContent.includes(`./pages/${page}.js`)) {
        // Находим место, куда вставить импорт
        const importSection = '// --- Pages ---';
        if (mainJsContent.includes(importSection)) {
          mainJsContent = mainJsContent.replace(importSection, `${importSection}\nimport './pages/${page}.js';`);
          fs.writeFileSync(mainJsPath, mainJsContent);
          console.log(`Добавлен импорт в main.js для ${page}.js`);
        }
      }
    }
  } catch (err) {
    console.error(`Ошибка при создании JS файла: ${err.message}`);
  }

  // Создаем CSS файл
  const cssDir = path.join('assets', 'css', 'pages');
  const cssPath = path.join(cssDir, `${page}.css`);

  if (!fs.existsSync(cssDir)) {
    fs.mkdirSync(cssDir, { recursive: true });
  }

  const cssContent = `.${page} {
  /* Стили для ${page} */
}

.${page} .container {
  /* Стили для контейнера в ${page} */
}
`;

  try {
    fs.writeFileSync(cssPath, cssContent);
    console.log(`Создан файл ${cssPath}`);

    // Добавляем импорт в main.css
    const mainCssPath = path.join('assets', 'css', 'main.css');
    if (fs.existsSync(mainCssPath)) {
      const mainCssContent = fs.readFileSync(mainCssPath, 'utf8');
      if (!mainCssContent.includes(`pages/${page}.css`)) {
        fs.appendFileSync(mainCssPath, `@import "pages/${page}.css";\n`);
        console.log(`Добавлен импорт в main.css для ${page}.css`);
      }
    }
  } catch (err) {
    console.error(`Ошибка при создании CSS файла: ${err.message}`);
  }

  console.log('Файлы JS и CSS успешно созданы и подключены');
}

// Создаем SEO файл для страницы в директории data/json/ru/seo/
const seoDir = path.join('data', 'json', 'ru', 'seo');
const seoPath = path.join(seoDir, `${page}.json`);

// Проверяем существование директории
if (!fs.existsSync(seoDir)) {
  fs.mkdirSync(seoDir, { recursive: true });
  console.log(`Создана директория ${seoDir}`);
}

// Проверяем, существует ли уже SEO файл для этой страницы
if (!fs.existsSync(seoPath)) {
  try {
    // Создаем базовый SEO файл с пустой структурой
    const seoData = {
      name: page,
      title: '',
      meta: [
        {
          name: 'description',
          content: '',
        },
        {
          property: 'og:url',
          content: `/${page}/`,
        },
        {
          property: 'og:type',
          content: 'website',
        },
        {
          property: 'og:title',
          content: '',
        },
        {
          property: 'og:description',
          content: '',
        },
        {
          property: 'og:site_name',
          content: '',
        },
        {
          property: 'og:image',
          content: '',
        },
      ],
    };

    // Записываем SEO данные в файл
    fs.writeFileSync(seoPath, JSON.stringify(seoData, null, 2));
    console.log(`Создан SEO файл для страницы: ${seoPath}`);
  } catch (err) {
    console.error(`Ошибка при создании SEO файла: ${err.message}`);
  }
} else {
  console.log(`SEO файл для страницы ${page} уже существует: ${seoPath}`);
}
