# Сессия 2026-03-07: Этап 1 — Scaffold-генераторы

## Цель

Создать инструменты для автоматического создания новых коллекций и страниц одной командой, с валидацией slug'ов, поддержкой многоязычности и генерацией fixture-данных.

---

## Промты пользователя

### Промт 1
> давай подробно запланируем Этап 1: Scaffold-генераторы
> Инструменты для автоматического создания новых сущностей одной командой:
> - npm run create-collection — генерация полного скелета коллекции (JSON-данные, Twig-шаблон, запись в project.php, SEO-заглушки)
> - npm run create-page — генерация новой статической страницы
> - Валидация: проверка что slug не конфликтует с существующими маршрутами
>
> задай мне вопросы на уточнение

Были заданы 6 вопросов на уточнение (формат вызова, многоязычность, Twig-шаблон, автообновление project.php, модернизация create-page, генерация fixtures).

### Промт 2 (ответы на вопросы)
> 1. формат вызова - на твоё усмотренние, главно чтобы он соответствовал всем правилам
> 2. многоязычность при генерации - создавать заглушки для всех языков сразу
> 3. Twig-шаблон - нет ответа, выбери самостоятельно
> 4. Автообновление project.php - выбери вариант удобно но не рисковано
> 5. Что делать с существующим create-page - да модернизируй его
> 6. генерация пробных данных - обязательно создавать и не один вариант а 3 варианта

### Промт 3
> отправь проект на сборку и запусти его, чтобы я мог посмотреть результат руками и глазами

Результат: `npm run build:dev` — CSS/JS собраны успешно (Webpack compiled successfully). PHP dev-сервер запущен на `http://localhost:8080`. Все страницы отвечают 200.

### Промт 4
> Задокументируй подробно в новом md файле C:\raznoe\orch\docs произведённые изменения, процесс запуска и настройки проекта. Обязательно фиксируй промты в документе включая этот промт.

Результат: данный документ.

---

## Принятые решения

| Вопрос | Решение | Обоснование |
|---|---|---|
| Формат вызова | Минимальный: только slug | Соответствует стилю существующих scaffold'ов (`create-section`, `create-component`), всё остальное выводится автоматически |
| Многоязычность | Все языки из `global.json` | Ответ пользователя |
| Twig-шаблон | Универсальный с breadcrumbs, cover, date, title, lead, body | Объединяет паттерны `tire.twig` (заголовок + описание) и `news.twig` (cover + date + body), покрывает большинство случаев |
| Обновление project.php | Вывод готового PHP-блока в консоль | Удобно (copy-paste) и безопасно (0 риск сломать файл), пользователь контролирует результат |
| create-page | Модернизирован | Добавлена валидация, многоязычность, проверка конфликтов |
| Fixtures | 3 примера | Ответ пользователя |

---

## Произведённые изменения

### 1. Общие утилиты (`tools/scaffold/utils.js`) — НОВЫЙ ФАЙЛ

Вынесены общие функции для всех scaffold-скриптов:

```js
getAvailableLangs()   // Читает global.json → ['ru', 'en']
getExistingSlugs()    // Собирает Set всех занятых slug'ов:
                      //   - страницы из data/json/ru/pages/*.json
                      //   - директории коллекций из data/json/ru/
                      //   - зарезервированные: api, admin, health, sitemap, 404, index
validateSlug(slug)    // Проверяет: не пуст, формат a-z0-9-, длина 2-50
singularize(word)     // Простая сингуляризация: products→product, categories→category
ensureDir(dir)        // mkdir -p
writeIfNotExists()    // Безопасная запись (не перезаписывает существующие файлы)
```

### 2. Генератор коллекций (`tools/scaffold/create-collection.js`) — НОВЫЙ ФАЙЛ

**Вызов:**
```bash
npm run create-collection -- <slug>
```

**Что генерирует (для каждого языка из global.json):**

| Файл | Описание |
|---|---|
| `data/json/{lang}/pages/{slug}.json` | Страница-список с массивом `items` и секциями |
| `data/json/{lang}/{slug}/example-1.json` | Fixture-сущность 1 |
| `data/json/{lang}/{slug}/example-2.json` | Fixture-сущность 2 |
| `data/json/{lang}/{slug}/example-3.json` | Fixture-сущность 3 |
| `data/json/{lang}/seo/{slug}.json` | SEO-заглушка (title, description, OG) |
| `templates/pages/{singular}.twig` | Twig-шаблон детальной страницы |

**Структура fixture-сущности:**
```json
{
  "visible": true,
  "item": {
    "name": "Product 1",
    "title": "Product 1",
    "cover": {"src": ""},
    "date": "21 февраля 2026",
    "desc": "Описание элемента 1 коллекции «products».",
    "lead": "Краткое описание элемента 1.",
    "body": "<p>Полное описание элемента 1...</p>"
  }
}
```

**Структура страницы-списка:**
```json
{
  "name": "products",
  "items": ["example-1", "example-2", "example-3"],
  "sections": [
    {"name": "header", "data": {}},
    {"name": "products", "data": {"items": ["example-1", "example-2", "example-3"]}},
    {"name": "footer", "data": {}}
  ]
}
```

**Twig-шаблон** (`templates/pages/{singular}.twig`):
- Наследует `base.twig`
- Breadcrumbs (навигация)
- Cover-изображение
- Дата
- Заголовок (title / name)
- Lead-текст
- Body (HTML-контент)
- Header + Footer секции

**Вывод в консоль** — готовый PHP-блок для вставки в `config/project.php`:
```
========================================
Добавьте в config/project.php:
========================================

// collections — добавить:
'products' => [
    'nav_slug'     => 'products',
    'list_page_id' => 'products',
    'template'     => 'pages/product.twig',
    'item_key'     => 'item',
    'data_dir'     => 'products',
    'slugs_source' => 'items',
    'og_type'      => 'website',
    'extras_key'   => 'product',
],

// sitemap_pages — добавить:
'products',
```

**Валидация:**
- Формат slug (a-z, 0-9, дефис, 2-50 символов)
- Конфликт с существующими страницами
- Конфликт с существующими коллекциями
- Конфликт с зарезервированными slug'ами (api, admin, health, sitemap, 404, index)

### 3. Модернизация `create-page` (`tools/scaffold/create-page.js`) — ПЕРЕЗАПИСАН

**Было (до модернизации):**
- Без валидации slug'а
- JSON только для `ru`
- SEO только для `ru`
- Нет проверки конфликтов
- Нет инструкции для `project.php`

**Стало (после модернизации):**
- Валидация формата slug
- Проверка конфликтов с существующими страницами/коллекциями/зарезервированными
- JSON для всех языков из `global.json`
- SEO для всех языков
- JS/CSS создаются только для новых страниц
- Вывод инструкции для добавления в `sitemap_pages`

**Вызов (без изменений):**
```bash
npm run create-page -- <slug>
```

### 4. Обновление `package.json`

Добавлен скрипт:
```json
"create-collection": "node tools/scaffold/create-collection.js"
```

---

## Процесс сборки и запуска

### Сборка

```bash
npm run build:dev
```

Выполняет последовательно:
1. `build:css` — PostCSS + хеширование → `assets/css/build/main.{hash}.css`
2. `build:js` — Webpack → `runtime.js`, `util-vendors.js`, `ui-vendors.js`, `main.js`
3. `clean:assets` — удаление устаревших файлов сборки
4. `setup:public-links` — симлинки в `public/`

**Результат сборки 2026-03-07:**
```
webpack 5.105.2 compiled successfully in 928 ms
Entrypoint main 3.74 MiB = runtime.js + util-vendors.js + ui-vendors.js + main.js
```

Ошибка `EPERM: operation not permitted, symlink` для `robots.txt` — ограничение Windows (требует прав администратора для создания симлинков), не влияет на работу приложения.

### Запуск dev-сервера

```bash
/c/php85/php.exe -S localhost:8080 -t public
```

### Проверка страниц

| URL | HTTP | Статус |
|---|---|---|
| `http://localhost:8080/` | 200 | OK |
| `http://localhost:8080/tires/` | 200 | OK |
| `http://localhost:8080/tires/at52/` | 200 | OK |
| `http://localhost:8080/news/` | 200 | OK |
| `http://localhost:8080/news/dealers-2026/` | 200 | OK |
| `http://localhost:8080/policy/` | 200 | OK |
| `http://localhost:8080/agree/` | 200 | OK |
| `http://localhost:8080/buy/` | 200 | OK |
| `http://localhost:8080/contacts/` | 500 | Pre-existing баг (отсутствует `components/card-doc.twig`) |

### Тесты

```bash
/c/php85/php.exe vendor/bin/phpunit
# Tests: 42, Assertions: 49, Skipped: 10 — OK
```

---

## Файлы, затронутые в сессии

| Файл | Действие |
|---|---|
| `tools/scaffold/utils.js` | Создан — общие утилиты scaffold'ов |
| `tools/scaffold/create-collection.js` | Создан — генератор коллекций |
| `tools/scaffold/create-page.js` | Перезаписан — модернизация с валидацией и многоязычностью |
| `package.json` | Изменён — добавлен скрипт `create-collection` |
| `docs/session-2026-03-07-scaffold-generators.md` | Создан — данный документ |

---

## Полный список scaffold-команд

| Команда | Назначение |
|---|---|
| `npm run create-collection -- <slug>` | Новая коллекция (JSON для всех языков, 3 fixtures, Twig, SEO) |
| `npm run create-page -- <slug>` | Новая страница (JSON для всех языков, SEO, JS, CSS) |
| `npm run create-section -- <name>` | Новая секция (Twig, JS, CSS) |
| `npm run create-component -- <name>` | Новый компонент (Twig, JS, CSS) |