# iSmart Platform — синхронизация экземпляров

Действует для проектов на платформе **iSmart Platform** (`ismart-platform`) — единая архитектура Slim 4 + PHP-DI + Twig + Symfony Mailer + league/event.

Текущие экземпляры:

- `italy-platform` — каноническая база
- `kumho-tires.ru` — домен авто-шин (RL/CDN, FTP-деплой)
- `bp` — Бипитрон (миграция с легаси PHP)
- `ismart-platform` — template-репозиторий платформы (стартовая точка для новых проектов)

Цель документа — дать однозначные правила, что обязано быть синхронизировано между экземплярами iSmart Platform, что может отличаться, и как именно поддерживать консистентность при правках.

---

## 1. Что обязательно идентично (iSmart Platform core)

Изменения в этих файлах применяются СИНХРОННО ко всем экземплярам iSmart Platform, отдельным коммитом в каждом репозитории с одинаковым сообщением (`fix(middleware): …` и т. п.).

| Файл / директория                                                                                                        | Назначение                                  |
| ------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------- |
| `src/Action/PageAction.php`                                                                                              | Generic-обработчик страниц и коллекций      |
| `src/Service/DataLoaderService.php`                                                                                      | Загрузка JSON-страниц/сущностей             |
| `src/Service/SeoService.php`, `SeoBuilderRegistry.php`, `SeoBuilderInterface.php`                                        | SEO pipeline                                |
| `src/Service/TemplateDataBuilder.php`                                                                                    | Сборка контекста для Twig                   |
| `src/Middleware/*` (Redirect, TrailingSlash, SecurityHeaders, Cors, RateLimit, Language, RequestDuration, CorrelationId) | PSR-15 middleware                           |
| `src/Event/*`, `src/EventListener/*`                                                                                     | События приложения                          |
| `src/Support/*` (BaseUrlResolver, JsonProcessor, …)                                                                      | Утилиты ядра                                |
| `src/Handler/*` (HttpErrorHandler, ServerErrorHandler)                                                                   | Обработчики ошибок                          |
| `config/middleware.php`                                                                                                  | **Порядок регистрации middleware (см. §3)** |
| `config/container.php` (DI-биндинги ядра)                                                                                | Без проектной специфики                     |
| `config/dependencies.php`, `config/errors.php`                                                                           | Базовая обвязка                             |
| `public/index.php`                                                                                                       | Bootstrap Slim                              |
| `composer.json` (lock-зависимости)                                                                                       | Версии Slim/PHP-DI/Twig идентичны           |

**Правило:** перед началом работы в файле из этой таблицы делаем `diff` с другими экземплярами iSmart Platform. После правки — `diff` снова, должен совпасть.

```bash
diff italy-platform/src/Action/PageAction.php kumho-tires.ru/src/Action/PageAction.php
diff italy-platform/src/Action/PageAction.php bp/src/Action/PageAction.php
```

---

## 2. Что отличается per-project (project-specific)

| Область                             | Где                                                                                                           |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Контент                             | `data/json/{lang}/...`                                                                                        |
| Вёрстка                             | `templates/sections/*.twig`, `templates/components/*.twig`, `templates/pages/*.twig`                          |
| CSS / JS                            | `assets/css/**`, `assets/js/**`                                                                               |
| Коллекции, route_map, sitemap_pages | `config/project.php` или раздел `collections` в `config/settings.php`                                         |
| Конфиг безопасности (CSP)           | `src/Middleware/SecurityHeadersMiddleware.php::DEFAULT_CSP` — может расширяться под внешние источники проекта |
| Деплой / FTP / vhost                | `.env`, `deploy/*`, hooks                                                                                     |

Эти файлы можно (и нужно) править независимо. Но **не копируйте сюда логику, которая должна жить в core** (см. §1).

---

## 3. Critical: порядок middleware

Slim 4: «last added = outermost = runs first».  
`addRoutingMiddleware()` бросает `HttpNotFoundException` для путей вне роутера. Если он зарегистрирован ПОСЛЕ `RedirectMiddleware`/`TrailingSlashMiddleware` — наши middleware не отработают, и префиксные SEO-редиректы (например `/img/* → /data/img/*`) будут возвращать 404 вместо 301.

Канонический порядок (`config/middleware.php`):

```php
$app->add(RequestDurationMiddleware::class);
$app->add(CorrelationIdMiddleware::class);
$app->add(SecurityHeadersMiddleware::class);
$app->add($container->get(CorsMiddleware::class));
$app->addBodyParsingMiddleware();
$app->add($container->get(RateLimitMiddleware::class));
$app->add(LanguageMiddleware::class);

$app->addRoutingMiddleware();   // ← ДО Redirect / TrailingSlash

$app->add(RedirectMiddleware::class);
$app->add(TrailingSlashMiddleware::class);
```

Не менять без причины и без синхронизации между всеми тремя.

---

## 4. URL-политика (общая для трёх проектов)

- **Без trailing slash.** Всегда. `/about`, не `/about/`.
- `/path/` → 301 → `/path` через `TrailingSlashMiddleware`.
- Префиксные SEO-редиректы (например после миграции `/img/* → /data/img/*`) — через `RedirectMiddleware` правила вида `{"from_prefix": "...", "to_prefix": "...", "status": 301}`.
- Никогда не кладём редирект-логику в шаблоны/PageAction — только через middleware.

---

## 5. CSP (Content Security Policy)

`src/Middleware/SecurityHeadersMiddleware.php::DEFAULT_CSP` — единственное место, где CSP для проекта прописывается явно.

Базовый профиль (italy):

```
default-src 'self'; script-src 'self' 'unsafe-inline'; ...
```

Если проект подключает внешние https-скрипты (Yandex.Метрика, api-maps.yandex.ru, smartcaptcha.yandexcloud.net, Google Analytics) — расширяем до:

```
default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src ... https:;
font-src 'self' https:; connect-src 'self' https: wss:; ...
```

Когда добавляете внешний `<script src="https://…">` или `fetch()` к внешнему домену — сразу обновляйте CSP в этом проекте. **Не правьте CSP в других проектах**, у них свой набор интеграций.

---

## 6. Когда фиксите баг — синхронизация

Чек-лист для правок в файлах из §1:

1. Воспроизвести баг в текущем проекте, починить.
2. Открыть тот же файл в двух других проектах — баг там тоже есть?
3. Если да — отдельный commit в каждом репозитории, **то же самое сообщение коммита** (можно адаптировать under-text про конкретный симптом, но scope+title идентичны).
4. После синхронизации — `diff` всех трёх копий. Различия допустимы только если они project-specific (комментарии, имена констант под язык, проектные настройки).
5. Если правка структурная (новый middleware, новый event, новый сервис) — пройти все три проекта в одном пуше работы, не оставлять «сделаю потом».

Пример из истории: `addRoutingMiddleware()` стоял в неправильном месте во всех трёх. Починили в bp → сразу же синхронизировали italy и kumho тремя одинаковыми коммитами (`fix(middleware): routing раньше redirect/trailing-slash`).

---

## 7. Добавление новой коллекции (entity)

Только в `config/project.php` (kumho) или раздел `collections` в `config/settings.php` (italy/bp). Без правок PHP в `PageAction`.

Минимальный набор полей коллекции:

```php
'collection-name' => [
    'data_dir'     => 'foo',          // data/json/{lang}/foo/{slug}.json
    'item_key'     => 'item',         // ключ внутри файла, либо ''
    'nav_slug'     => 'foo',          // '/foo' в URL
    'list_page_id' => 'foo-list',     // pages/foo-list.json
    'slugs_source' => 'items',        // где искать список slug'ов
    'slugs_page'   => 'foo-list',     // (опц.) если slug'и не на странице nav_slug
    'extras_key'   => 'foo',          // ключ в Twig-extras
    'og_type'      => 'website',
    'entity_url_pattern' => '/foo/{slug}',
    // (опц.) сортировка items по полю
    'sort_by' => 'date', 'sort_format' => 'd.m.Y', 'sort_dir' => 'desc',
],
```

Если для типа сущности нужен спец-шаблон (kumho-style) — `'template' => 'pages/foo.twig'`. Тогда `pages/{list_page_id}.json` НЕ должен содержать sections (entity рендерится напрямую через шаблон).

Если `template` не задан — sections из `pages/{list_page_id}.json` сохраняются и работают через extras (bp-style: `service-intro` + `service-info` читают `entity.item` через extras).

**Что нельзя**: специальные case'ы под конкретную коллекцию в `PageAction`. Если нужна новая способность (например nested-маршрут `/category/{cat_id}/product/{id}`) — расширяйте generic-логику и описывайте поведение через config.

---

## 8. Нейминг (одинаковые правила)

См. отдельные guides (идентичны между проектами):

- `css-naming.md` (italy) / `css-rules.md` (kumho) — BEM, kebab-case
- `twig-naming.md` / `twig-rules.md` — kebab-case files, `sections/` + `components/`
- `html-naming.md` / `html-rules.md` — `section__item`, `section__subitem`, `{block}__{element}`
- `json-naming.md` / `json-rules.md` — kebab-case keys, snake_case недопустим
- `js-naming.md` / `js-rules.md`

Перед мерджем больших правок — пробежать `naming-compliance-report.md`.

---

## 9. Data migration: не теряйте данные

Случай из истории: при миграции `data/json/products.json` (44 продукта) в `data/json/ru/products/{id}.json` ID продуктов из разных категорий перезаписали друг друга — осталось 10 файлов вместо 44, потеряны 34 продукта.

Правила миграции:

1. **Сначала diff количества.** До коммита миграции:
   ```python
   src_count = len(json.load(open('legacy.json'))['items'])
   new_count = len(glob.glob('new/*.json'))
   assert src_count == new_count, f'lost {src_count - new_count}'
   ```
2. **Уникальные slug'и.** Если legacy id повторяются — собирайте составной slug (`{cat_id}-{id}`), не надейтесь, что id глобальны.
3. **Smoke-test после миграции.** Открыть представителя каждой категории/типа — он отображается?
4. **Коммит миграции отдельный.** Никогда не миксуйте data-migration с code-changes в одном коммите.

---

## 10. Запрещено упрощать

При миграции legacy → новая архитектура **переносить 1:1**. Не выкидывать sections/components «потому что выглядят неиспользуемыми». Если конкретный блок реально не нужен — отдельный коммит «remove» с явным обоснованием в сообщении, не часть «refactor».

---

## 11. Commits

- `feat(scope): …` / `fix(scope): …` / `refactor(scope): …` / `chore(scope): …`
- Сообщения коммитов на русском допускаются (исторически принято).
- **Никогда** не добавлять `Co-Authored-By` (global rule пользователя).
- Не использовать `--amend` для merged-коммитов и hooks (`--no-verify`) без явного разрешения.
- Pre-commit хуки не отключать — если падают, чинить причину.

---

## 12. Где живёт этот документ

Канонический источник — `italy-platform/docs/guides/multi-project-sync.md`.  
Копии (зеркала) лежат во всех экземплярах iSmart Platform:

- `ismart-platform/docs/guides/multi-project-sync.md`
- `kumho-tires.ru/docs/guides/multi-project-sync.md`
- `bp/docs/guides/multi-project-sync.md`

При изменении правил — обновляются все копии в одном проходе, как и любой core-файл.
