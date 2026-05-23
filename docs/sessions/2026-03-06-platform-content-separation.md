# Разделение платформы и контента: рефакторинг хардкода

**Дата:** 2026-03-06
**Контекст:** iSmart Platform — многоязычная веб-платформа на PHP 8.5+ (Slim 4, Twig 3)
**Цель:** Вынести бизнес-контент (Kumho Tires) из инфраструктурного кода в конфигурацию и данные

---

## Проблема

Анализ кодовой базы выявил 6 точек проникновения контентной логики в платформенный код.
Это блокировало переиспользование платформы для других клиентов без правки PHP/Twig файлов.

### Критические места хардкода до рефакторинга

| Файл | Хардкод | Проблема |
|------|---------|---------|
| `src/Action/PageAction.php` | `$pageId === 'news'` | Имя коллекции зашито в PHP |
| `src/Action/PageAction.php` | `'Шины'`, `'/tires/'`, `'tires'` | Строки бренда в breadcrumb |
| `src/Action/PageAction.php` | `'/news/' . $slug`, `'news'` | URL-структура новостей зашита в PHP |
| `src/Action/PageAction.php` | `'pages/tire.twig'`, `'pages/news.twig'` | Имена шаблонов зашиты в PHP |
| `templates/sections/header.twig` | `normalizedItemHref == 'tires'` | Slug конкретного раздела в шаблоне |
| `templates/sections/header.twig` | `pageKey in ['tires-list', 'tire']` | page_id конкретных страниц в шаблоне |
| `templates/sections/tires.twig` | Кнопки: `'Лето'`, `'Зима'`, `'Всесезонные'`, `'Шипованные'` | UI-контент в шаблоне |
| `templates/sections/news.twig` | `url('/news/')`, `'Все новости'` | URL и текст ссылки в шаблоне |
| `templates/components/card-tire.twig` | `'data/img/tires/hs51/400/hs51-30deg.webp'` | Путь к файлу конкретной шины в шаблоне |

---

## Изменения

### 1. `config/settings.php` — новый блок `collections`

**Суть:** Добавлен конфигурационный блок, который параметризует всю коллекционную логику PageAction.
Ранее имена коллекций, slugи навигации и имена шаблонов были хардкодом в PHP.

```php
// ДО: хардкод размазан по PageAction.php
if ($pageId === 'news') { ... }
$template = $tire !== null ? 'pages/tire.twig' : 'pages/news.twig';
if ($href === 'tires') { ... }

// ПОСЛЕ: единый источник истины в settings.php
'collections' => [
    'tires' => [
        'nav_slug'     => 'tires',
        'list_page_id' => 'tires-list',
        'template'     => 'pages/tire.twig',
    ],
    'news' => [
        'nav_slug'     => 'news',
        'list_page_id' => 'news',
        'template'     => 'pages/news.twig',
    ],
],
```

**Поля:**
- `nav_slug` — slug в URL и в `global.json nav` (используется для поиска заголовка в breadcrumb)
- `list_page_id` — `page_id` страницы-списка; PageAction сравнивает с ним `$pageId` для определения типа коллекции
- `template` — Twig-шаблон для страницы отдельного элемента коллекции

**Обратная совместимость:** Все поля читаются через `??` с fallback-значениями, совпадающими со старым хардкодом. При отсутствии `collections` в settings поведение не меняется.

---

### 2. `src/Action/PageAction.php` — параметризация через `collections`

**Суть:** Убраны все хардкоды коллекций. В начале `__invoke` извлекаются переменные из `settings['collections']`, которые далее используются везде.

#### 2.1. Чтение конфига коллекций

```php
// ДОБАВЛЕНО: извлечение конфига коллекций из settings
$collections     = (array) ($this->settings['collections'] ?? []);
$tiresConfig     = (array) ($collections['tires'] ?? []);
$newsConfig      = (array) ($collections['news'] ?? []);
$tiresListPageId = (string) ($tiresConfig['list_page_id'] ?? 'tires-list');
$newsListPageId  = (string) ($newsConfig['list_page_id'] ?? 'news');
$tiresNavSlug    = (string) ($tiresConfig['nav_slug'] ?? 'tires');
$newsNavSlug     = (string) ($newsConfig['nav_slug'] ?? 'news');
$tiresTemplate   = (string) ($tiresConfig['template'] ?? 'pages/tire.twig');
$newsTemplate    = (string) ($newsConfig['template'] ?? 'pages/news.twig');
```

#### 2.2. Определение типа коллекции

```php
// ДО
if ($tire === null && $pageId === 'news' && count($routeParams) === 0) {
if ($tire === null && $pageId === 'news' && count($routeParams) === 1) {

// ПОСЛЕ
if ($tire === null && $pageId === $newsListPageId && count($routeParams) === 0) {
if ($tire === null && $pageId === $newsListPageId && count($routeParams) === 1) {
```

#### 2.3. Выбор шаблона

```php
// ДО
$template = $tire !== null ? 'pages/tire.twig' : ($news !== null ? 'pages/news.twig' : 'pages/page.twig');

// ПОСЛЕ
$template = $tire !== null ? $tiresTemplate : ($news !== null ? $newsTemplate : 'pages/page.twig');
```

#### 2.4. Метод `buildTireBreadcrumb` — параметризация nav_slug

Сигнатура расширена параметром `$navSlug`:

```php
// ДО
private function buildTireBreadcrumb(array $global, string $langCode, array $tire): array
{
    ...
    $listTitle = 'Шины';          // хардкод строки бренда
    $listHref  = '/tires/';       // хардкод URL
    ...
    if ($href === 'tires') {      // хардкод slug
        $listTitle = ...
        $listHref  = '/' . $href . '/';
    }
    ...
    ['name' => $name, 'url' => '/' . $tire['slug'] . '/'],
}

// ПОСЛЕ
private function buildTireBreadcrumb(
    array $global, string $langCode, array $tire, string $navSlug = 'tires'
): array {
    ...
    $listTitle = ucfirst($navSlug);       // из параметра
    $listHref  = '/' . $navSlug . '/';   // из параметра
    ...
    if ($href === $navSlug) {             // из параметра
        $listTitle = ...
        $listHref  = '/' . $href . '/';
    }
    ...
    ['name' => $name, 'url' => '/' . $tire['slug'] . '/'],
}
```

Вызов обновлён: `$this->buildTireBreadcrumb($global, $langCode, $tire, $tiresNavSlug)`.

#### 2.5. Метод `buildNewsBreadcrumb` — параметризация nav_slug и URL новостей

```php
// ДО
private function buildNewsBreadcrumb(array $global, string $langCode, array $news): array
{
    ...
    $listTitle = 'Новости';        // хардкод строки
    $listHref  = '/news/';         // хардкод URL
    ...
    if ($href === 'news') { ... }  // хардкод slug
    ...
    ['name' => $name, 'url' => '/news/' . $news['slug'] . '/'],  // хардкод URL
}

// ПОСЛЕ
private function buildNewsBreadcrumb(
    array $global, string $langCode, array $news, string $navSlug = 'news'
): array {
    ...
    $listTitle = ucfirst($navSlug);
    $listHref  = '/' . $navSlug . '/';
    ...
    if ($href === $navSlug) { ... }
    ...
    ['name' => $name, 'url' => '/' . $navSlug . '/' . $news['slug'] . '/'],
}
```

Вызов обновлён: `$this->buildNewsBreadcrumb($global, $langCode, $news, $newsNavSlug)`.

---

### 3. `data/json/global.json` — поле `active_for` в nav

**Суть:** Логика определения активной ссылки в шапке перенесена из Twig-шаблона в данные.
Раньше `header.twig` знал, что `/tires/` активна для page_id `tires-list` и `tire` — это контентные знания в платформенном шаблоне.

```json
// ДО
{
  "title": "Шины",
  "href": "/tires/"
}

// ПОСЛЕ (ru и en nav)
{
  "title": "Шины",
  "href": "/tires/",
  "active_for": ["tires-list", "tire"]
}
```

Поле `active_for` — необязательный массив `page_id`, при попадании в который nav-ссылка получает класс `active`. Если поле отсутствует, логика работает по старому принципу (сравнение `pageKey == normalizedItemHref`), что обеспечивает обратную совместимость для всех остальных пунктов меню.

---

### 4. `templates/sections/header.twig` — убран хардкод slug `'tires'`

```twig
{# ДО: Kumho-специфичная логика в платформенном шаблоне #}
{% if normalizedItemHref == '' %}
  {% set isActive = pageKey == 'index' %}
{% elseif normalizedItemHref == 'tires' %}
  {% set isActive = pageKey in ['tires-list', 'tire'] %}
{% else %}
  {% set isActive = pageKey == normalizedItemHref %}
{% endif %}

{# ПОСЛЕ: универсальная логика через данные #}
{% if normalizedItemHref == '' %}
  {% set isActive = pageKey == 'index' %}
{% elseif item.active_for is defined and pageKey in item.active_for %}
  {% set isActive = true %}
{% else %}
  {% set isActive = pageKey == normalizedItemHref %}
{% endif %}
```

Теперь шаблон не знает ни о каких конкретных разделах. Любой nav-пункт может объявить свои `active_for` через `global.json`.

---

### 5. `data/json/ru/pages/tires-list.json` — кнопки сезонов в данных

**Суть:** Массив кнопок фильтра по сезонам перенесён из Twig в JSON-данные страницы.

```json
// ДО
"filter": {
  "visible": true
}

// ПОСЛЕ
"filter": {
  "visible": true,
  "seasons": [
    {"label": "Лето",        "value": "summer"},
    {"label": "Зима",        "value": "winter",    "disabled": true},
    {"label": "Всесезонные", "value": "allseason"},
    {"label": "Шипованные",  "value": "studded",   "disabled": true}
  ]
}
```

Поля элемента:
- `label` — отображаемый текст кнопки
- `value` — значение `data-season` атрибута (технический код сезона)
- `disabled` — опциональный флаг, делает кнопку неактивной

---

### 6. `templates/sections/tires.twig` — кнопки сезонов из `data.filter.seasons`

```twig
{# ДО: четыре кнопки хардкодом прямо в тексте шаблона #}
{% set tiresFilter = {
  'root_class': 'section__subitem filter-wrap',
  'groups': [
    {
      'type': 'buttons',
      'class': 'seasons-wrap',
      'buttons': [
        {'label': 'Лето',        'class': 'js-select-season', 'data_attr_name': 'data-season', 'data_attr': 'summer'},
        {'label': 'Зима',        'class': 'js-select-season', 'data_attr_name': 'data-season', 'data_attr': 'winter',    'disabled': true},
        {'label': 'Всесезонные', 'class': 'js-select-season', 'data_attr_name': 'data-season', 'data_attr': 'allseason'},
        {'label': 'Шипованные',  'class': 'js-select-season', 'data_attr_name': 'data-season', 'data_attr': 'studded',   'disabled': true}
      ]
    },

{# ПОСЛЕ: кнопки строятся из data.filter.seasons #}
{% set seasonButtons = [] %}
{% for s in data.filter.seasons | default([]) %}
  {% set btn = {'label': s.label, 'class': 'js-select-season', 'data_attr_name': 'data-season', 'data_attr': s.value} %}
  {% if s.disabled is defined and s.disabled %}
    {% set btn = btn | merge({'disabled': true}) %}
  {% endif %}
  {% set seasonButtons = seasonButtons | merge([btn]) %}
{% endfor %}
{% set tiresFilter = {
  'root_class': 'section__subitem filter-wrap',
  'groups': [
    {
      'type': 'buttons',
      'class': 'seasons-wrap',
      'buttons': seasonButtons
    },
```

Если `data.filter.seasons` не задан, `seasonButtons` будет пустым массивом — блок кнопок отрендерится пустым. Существующий маппинг нормализации сезонных кодов (строки 127–135) сохранён без изменений: он обрабатывает значения `filter.season` в данных шин и не зависит от UI-кнопок.

---

### 7. `data/json/ru/pages/index.json` — поле `all_news_link` в секции news

**Суть:** Ссылка "Все новости" на главной странице теперь определяется данными, а не хардкодом шаблона.

```json
// ДО — нет никакого поля, URL '/news/' и текст 'Все новости' были в Twig
"data": {
  "heading": { ... },
  "items": [ ... ]
}

// ПОСЛЕ
"data": {
  "heading": { ... },
  "all_news_link": {
    "href": "/news/",
    "title": "Все новости"
  },
  "items": [ ... ]
}
```

---

### 8. `templates/sections/news.twig` — ссылка из `data.all_news_link`

```twig
{# ДО: URL и текст зашиты в шаблон #}
{% if page_id is defined and page_id != 'news' %}
  <a href="{{ url('/news/') }}" class="link underline-center news__all-link">Все новости</a>
{% endif %}

{# ПОСЛЕ: из данных секции; ссылка не рендерится если поле не задано #}
{% if page_id is defined and page_id != 'news' and data.all_news_link is defined %}
  <a href="{{ url(data.all_news_link.href | default('/news/')) }}"
     class="link underline-center news__all-link">
    {{ data.all_news_link.title | default('Все новости') }}
  </a>
{% endif %}
```

Условие рендеринга изменилось: ссылка появляется только если `data.all_news_link` задан в JSON.
На странице `/news/` (where `page_id == 'news'`) ссылка по-прежнему не показывается.

---

### 9. `templates/components/card-tire.twig` — убран fallback-путь конкретной шины

```twig
{# ДО: путь к файлу конкретной шины бренда Kumho #}
{% set imageSrc = item.images is defined ...
  ? item.image.src
  : 'data/img/tires/hs51/400/hs51-30deg.webp' %}

{# ПОСЛЕ: пустая строка — карточка без изображения если данные не заданы #}
{% set imageSrc = item.images is defined ...
  ? item.image.src
  : '' %}
```

---

## Архитектурный итог

### Что изменилось в разделении платформа / контент

```
ДО рефакторинга:
  src/Action/PageAction.php        ← содержал: 'news', 'tires', 'Шины', '/tires/', 'pages/tire.twig'
  templates/sections/header.twig  ← содержал: 'tires', ['tires-list', 'tire']
  templates/sections/tires.twig   ← содержал: 'Лето', 'Зима', 'Всесезонные', 'Шипованные'
  templates/sections/news.twig    ← содержал: '/news/', 'Все новости'
  templates/components/card-tire  ← содержал: 'data/img/tires/hs51/400/hs51-30deg.webp'

ПОСЛЕ рефакторинга:
  config/settings.php             ← collections.tires / collections.news (nav_slug, list_page_id, template)
  data/json/global.json           ← nav[*].active_for (какие page_id активируют ссылку)
  data/json/ru/pages/tires-list.json  ← filter.seasons (кнопки с label/value/disabled)
  data/json/ru/pages/index.json   ← news section → all_news_link.href / all_news_link.title
```

### Как добавить новую коллекцию для другого проекта

> **Обновление (2026-03-07, Этап 0 + 1):** После завершения Этапа 0 (content-agnostic ядро) и Этапа 1 (scaffold-генераторы) процесс значительно упрощён. Универсальные методы `loadEntitySlugs`/`loadEntity` в `DataLoaderService` обрабатывают любую коллекцию — **не нужно реализовывать отдельные методы загрузки**.

**Способ 1 — Scaffold-генератор (рекомендуемый):**
```bash
npm run create-collection -- products
# Генерирует: JSON для всех языков, 3 fixture-сущности, Twig-шаблон, SEO
# Выводит готовый PHP-блок для вставки в config/project.php
```

**Способ 2 — Ручное создание:**
1. Добавить запись в `config/project.php → collections` (полный формат с `item_key`, `data_dir`, `slugs_source`, `og_type`, `extras_key`)
2. Добавить в `route_map`: `'products' => 'products-list'`
3. Создать шаблон `templates/pages/product.twig`
4. Создать данные в `data/json/{lang}/products/{slug}.json`
5. Создать страницу-список `data/json/{lang}/pages/products-list.json`

### Оставшиеся точки хардкода (вне scope этого рефакторинга)

> **Обновление (2026-03-07, Этап 0):** `DataLoaderService` полностью очищен — универсальные методы `loadEntitySlugs`/`loadEntity` заменили контент-специфичные. `sitemap_pages` вынесен в `config/project.php`.

| Файл | Хардкод | Статус |
|------|---------|--------|
| ~~`src/Service/DataLoaderService.php`~~ | ~~Пути `/tires/`, `/news/` в именах методов~~ | **РЕШЕНО** (Этап 0) — универсальные методы |
| ~~`config/settings.php → sitemap_pages`~~ | ~~Список страниц для sitemap~~ | **РЕШЕНО** (Этап 0) — вынесен в `config/project.php` |
| `templates/sections/tires.twig` | Маппинг сезонных алиасов (Лето→summer, строки 127–135) | Открыто — вынести в `data.filter.season_aliases` |
| `templates/sections/tires.twig` | `'Такие модели не найдены'` | Открыто — перенести в `data.filter.empty_text` |
| `src/Action/ApiSendAction.php` | Сообщения об ошибках валидации на русском | Открыто — вынести в конфиг/i18n |
