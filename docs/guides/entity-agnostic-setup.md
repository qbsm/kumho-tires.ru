# Инструкция: отвязка проекта от конкретной сущности (рестораны, шины и т.д.)

Чтобы проект не был привязан к одной сущности (рестораны, шины, товары, события), при смене типа каталога нужно заменить перечисленные ниже места. Удобно задать три идентификатора и идти по списку:

- **`{slug}`** — slug в URL каталога (например `tires`, `restaurants`, `products`)
- **`{list_id}`** — page_id страницы списка (например `tires-list`, `restaurants-list`)
- **`{item_key}`** — ключ объекта элемента в JSON (например `tire`, `restaurant`, `product`)

---

## 1. Конфигурация PHP

| Файл | Что заменить |
|------|-------------------------------|
| **config/settings.php** | В `route_map`: ключ и значение — `'{slug}' => '{list_id}'` (напр. `'tires' => 'tires-list'`). В `sitemap_pages`: заменить page_id списка на `{list_id}`. |

---

## 2. Загрузка данных (DataLoaderService)

| Файл | Что заменить |
|------|-------------------------------|
| **src/Service/DataLoaderService.php** | Либо оставить одну пару методов под вашу сущность (напр. `loadTireSlugs` / `loadTire`), либо дублировать по аналогии. Пути: список slug'ов — `{lang}/pages/{slug}.json` (ключ `items`); один элемент — `{lang}/{slug}/{slug}.json`. Проверка ключа в данных: `empty($data['{item_key}'])` (напр. `tire`, `restaurant`). |

Конкретно:

- Метод загрузки списка slug'ов: путь к файлу вида `.../pages/{slug}.json`, возврат `$data['items']`.
- Метод загрузки элемента: путь `.../{lang}/{slug}/{slug}.json`, проверка `$data['{item_key}']` и при необходимости `$data['visible']`.

---

## 3. Роутинг и шаблоны страниц (PageAction)

| Файл | Что заменить |
|------|-------------------------------|
| **src/Action/PageAction.php** | 1) Имя переменной сущности (напр. `$tire` → `$product`) и вызовы загрузки (напр. `loadTireSlugs` / `loadTire`). 2) Выбор шаблона: `pages/{item_key}.twig` (напр. `tire.twig`, `restaurant.twig`). 3) Методы SEO и хлебных крошек: имена типа `buildSeoForTire`, `buildTireBreadcrumb`; внутри — ключ данных `['{item_key}']`, заголовок списка по умолчанию («Шины», «Рестораны» и т.д.), проверка `href === '{slug}'` для пункта навигации. 4) В `$extras` передаётся переменная с именем сущности (напр. `tire`, `restaurant`) и `breadcrumb`. |

Итого в PageAction:

- Переменные и вызовы загрузки под вашу сущность.
- Шаблон детальной страницы: `pages/{item_key}.twig`.
- Два метода: построение SEO и хлебных крошек для элемента; внутри — использование `{item_key}` и `{slug}`.

---

## 4. Генерация llms-full (GEO)

| Файл | Что заменить |
|------|-------------------------------|
| **config/llms-full.php** | В коллекции: `list_path` → `{lang}/pages/{slug}.json`, `item_dir` → `{lang}/{slug}`, `name_key` (и при необходимости `desc_key`, `visible_key`) — под структуру элемента, например `{item_key}.name`, `desc.short`. В `fields` — ключи вида `{item_key}.field_name` под ваши поля. |

---

## 5. JSON: страницы и данные

| Что | Путь / структура |
|-----|-------------------|
| Список slug'ов элементов | **data/json/{lang}/pages/{slug}.json** — объект с массивом `items`: `["slug-1", "slug-2", ...]`. |
| Страница списка | **data/json/{lang}/pages/{list_id}.json** — секция с именем, совпадающим с секцией в шаблоне (напр. `tires`, `restaurants`), и данными (heading и т.д.). |
| Один элемент | **data/json/{lang}/{slug}/{item-slug}.json** — объект с ключом **`{item_key}`** (напр. `tire`, `restaurant`) и полями внутри; при необходимости `slug`, `visible`, `desc`. |
| SEO страницы списка | **data/json/{lang}/seo/{list_id}.json** — title, meta, og для страницы каталога. |

Пример структуры элемента:

```json
{
  "slug": "my-item",
  "visible": true,
  "{item_key}": {
    "name": "Название",
    "code": "ABC123"
  },
  "desc": { "short": "...", "full": "..." }
}
```

---

## 6. Шаблоны Twig

| Файл | Что заменить |
|------|-------------------------------|
| **templates/sections/{slug}.twig** (или аналогичное имя секции списка) | Имя секции (класс), путь к списку slug'ов: `pages/{slug}.json`, путь к элементу: `{slug}/{slug}.json`, ключ данных `{item_key}` (напр. `tire`), подключение карточки: `components/card-{item_key}.twig`. |
| **templates/pages/{item_key}.twig** | Шаблон детальной страницы: переменная из `extras` (напр. `tire`, `restaurant`), обращение к полям через `{item_key}.field` (напр. `tire.name`). |
| **templates/components/card-{item_key}.twig** | Карточка в списке: приём `item` с ключом `{item_key}`, ссылка `/{item.slug}/`, вывод полей из `item.{item_key}`. |
| **templates/sections/logoline.twig** | Если логолайн выводит элементы каталога: ключ в `data` (напр. `data.tires`, `data.restaurants`), путь к элементу `{slug}/{slug}.json`, отображение полей из `item.{item_key}`. |

Имена файлов секции и карточки должны совпадать с выбранным именованием (напр. `tires.twig` + `card-tire.twig` или `restaurants.twig` + `card-restaurant.twig`).

---

## 7. Навигация и главная страница

| Файл | Что заменить |
|------|-------------------------------|
| **data/json/global.json** | В `nav` для каждого языка: пункт с `href: "/{slug}/"` и нужным `title` (напр. «Шины», «Рестораны», «Каталог»). |
| **data/json/{lang}/pages/index.json** | Секция logoline (или аналог): в `data` массив slug'ов под выбранным ключом (напр. `tires`, `products`), чтобы шаблон logoline подставлял ссылки на элементы. |

---

## 8. CSS

| Файл | Что заменить |
|------|-------------------------------|
| **assets/css/sections/{slug}.css** (или по имени секции списка) | Стили секции каталога и сетки карточек. |
| **assets/css/components/card-{item_key}.css** | Стили карточки элемента. |
| **assets/css/pages/{slug}.css** или **assets/css/pages/{item_key}.css** | Стили детальной страницы элемента. |
| **assets/css/main.css** | Импорты указанных файлов (если имена меняются). |

---

## 9. Краткий чеклист под новую сущность

1. **config/settings.php** — `route_map`, `sitemap_pages`.
2. **src/Service/DataLoaderService.php** — пути к `pages/{slug}.json` и `{lang}/{slug}/*.json`, ключ `{item_key}` в данных.
3. **src/Action/PageAction.php** — переменные, вызовы загрузки, шаблон детальной страницы, методы SEO и breadcrumb, проверка `href === '{slug}'`, `$extras`.
4. **config/llms-full.php** — пути и ключи коллекции под `{item_key}`.
5. **data/json** — `pages/{slug}.json`, `pages/{list_id}.json`, `seo/{list_id}.json`, каталог `{lang}/{slug}/*.json` с ключом `{item_key}` в каждом файле.
6. **templates** — секция списка, `pages/{item_key}.twig`, `card-{item_key}.twig`, при необходимости logoline.
7. **data/json/global.json** — пункт навигации с `/{slug}/`.
8. **data/json/.../pages/index.json** — данные для logoline (массив slug'ов под выбранным ключом).
9. **assets/css** — стили секции, карточки и страницы элемента, импорты в main.css.

---

## 10. Рекомендация: одна настраиваемая сущность

Чтобы не дублировать код при смене типа каталога, можно вынести сущность в конфиг и использовать один набор методов и шаблонов:

- В **config/settings.php** добавить, например, `'catalog' => ['slug' => 'tires', 'list_page_id' => 'tires-list', 'item_key' => 'tire']`.
- В **DataLoaderService** оставить один метод загрузки slug'ов и один — элемента, читая пути и ключ из `settings['catalog']`.
- В **PageAction** один ветка «каталог»: загрузка по slug, один шаблон детальной страницы (напр. `pages/catalog-item.twig`), один метод SEO и один — breadcrumb, везде использующие `settings['catalog']` и общий ключ в данных (напр. `item`).
- В JSON структура элемента с единым ключом (напр. `item`) и полями внутри; при смене типа каталога меняется только конфиг и содержимое JSON, без правок PHP и имён шаблонов.

Тогда замена «рестораны / шины» на «продукты» сводится к правке конфига, JSON и текстов (заголовки, навигация), без изменений в коде загрузки и роутинга.
