# Сессия 2026-03-06: Исправление URL на Windows + CSS-баг

**Дата:** 2026-03-06
**Проект:** iSmart Platform (Kumho Tires)
**Среда:** Windows 11 Pro, bash (Git Bash), PHP 8.5.1 (C:\php85), Node 24.14.0
**Сервер:** PHP built-in dev server (`php -S localhost:8080 -t public/`)

---

## Содержание

1. [Промт и контекст](#1-промт-и-контекст)
2. [Диагностика](#2-диагностика)
3. [Корневая причина](#3-корневая-причина)
4. [Исправление 1 — BaseUrlResolver.php](#4-исправление-1--baseurlresolverphp)
5. [Исправление 2 — container.php](#5-исправление-2--containerphp)
6. [Исправление 3 — actions.css](#6-исправление-3--actionscss)
7. [Верификация](#7-верификация)
8. [Затронутые файлы](#8-затронутые-файлы)

---

## 1. Промт и контекст

### Промт пользователя

```
Не корреткно работает главная страница
```

К промту приложен скриншот страницы `http://localhost:8080/` и полный HTML-код рендера `<div id="page-content">`.

### Наблюдаемая проблема

На скриншоте главная страница отображала:
- Секцию intro (видео-фон + заголовок) — частично видна
- Sticky-хедер с логотипом и навигацией — корректен
- Ниже хедера — пустое белое пространство вместо контента (акции, шины, новости, футер)

HTML-разметка содержала все секции — проблема была не в генерации шаблонов, а в некорректных URL ресурсов и сломанном CSS-свойстве.

---

## 2. Диагностика

### Шаг 1: Проверка HTML-рендера

Все секции (`header`, `intro`, `actions`, `tires`, `news`, `footer`) присутствовали в DOM:

```bash
curl -s http://localhost:8080/ | grep -c "section"
# 56
```

### Шаг 2: Проверка URL в рендере

Обнаружен обратный слеш (`\`) в URL ресурсов:

```html
<!-- ДО исправления -->
<source src="http://localhost:8080\/data/video/intro/cover.mp4" type="video/mp4">
<link rel="canonical" href="https://localhost:8080\" />
<script src="http://localhost:8080\/assets/js/utils/utm.js" defer></script>
```

```js
window.appConfig = {
  baseUrl: "http\u003A\/\/localhost\u003A8080\\\/",  // → http://localhost:8080\/
};
```

### Шаг 3: Проверка JSON-LD

JSON-LD содержал двойной URL из-за обратного слеша:

```json
"logo": "https://localhost:8080\/http://localhost:8080\/data/img/ui/logos/logo-h-color-2.svg"
```

### Шаг 4: Локализация причины

Прямой тест `dirname()` на Windows:

```bash
/c/php85/php.exe -r "echo dirname('/') . PHP_EOL; echo dirname('/index.php') . PHP_EOL;"
# \
# \
```

На Windows `dirname('/')` возвращает `\` вместо `/` — это корневая причина.

### Шаг 5: Проверка загрузки .env

```bash
/c/php85/php.exe -r "
\$projectRoot = 'C:/raznoe/orch';
require \$projectRoot . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(\$projectRoot)->safeLoad();
echo 'ENV: ' . (\$_ENV['APP_BASE_URL'] ?? 'NOT SET') . PHP_EOL;
echo 'getenv: ' . (getenv('APP_BASE_URL') ?: 'NOT SET') . PHP_EOL;
"
# ENV: http://localhost:8080/
# getenv: NOT SET
```

`$_ENV['APP_BASE_URL']` загружается корректно. Но `LanguageMiddleware` использует `BaseUrlResolver` (который не читает `.env`), а не значение из `.env`.

### Шаг 6: CSS-инспекция

В `assets/css/sections/actions.css` обнаружен разрыв строки внутри CSS-значения:

```css
/* БЫЛО — невалидный CSS */
.actions .section__subitem.cards-action-wrap {
  gap: 2
  rem;
}
```

Браузер игнорирует невалидное свойство `gap: 2` и неизвестное свойство `rem`.

---

## 3. Корневая причина

### Цепочка вызовов, порождающая сломанный URL

```
HTTP-запрос
  → LanguageMiddleware::process()
    → BaseUrlResolver::resolve($request)
      → dirname($_SERVER['SCRIPT_NAME'])   // dirname('/') → '\' на Windows
      → $basePath = '\'                     // не '/' и не '.', проходит условие
      → return 'http://localhost:8080\/'    // обратный слеш в URL
    → request->withAttribute('base_url', 'http://localhost:8080\/')
  → PageAction::__invoke()
    → $baseUrl = $request->getAttribute('base_url')  // 'http://localhost:8080\/'
    → DataLoaderService::loadPage(..., $baseUrl)
      → JsonProcessor::processJsonPaths($data, $baseUrl)
        → 'data/video/intro/cover.mp4' → 'http://localhost:8080\/data/video/intro/cover.mp4'
    → TemplateDataBuilder::build(...)
      → template data['base_url'] = 'http://localhost:8080\/'
  → Twig рендерит шаблон с base_url содержащим обратный слеш
```

### Почему APP_BASE_URL из .env не помогал

`container.php` читает `APP_BASE_URL` для инициализации Twig-расширений (`UrlExtension`, `AssetExtension`).
Но `LanguageMiddleware` на каждый запрос вызывает `BaseUrlResolver::resolve()` и перезаписывает `base_url` в атрибутах запроса.

Два разных источника `base_url`:
- **Twig global** (из container.php) — корректный, из `.env`
- **Request attribute** (из BaseUrlResolver) — сломанный, из `dirname()`

`TemplateDataBuilder::build()` использует request attribute и передаёт его в шаблон, перекрывая Twig global.

### Почему страница выглядела пустой

1. `JsonProcessor` превращал `data/video/intro/cover.mp4` в `http://localhost:8080\/data/video/intro/cover.mp4`
2. `UrlExtension::generateUrl()` видел `http://` в начале и возвращал URL как есть (не нормализуя)
3. Браузеры (Chrome, Firefox) нормализуют `\` в URL как `/`, превращая в `http://localhost:8080//data/...`
4. PHP built-in сервер отвечал 200 на `//data/...`, но рендеринг мог быть нестабильным
5. CSS-баг `gap: 2\nrem` ломал отступы в секции акций

---

## 4. Исправление 1 — BaseUrlResolver.php

**Файл:** `src/Support/BaseUrlResolver.php`, строка 23

```php
// ДО
$scriptDir = dirname($scriptName);

// ПОСЛЕ
$scriptDir = str_replace('\\', '/', dirname($scriptName));
```

**Суть:** Нормализация разделителя пути после `dirname()`. На Windows `dirname('/')` возвращает `\`, на Linux/macOS — `/`. После `str_replace` результат всегда `/`.

**Влияние:** Все URL, генерируемые через `BaseUrlResolver` (используется в `LanguageMiddleware` на каждый запрос), теперь корректны на Windows.

---

## 5. Исправление 2 — container.php

**Файл:** `config/container.php`, строка 64

```php
// ДО
$scriptDir = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'));

// ПОСЛЕ
$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
```

**Суть:** Аналогичная нормализация в fallback-ветке, которая срабатывает когда `APP_BASE_URL` не задан в `.env`. Сейчас `APP_BASE_URL` задан и fallback не используется, но без исправления он был бы сломан при деплое без `.env`.

---

## 6. Исправление 3 — actions.css

**Файл:** `assets/css/sections/actions.css`, строки 27-28

```css
/* ДО — невалидный CSS из-за переноса строки */
.actions .section__subitem.cards-action-wrap {
  display: flex;
  flex-direction: column;
  gap: 2
  rem;
}

/* ПОСЛЕ */
.actions .section__subitem.cards-action-wrap {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}
```

**Суть:** Перенос строки между числом `2` и единицей `rem` делал CSS-значение невалидным. Браузер игнорировал `gap: 2` (число без единицы) и `rem;` (неизвестное свойство). Карточки акций теряли вертикальный отступ.

**После исправления:** CSS пересобран командой `npm run build:css`. Новый хеш: `main.ba27e6cd.css` (был `main.2359a71f.css`).

---

## 7. Верификация

### URL после исправления

```bash
curl -s http://localhost:8080/ | grep -o 'baseUrl: "[^"]*"'
# baseUrl: "http\u003A\/\/localhost\u003A8080\/"
# Декодированное значение: http://localhost:8080/  (без обратного слеша)
```

### Canonical и hreflang

```html
<!-- ПОСЛЕ — корректные URL -->
<link rel="canonical" href="https://localhost:8080" />
<link rel="alternate" href="https://localhost:8080/en" hreflang="en" />
<link rel="alternate" href="https://localhost:8080/" hreflang="x-default" />
```

### URL ресурсов

```html
<!-- ПОСЛЕ — корректные URL -->
<source src="http://localhost:8080/data/video/intro/cover.mp4" type="video/mp4">
<script src="http://localhost:8080/assets/js/utils/utm.js" defer></script>
```

### CSS-ассет

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/assets/css/build/main.ba27e6cd.css
# 200
```

---

## 8. Затронутые файлы

| Файл | Тип изменения | Описание |
|------|---------------|----------|
| `src/Support/BaseUrlResolver.php` | Bugfix | `str_replace('\\', '/', dirname(...))` — нормализация пути на Windows |
| `config/container.php` | Bugfix | Та же нормализация в fallback-ветке определения `baseUrl` |
| `assets/css/sections/actions.css` | Bugfix | `gap: 2\nrem` → `gap: 2rem` — исправлен невалидный CSS |
| `assets/css/build/main.ba27e6cd.css` | Regenerated | Пересобранный CSS с исправленным `gap` |
| `assets/css/build/css-manifest.json` | Regenerated | Обновлённый хеш CSS-файла |

### Файлы, НЕ затронутые изменениями

- Шаблоны Twig — изменения не потребовались
- JSON-данные страниц — корректны
- JavaScript — корректен
- Webpack-конфиг — корректен
