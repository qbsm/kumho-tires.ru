# Сессия 2026-03-07: Этап 0.5 — Инфраструктура тиражирования

## Цель

Подготовить платформу iSmart к тиражированию: PHPDoc-контракты сервисов, PSR-14 Event Dispatcher, JSON-логирование с измерением времени запросов, реестр Twig-компонентов, scaffold для создания deployment'ов.

---

## Промты пользователя

### Промт 1
> давай запланируем на основании актуального кода проекта реализацию Этап 0.5: Инфраструктура тиражирования (оставшееся)
> - Контракты сервисов — PHPDoc на все public-методы DataLoaderService, SeoService, TemplateDataBuilder, LanguageService
> - Event Dispatcher (PSR-14) — league/event, события PageLoaded, EntityResolved, SeoBuilt в PageAction
> - Реестр компонентов — каталог docs/registry/components.md всех Twig-компонентов и секций
> - Scaffold deployment — create-deployment.sh для полной структуры deployments/{client}/ (docker-compose, .env, project.php)
> - JSON-логирование — Monolog в JSON-формат, RequestDurationMiddleware, обогащение контекста (request_id, duration_ms)

Результат: детальный план из 6 шагов с анализом текущего состояния кода, оценкой трудоёмкости и порядком реализации. Использован subagent Explore для сканирования 30 компонентов и 17 секций Twig.

### Промт 2
> реализуй Этап 0.5: Инфраструктура тиражирования (оставшееся) полностью

Результат: полная реализация всех 5 подзадач (см. раздел "Произведённые изменения").

### Промт 3
> пересобери и запусти проект хочу протестировать руками и глазами

Результат: `npm run build:dev` — CSS/JS собраны (Webpack compiled successfully). PHP dev-сервер запущен на `http://localhost:8080`. Все страницы отвечают 200. Заголовки `X-Request-Id` и `X-Response-Time` присутствуют в ответах.

### Промт 4
> Задокументируй подробно в новом md файле C:\raznoe\orch\docs произведённые изменения, процесс запуска и настройки проекта. Обязательно фиксируй промты в документе включая этот промт.

Результат: данный документ.

---

## Произведённые изменения

### 1. PHPDoc-контракты сервисов

Добавлены описательные PHPDoc-блоки на все public-методы четырёх сервисов.

**`src/Service/DataLoaderService.php`** — 6 методов:

| Метод | Описание |
|---|---|
| `loadGlobal()` | Загружает global.json — глобальные данные сайта. Возвращает `[]` при отсутствии. |
| `loadPage()` | Загружает данные страницы по page_id. `null` если файл не найден. |
| `loadSeo()` | Загружает SEO-данные страницы (title, meta, json_ld). |
| `loadEntitySlugs()` | Загружает список slug'ов коллекции. Алгоритм: прямой ключ → fallback через sections. |
| `loadEntity()` | Загружает одну сущность. Проверяет item_key и visible. |
| `loadJson()` | Читает и декодирует JSON, обрабатывает пути через JsonProcessor. |

**`src/Service/SeoService.php`** — 1 метод:

| Метод | Описание |
|---|---|
| `processTemplates()` | Рекурсивно рендерит Twig-шаблоны внутри SEO-данных. При ошибке возвращает исходные данные. |

**`src/Service/LanguageService.php`** — 1 метод:

| Метод | Описание |
|---|---|
| `detect()` | Определяет язык из первого сегмента URL. Если совпадает с поддерживаемым — извлекает из сегментов. |

**`src/Service/TemplateDataBuilder.php`** — 1 метод:

| Метод | Описание |
|---|---|
| `build()` | Собирает финальный массив данных для Twig: settings, global, pageData, seo, breadcrumb, extras. |

### 2. Event Dispatcher (PSR-14)

**Зависимость:** `league/event:^3.0.3` + `psr/event-dispatcher:^1.0.0` (установлены через composer).

**Новые файлы — 3 класса событий:**

**`src/Event/PageLoaded.php`**
```php
final class PageLoaded {
    public readonly string $pageId;    // index, 404, tires-list...
    public readonly string $langCode;  // ru, en
    public readonly array $pageData;   // sections, items
    public readonly int $status;       // 200, 404
}
```
Диспатчится после определения pageId, загрузки pageData и установки HTTP-статуса.

**`src/Event/EntityResolved.php`**
```php
final class EntityResolved {
    public readonly string $entityType; // tires, news, products...
    public readonly string $slug;       // at52, dealers-2026...
    public readonly array $entity;      // данные сущности
    public readonly array $config;      // конфигурация коллекции
}
```
Диспатчится после успешного `loadEntity`, до построения SEO.

**`src/Event/SeoBuilt.php`**
```php
final class SeoBuilt {
    public readonly string $pageId;    // pageId или slug сущности
    public readonly array $seoData;    // title, meta, json_ld
    public readonly bool $isEntity;    // true для entity, false для обычной страницы
}
```
Диспатчится после формирования финальных SEO-данных.

**Интеграция в `src/Action/PageAction.php`:**
- Добавлен параметр `?EventDispatcherInterface $dispatcher = null` в конструктор (optional — не ломает существующий код).
- Добавлен приватный метод `dispatch(object $event): void` — вызывает `$this->dispatcher?->dispatch($event)`.
- Три точки диспатча:
  1. `PageLoaded` — после блока определения entity/404, перед загрузкой SEO.
  2. `EntityResolved` — в двух местах: при обнаружении entity по прямому slug и при обнаружении через коллекцию с routeParams.
  3. `SeoBuilt` — после финального формирования seoData, перед рендерингом.

**Регистрация в `config/container.php`:**
```php
EventDispatcherInterface::class => static function (): EventDispatcherInterface {
    return new EventDispatcher();
},

PageAction::class => \DI\autowire()
    ->constructorParameter('settings', \DI\get('settings'))
    ->constructorParameter('dispatcher', \DI\get(EventDispatcherInterface::class)),
```

**Зачем нужно:** Этапы 2-4 (auth, n8n, django) смогут подключаться через слушатели событий, не модифицируя PageAction. Примеры: логирование просмотров, аналитика, кэширование, уведомления.

### 3. JSON-логирование и RequestDurationMiddleware

**3.1 JSON-формат логов**

Заменён `StreamHandler` на `RotatingFileHandler` с `JsonFormatter` в `config/container.php`:

```php
$handler = new RotatingFileHandler($logFile, 14, $level);
$handler->setFormatter(new JsonFormatter());
$logger->pushHandler($handler);
```

- **Ротация:** 14 дней, файлы `logs/app-YYYY-MM-DD.log`
- **Формат:** JSON (встроенный в Monolog 3, не требует установки)
- Старый файл `logs/app.log` остаётся (исторические данные), новые записи в `logs/app-YYYY-MM-DD.log`

**Пример записи лога:**
```json
{
  "message": "request_completed",
  "context": {
    "request_id": "4a2309145ddbf26b257247701191fbe3",
    "method": "GET",
    "path": "/tires/",
    "status": 200,
    "duration_ms": 61.7
  },
  "level": 200,
  "level_name": "INFO",
  "channel": "app",
  "datetime": "2026-03-07T07:08:31.737560+00:00",
  "extra": {}
}
```

**3.2 RequestDurationMiddleware**

**Новый файл:** `src/Middleware/RequestDurationMiddleware.php`

Функционал:
- Измеряет время обработки запроса через `hrtime(true)` (наносекундная точность).
- Логирует: `request_id`, `method`, `path`, `status`, `duration_ms`.
- Добавляет заголовок `X-Response-Time: {ms}ms` к ответу.

**Порядок в middleware стеке (`config/middleware.php`):**

```php
$app->add(RequestDurationMiddleware::class);  // внутренний — оборачивает всё
$app->add(CorrelationIdMiddleware::class);     // внешний — устанавливает request_id первым
```

В Slim middleware стек LIFO: последний `$app->add()` выполняется первым. Поэтому `CorrelationIdMiddleware` (добавлен позже) выполняется первым → устанавливает `request_id` → `RequestDurationMiddleware` получает его для логирования.

**Важно:** Первоначально порядок был обратным — `request_id` оказывался пустым в логах. Исправлено перестановкой строк в middleware.php.

### 4. Реестр Twig-компонентов

**Новый файл:** `docs/registry/components.md`

Содержит полный каталог всех Twig-шаблонов проекта, сгенерированный на основе анализа кода:

| Раздел | Количество | Описание |
|---|---|---|
| Components | 30 | Переиспользуемые UI-элементы (button, card-*, slider, accordion и др.) |
| Sections | 17 | Секции страниц (header, footer, intro, content, tires, news и др.) |
| Pages | 3 | Шаблоны страниц (page.twig, tire.twig, news.twig) |

Для каждого компонента документировано:
- Путь к файлу
- Принимаемые параметры
- Включаемые компоненты (include)
- Краткое описание

Дополнительно документированы:
- Глобальные переменные (global, base_url, lang_code, page_id, csrf_token и др.)
- Schema.org микроразметка (FAQPage в accordion, Organization в header/footer/contacts)

### 5. Scaffold deployment

**Новый файл:** `tools/scaffold/create-deployment.js`

**Вызов:**
```bash
npm run create-deployment -- <client-slug>
```

**Что генерирует:**

```
deployments/<client-slug>/
├── .env                          # Переменные окружения (из шаблона)
├── .env.example                  # Шаблон .env с описаниями
├── docker-compose.yml            # PHP-FPM + Nginx (+ закомментированный n8n)
├── nginx.conf                    # Конфигурация Nginx
├── config/
│   └── project.php               # Копия project.php.dist
├── data/
│   └── json/
│       ├── global.json            # Минимальный global (lang, nav, footer)
│       └── ru/
│           ├── pages/
│           │   └── index.json     # Заглушка главной страницы
│           └── seo/
│               └── index.json     # SEO-заглушка
└── README.md                     # Инструкция по запуску
```

**Валидация:** slug формата `[a-z][a-z0-9-]*`, проверка что директория не существует.

**`package.json`:** добавлен скрипт `"create-deployment": "node tools/scaffold/create-deployment.js"`.

---

## Процесс сборки и запуска

### Предварительные требования

- PHP 8.5+ (`/c/php85/php.exe` на данной машине)
- Node.js + npm
- Composer

### Установка зависимостей

```bash
# PHP-зависимости (включая league/event)
composer install

# Node.js-зависимости
npm install
```

### Сборка ассетов

```bash
npm run build:dev
```

### Запуск dev-сервера

```bash
/c/php85/php.exe -S localhost:8080 -t public
```

Сайт доступен по адресу: `http://localhost:8080`

### Проверка новых возможностей

**Заголовки ответа (DevTools → Network → Headers):**
- `X-Request-Id: <32-символьный hex>` — уникальный ID запроса
- `X-Response-Time: <число>ms` — время обработки

**JSON-логи:**
```bash
tail -f logs/app-2026-03-07.log
```

**Тесты:**
```bash
/c/php85/php.exe vendor/bin/phpunit
# Tests: 42, Assertions: 49, Skipped: 10 — OK
```

---

## Smoke-тестирование

Проведено 2026-03-07 после реализации Этапа 0.5.

| URL | HTTP | X-Request-Id | X-Response-Time | Результат |
|---|---|---|---|---|
| `/` | 200 | ✅ | 235.75ms | OK |
| `/tires/` | 200 | ✅ | 61.7ms | OK |
| `/tires/at52/` | 200 | ✅ | 29.81ms | OK |
| `/news/` | 200 | ✅ | 28.86ms | OK |
| `/news/dealers-2026/` | 200 | ✅ | ~30ms | OK |
| `/policy/` | 200 | ✅ | ~25ms | OK |
| `/agree/` | 200 | ✅ | ~25ms | OK |
| `/buy/` | 200 | ✅ | ~25ms | OK |
| `/nonexistent/` | 404 | ✅ | 26.01ms | OK (корректный 404) |

---

## Файлы, затронутые в сессии

| Файл | Действие |
|---|---|
| `composer.json` | Изменён — добавлен `league/event:^3.0` |
| `composer.lock` | Обновлён — league/event + psr/event-dispatcher |
| `src/Service/DataLoaderService.php` | Изменён — PHPDoc на 6 public-методов |
| `src/Service/SeoService.php` | Изменён — PHPDoc на processTemplates |
| `src/Service/LanguageService.php` | Изменён — PHPDoc на detect |
| `src/Service/TemplateDataBuilder.php` | Изменён — PHPDoc на build |
| `src/Event/PageLoaded.php` | Создан — событие загрузки страницы |
| `src/Event/EntityResolved.php` | Создан — событие нахождения сущности |
| `src/Event/SeoBuilt.php` | Создан — событие построения SEO |
| `src/Action/PageAction.php` | Изменён — интеграция EventDispatcher (3 точки диспатча) |
| `src/Middleware/RequestDurationMiddleware.php` | Создан — измерение времени запроса |
| `config/container.php` | Изменён — EventDispatcher, JsonFormatter, RotatingFileHandler, RequestDurationMiddleware |
| `config/middleware.php` | Изменён — подключение RequestDurationMiddleware |
| `tools/scaffold/create-deployment.js` | Создан — генератор deployment'а |
| `package.json` | Изменён — добавлен скрипт create-deployment |
| `docs/registry/components.md` | Создан — реестр 30 компонентов + 17 секций |
| `CHANGELOG.md` | Изменён — добавлена версия 1.1.0 |
| `docs/session-2026-03-07-infrastructure.md` | Создан — данный документ |

---

## Полный список scaffold-команд (обновлённый)

| Команда | Назначение |
|---|---|
| `npm run create-deployment -- <slug>` | Новый deployment (docker-compose, nginx, .env, project.php, данные) |
| `npm run create-collection -- <slug>` | Новая коллекция (JSON для всех языков, 3 fixtures, Twig, SEO) |
| `npm run create-page -- <slug>` | Новая страница (JSON для всех языков, SEO, JS, CSS) |
| `npm run create-section -- <name>` | Новая секция (Twig, JS, CSS) |
| `npm run create-component -- <name>` | Новый компонент (Twig, JS, CSS) |
