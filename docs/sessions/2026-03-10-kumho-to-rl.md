# Сессия 2026-03-10 — Переход с Kumho на Ритейл Логистик

> **Дата:** 10 марта 2026
> **Ветка:** `main`
> **Статус изменений:** не закоммичены (коммиты делает пользователь вручную)
> **Агент:** Claude Code (claude-opus-4-6)

---

## Содержание

1. [Промты пользователя](#1-промты-пользователя)
2. [Обзор произведённых изменений](#2-обзор-произведённых-изменений)
3. [Удалённые файлы (данные Kumho)](#3-удалённые-файлы-данные-kumho)
4. [Созданные файлы (данные РЛ)](#4-созданные-файлы-данные-рл)
5. [Изменённые файлы](#5-изменённые-файлы)
6. [Восстановленные файлы (шаблоны платформы)](#6-восстановленные-файлы-шаблоны-платформы)
7. [Ветки и стратегия тиражирования](#7-ветки-и-стратегия-тиражирования)
8. [Созданная документация](#8-созданная-документация)
9. [Настройки проекта (config/project.php)](#9-настройки-проекта)
10. [Запуск и проверка](#10-запуск-и-проверка)
11. [Ошибки и уроки](#11-ошибки-и-уроки)

---

## 1. Промты пользователя

Ниже — все промты пользователя в хронологическом порядке, дословно.

### Промт 1 — Определение следующего шага
```
C:\raznoe\orch\docs\session-2026-03-10-main-page-rl.md
C:\raznoe\orch\docs\tz\tz_content_rl.md
C:\raznoe\orch\docs\tz\tz_final.md
Теперь какой у нас следующий шаг?
```

### Промт 2 — Удаление Kumho и добавление страниц РЛ
```
коммиты я сделал и всегда буду делать сам. удалить Kumho и добавить
недостающие страницы C:\raznoe\orch\docs\session-2026-03-10-main-page-rl.md
```

### Промт 3 — Обсуждение удаления шаблонов
```
посмотри диалог ниже и давай обсудим этот момент, пока ничего не делай,
только обсуждаем.
```
*(Пользователь приложил диалог с коллегой Данилом Фёдоровичем о том, правильно ли было удалять шаблоны Twig/JS/CSS при переходе на нового заказчика)*

### Промт 4 — Продолжение анализа
```
посмотри продолжение диалога. Ничего не делай кроме анализа и предложений
и просмотра кодовой базы. Ты только отвечаешь.
```
*(Продолжение диалога о стратегии тиражирования платформы)*

### Промт 5 — План тиражирования
```
предложи план действий, который касается ведения и развития платформы и
который касается приведения её к возможности тиражирования на другие
проекты, без этого я фактически не смогу перейти на проект ритейл логистик.
план оформи в папке docs\tz очень красивым md и html
```

### Промт 6 — Адаптация HTML
```
C:\raznoe\orch\docs\tz\platform-replication-plan.html — данный файл
адаптирован под айфон и мак?
```

### Промт 7 — Восстановление шаблонов
```
Восстановить из git удалённые шаблоны (9 Twig), JS (2 файла), CSS (11 файлов)
и импорты в main.js / main.css. JSON-данные Kumho остаются удалёнными — это правильно.
```

### Промт 8 — Создание веток
```
Создать ветку project/kumho от коммита до зачистки. Создать ветку
project/retail-logistic от main. Задокументировать ветвление в README.
```

### Промт 9 — Формализация плейбука
```
Формализованная инструкция: чеклист входных данных, пошаговое создание ветки
проекта, что удалять / что не трогать / что настраивать, чеклист верификации.
```

### Промт 10 — Документирование сессии (этот документ)
```
Задокументируй подробно в новом md файле C:\raznoe\orch\docs произведённые
изменения, процесс запуска, настройки проекта и изменений в документации.
Обязательно фиксируй промты в документе включая этот промт.
```

---

## 2. Обзор произведённых изменений

### Статистика
- **Удалено файлов:** 46 (JSON-данные Kumho, изображения)
- **Создано файлов:** 5 (JSON-страницы РЛ, документация)
- **Изменено файлов:** 13 (конфиг, JSON, CSS, README)
- **Восстановлено из git:** 20 файлов (шаблоны, JS, CSS — ошибочно удалённые)

### Суть
Платформа переведена с контента Kumho Tires (шинный бренд) на Ритейл Логистик (импортёр алкоголя, бренд Vintegra). Удалены только **данные** (JSON + изображения). Шаблоны, JS и CSS сохранены как библиотека платформы.

---

## 3. Удалённые файлы (данные Kumho)

### JSON-данные коллекций
```
data/json/ru/tires/          — 27 файлов (at52, at61, cx11, es31, ha31, ha32,
                                ha32-plus, hp71, hp91, hs51, hs52, hs63, kc53,
                                kh11, kh17, kh25, kh27, kh31, kl21, kl33,
                                kl71, ku39, mt51, mt71, ps71, ps72, ta31)
data/json/ru/news/           — 2 файла (dealers-2026, launch-2026)
```

### JSON-страницы Kumho
```
data/json/ru/pages/buy.json
data/json/ru/pages/dealers.json     (3254 строки!)
data/json/ru/pages/news.json
data/json/ru/pages/tires.json
data/json/ru/pages/tires-list.json
data/json/ru/pages/warranty.json
```

### SEO-файлы Kumho
```
data/json/ru/seo/buy.json
data/json/ru/seo/news.json
data/json/ru/seo/tires-list.json
data/json/ru/seo/warranty.json
```

### Изображения Kumho
```
data/img/actions/warranty.jpg
data/img/frames/buy.jpeg, buy.jpg, buy.webp
data/img/frames/news.jpg, news.webp, news1.webp
data/img/frames/tires.jpg, tires.webp
data/img/frames/warranty.jpg, warranty.webp
data/img/news/1.png, 2.png
data/img/tires/                      — полная директория (не в diff, удалена ранее)
```

---

## 4. Созданные файлы (данные РЛ)

### `data/json/ru/pages/demo-tools.json`
Новая страница «AI-инструменты» — демо-витрина AI-сервисов Vintegra.

**Секции:**
| # | Секция | Описание |
|---|--------|----------|
| 1 | `header` | Шапка сайта |
| 2 | `promo` | Заголовок H1 "AI-инструменты", подпись, фон |
| 3 | `content-container` | 3 блока: удаление фона (рабочий инструмент с Photoroom API), извлечение данных с этикетки (заглушка «Скоро»), CTA на личный кабинет |
| 4 | `footer` | Подвал |

Инструмент «Удаление фона» содержит полноценный dropzone с файловым input, блоками «До/После», индикатором загрузки и обработкой ошибок. Endpoint: `/api/photoroom/remove-background`.

### `data/json/ru/seo/demo-tools.json`
SEO-данные для страницы demo-tools:
- Title: «AI-инструменты — Vintegra | Ритейл Логистик»
- OG-теги: type, title, description, site_name

### Документация (см. раздел 8)
- `docs/tz/platform-replication-plan.md`
- `docs/tz/platform-replication-plan.html`
- `docs/tz/playbook-replication.md`

---

## 5. Изменённые файлы

### `config/project.php` — Конфигурация deployment'а

**Было (Kumho):**
```php
'route_map' => [
    'tires' => 'tires-list',
    'buy' => 'buy',
    'warranty' => 'warranty',
    'news' => 'news',
    'dealers' => 'dealers',
],
'collections' => [
    'tires' => [...],
    'news' => [...],
],
'sitemap_pages' => ['index', 'contacts', 'tires-list', 'buy', 'warranty', 'news', 'policy', 'agree'],
```

**Стало (РЛ):**
```php
'route_map' => [
    'demo-tools' => 'demo-tools',
],
'collections' => [],
'sitemap_pages' => ['index', 'contacts', 'demo-tools', 'policy', 'agree'],
'integrations' => [
    'photoroom' => ['enabled' => true],
],
```

### `data/json/global.json` — Глобальные данные

Изменения:
- **Навигация (ru):** Главная, Инструменты (`/demo-tools/`), Партнёрам (`/#partners`), Контакты, Интернет-магазин (external → vintegra.ru)
- **Навигация (en):** аналогично на английском
- **Логотип:** все варианты → `data/img/ui/logos/logo-h-color-2.png` (Vintegra)
- **Удалён:** блок `filter` (был Kumho-специфичный)
- **Socials:** пустой массив `[]`

### `data/json/ru/pages/index.json` — Главная страница

Полностью переписана. Секции:
| Секция | Содержание |
|--------|-----------|
| `hero` | H1: «Цифровая платформа Ритейл Логистик», описание, форма входа (логин/пароль, 2FA, IT-поддержка) |
| `trust` | 4 карточки: Импортёр, Цифровые процессы, Надёжность, Открытая экосистема |
| `partners` | Блок «Партнёрам»: заголовок, описание, 4 фичи, 4 карточки (Банки, Поставщики, Маркетплейсы, Розничные сети) |
| `footer` | Подвал |

### `data/json/ru/pages/contacts.json` — Контакты

Переписана для РЛ:
- Удалены: ассоциации АЛАРОС, каталоги, соцсети, кнопки карты
- Оставлены: адрес офиса (Москва), телефон/email из global, форма обратной связи
- Иконки: `icon-locate-color-2.svg`, `icon-phone-color-2.svg`, `icon-mail-color-2.svg`

### `data/json/ru/pages/policy.json` — Политика конфиденциальности

- Оператор: «ООО «Ритейл Логистик»» (было «ООО «Компания»»)
- Удалены ссылки на `example.com`
- Упрощена с 12 до 7 разделов

### `data/json/ru/pages/agree.json` — Пользовательское соглашение

- Компания: «ООО «Ритейл Логистик»»
- Раздел 2 «Описание сервиса»: описание платформы Vintegra, AI-инструменты, личный кабинет, демо
- 7 разделов

### `data/json/ru/pages/404.json` — Страница ошибки

Упрощена: удалён `form-container`, оставлены только `header`, `promo` (фон), `footer`.

### SEO-файлы (6 штук)

Все обновлены на бренд Vintegra:

| Файл | title |
|------|-------|
| `seo/index.json` | Vintegra — Цифровая платформа Ритейл Логистик |
| `seo/contacts.json` | Контакты — Vintegra \| Ритейл Логистик |
| `seo/demo-tools.json` | AI-инструменты — Vintegra \| Ритейл Логистик |
| `seo/policy.json` | Политика конфиденциальности — Vintegra \| Ритейл Логистик |
| `seo/agree.json` | Пользовательское соглашение — Vintegra \| Ритейл Логистик |
| `seo/404.json` | Страница не найдена — Vintegra |

OG:site_name во всех: «Vintegra — Ритейл Логистик» (или «Vintegra»).

### `assets/css/main.css` — Импорты CSS

Восстановлены 3 импорта (были ошибочно удалены и восстановлены из git):
```css
@import "sections/logoline.css";    /* был всегда */
@import "pages/tires.css";          /* восстановлен */
@import "pages/news.css";           /* восстановлен */
```
Полный список секций и компонентов — 31 импорт секций, 29 импортов компонентов, 5 импортов страниц.

### `assets/js/main.js` — Импорты JS

Восстановлены 2 импорта (были ошибочно удалены):
```js
import './sections/tires.js';
import './sections/dealers.js';
```

### `README.md` — Документация

Добавлен раздел «Ветки и тиражирование» (+39 строк):
- Таблица веток (main, project/kumho, project/retail-logistic)
- 4 правила работы с ветками
- Пример создания нового проекта (shell-команды)
- Ссылка на `docs/tz/platform-replication-plan.md`

---

## 6. Восстановленные файлы (шаблоны платформы)

При зачистке Kumho были ошибочно удалены шаблоны, JS и CSS. Это **библиотека платформы**, не контент проекта. Восстановлены из коммита `3d56591`:

### Twig-шаблоны (9 файлов)
```
templates/pages/tire.twig          — Карточка товара (шаблон коллекции)
templates/pages/news.twig          — Страница новости
templates/sections/tires.twig      — Секция каталога
templates/sections/news.twig       — Секция списка новостей
templates/sections/dealers.twig    — Секция дилеров
templates/components/card-tire.twig — Карточка товара в каталоге
templates/components/card-news.twig — Карточка новости
templates/components/card-dealer.twig — Карточка дилера
templates/components/filter.twig   — Универсальный data-driven фильтр
```

### JavaScript (2 файла)
```
assets/js/sections/tires.js        — Логика каталога (фильтрация, сортировка)
assets/js/sections/dealers.js      — Логика секции дилеров
```

### CSS (9 файлов)
```
assets/css/sections/tires.css
assets/css/sections/news.css
assets/css/sections/dealers.css
assets/css/components/filter.css
assets/css/components/card-tire.css
assets/css/components/card-news.css
assets/css/components/card-dealer.css
assets/css/pages/tires.css
assets/css/pages/news.css
```

### Почему нельзя было удалять

> Шаблоны, JS и CSS — это библиотека платформы, а не контент проекта. Удалять при смене проекта — всё равно что удалять неиспользуемые классы из Bootstrap.

- Шаблоны без JSON **инертны** — они никогда не вызываются, если нет JSON-секции с соответствующим `name`
- `filter.twig` — универсальный data-driven компонент, пригодный для любого каталога
- `card-tire.twig` легко переименовывается в `card-product.twig` для универсального использования
- Стоимость хранения: ~5 KB gzip. Стоимость потери: повторная разработка

---

## 7. Ветки и стратегия тиражирования

### Созданные ветки

| Ветка | Базовый коммит | Описание |
|-------|---------------|----------|
| `main` | текущий HEAD | Платформа + библиотека компонентов + данные РЛ |
| `project/kumho` | `432097e` (v1.1.0) | Полный контент Kumho: 27 моделей шин, 2 коллекции, все страницы |
| `project/retail-logistic` | от main HEAD | Ритейл Логистик: hero, trust, partners, demo-tools, contacts |

### Стратегия branch-per-project

```
main (платформа)
 ├── project/kumho (от 432097e)
 ├── project/retail-logistic (от main)
 └── project/new-client (от main в будущем)
```

**Правила:**
1. Новые компоненты и исправления ядра → коммит в `main`
2. Данные заказчика (JSON, изображения, project.php, variables.css, .env) → коммит в `project/*`
3. Обновление платформы в проекте → `git merge main` в ветку проекта
4. Новый проект → `git checkout -b project/new-client main`, удалить только JSON/изображения

---

## 8. Созданная документация

### `docs/tz/platform-replication-plan.md`
Комплексный план тиражирования платформы:
- 3-слойная архитектура (ядро → компонентная библиотека → проектные данные)
- Git-стратегия branch-per-project
- Классификация файлов по слоям
- 4-фазный план действий (фиксация, разделение, унификация, шаблон тиражирования)
- Приложение: реестр компонентной библиотеки

### `docs/tz/platform-replication-plan.html`
Визуальная HTML-версия плана:
- Тёмная тема, цвета Vintegra
- Диаграммы слоёв, таймлайн фаз, бейджи
- Адаптация под iPhone/Mac: safe-area-insets, прокручиваемые таблицы, Apple meta-теги, брейкпойнты 768px/390px

### `docs/tz/playbook-replication.md`
Формализованный плейбук тиражирования:
- **Часть 1:** Чеклист входных данных от заказчика (17 пунктов с флагами блокирования)
- **Часть 2:** Пошаговое создание ветки проекта (6 шагов с shell-командами)
- **Часть 3:** Таблицы «Удалить / Не трогать / Настроить»
- **Часть 4:** Чеклист верификации (технический, smoke, визуальный, SEO, проверка на остатки старого контента)
- **Часть 5:** Quick start команды

### `README.md` (обновлён)
Добавлен раздел «Ветки и тиражирование» сразу после «Обзор проекта».

---

## 9. Настройки проекта

### Текущий `config/project.php`

```php
return [
    'route_map' => [
        'demo-tools' => 'demo-tools',
    ],
    'collections' => [],
    'sitemap_pages' => ['index', 'contacts', 'demo-tools', 'policy', 'agree'],
    'integrations' => [
        'photoroom' => ['enabled' => true],
    ],
];
```

**Маршруты:**
| URL | page_id | Источник |
|-----|---------|----------|
| `/` | `index` | встроенный (не в route_map) |
| `/contacts/` | `contacts` | встроенный |
| `/policy/` | `policy` | встроенный |
| `/agree/` | `agree` | встроенный |
| `/demo-tools/` | `demo-tools` | `route_map` |

**Коллекции:** нет (пустой массив). У Kumho были `tires` и `news`.

**Интеграции:** Photoroom API (удаление фона) включён.

### Глобальные данные (`data/json/global.json`)

- **Языки:** ru, en
- **Телефон:** +7 (000) 000-00-00 (заглушка — ожидает реальные данные)
- **Email:** info@example.com (заглушка)
- **Логотип:** `data/img/ui/logos/logo-h-color-2.png` (Vintegra)
- **Навигация (ru):** Главная, Инструменты, Партнёрам, Контакты, Интернет-магазин
- **Socials:** пустой массив

### CSS-переменные (`assets/css/base/variables.css`)

Файл изменён (в staged, но не в diff — вероятно, изменены цветовые переменные под бренд Vintegra).

### Фавиконки

Все файлы в `data/img/favicons/` обновлены (16 файлов: png, ico, svg, webmanifest).

---

## 10. Запуск и проверка

### Предварительные требования
- Node.js + npm
- PHP 8.5+ (`C:\php85\php.exe` на текущей машине)
- Composer

### Инициализация
```bash
npm run init        # npm install + composer install + init-env
```

### Запуск dev-сервера
```bash
# Терминал 1: watch CSS + JS
npm run dev

# Терминал 2: PHP-сервер
php -S localhost:8080 -t public
# На Windows: /c/php85/php.exe -S localhost:8080 -t public
```

### Сборка
```bash
npm run build:dev   # Dev: CSS + JS + clean + symlinks
npm run build       # Production: check + lint + test + build + clean + symlinks
```

### Проверка
```bash
npm test            # validate-json + PHPUnit + Vitest
npm run check       # Все линтеры + тесты
npm run test:smoke  # Smoke-тесты (требует запущенный сервер)
```

### Что проверить вручную
1. Главная (`/`) — hero с формой входа, блок trust (4 карточки), блок partners
2. Контакты (`/contacts/`) — адрес, телефон, email, форма
3. Инструменты (`/demo-tools/`) — dropzone для удаления фона, заглушки
4. Политика (`/policy/`) — «ООО Ритейл Логистик» в тексте
5. Соглашение (`/agree/`) — «ООО Ритейл Логистик», описание Vintegra
6. 404 — корректный рендеринг без формы
7. Навигация — 5 пунктов, «Интернет-магазин» открывается в новой вкладке
8. Логотип — Vintegra во всех местах
9. **Отсутствие Kumho:** поиск «kumho», «tires», «шины» не должен давать результатов в UI

### Известные ограничения
- **Windows symlink:** `EPERM: operation not permitted, symlink` при `npm run build:dev` — известное ограничение Windows, не блокирует работу. Решение: junction создаётся через `setup-public-links.js`.
- **Заглушки:** телефон, email, адрес — ожидают реальные данные от заказчика
- **Photoroom API:** endpoint `/api/photoroom/remove-background` требует настройки бэкенда (API-ключ в `.env`)

---

## 11. Ошибки и уроки

### Ошибка: удаление шаблонов при зачистке Kumho

**Что произошло:** При переходе на РЛ были удалены не только JSON-данные Kumho (правильно), но и шаблоны Twig, JS и CSS (неправильно).

**Почему это ошибка:**
- Шаблоны — это **библиотека платформы** (Слой 2), а не данные проекта (Слой 3)
- Без JSON-данных шаблоны инертны и не влияют на рендеринг
- Удаление шаблонов делает «эталонную платформу нежизнеспособной» (цитата коллеги)

**Аналогия:** удалять неиспользуемые шаблоны при смене проекта — всё равно что удалять неиспользуемые классы из Bootstrap.

**Исправление:** Все 20 файлов восстановлены из коммита `3d56591`. Импорты в `main.js` и `main.css` возвращены.

**Урок:** При тиражировании на нового заказчика **удаляются только**:
- `data/json/{lang}/{collection}/` — данные коллекций
- `data/json/{lang}/pages/{old-pages}.json` — страницы предыдущего проекта
- `data/json/{lang}/seo/{old-seo}.json` — SEO предыдущего проекта
- `data/img/{collection}/` — изображения коллекций
- `data/img/frames/{old}.{jpg,webp}` — фреймы страниц

**Никогда не удаляются:** `templates/`, `assets/js/`, `assets/css/`, `src/`.

---

## Приложение: полный diff stat

```
64 files changed, 104 insertions(+), 4540 deletions(-)
```

Основной объём удалений — `data/json/ru/pages/dealers.json` (3254 строки) и 27 файлов шин.
