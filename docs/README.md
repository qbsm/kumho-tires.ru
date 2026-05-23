# docs — документация deployment'а kumho-tires.ru

Локальная документация конкретного deployment'а. Дополняет baseline-документацию (`ismart-platform/docs/`), не дублирует её.

См. [ADR-0008](https://github.com/qbsm/ismart-platform/blob/main/docs/architecture/decisions/0008-multi-deployment-docs.md) — структура и flow.

## Что лежит здесь

```
docs/
  README.md         # этот файл
  sessions/         # сессии работы с этим deployment'ом
    YYYY-MM-DD-<topic>.md
  proposals/        # deployment-инициированные proposals
    NNNN-<topic>.md
```

### `sessions/`

Сессии работы конкретно с этим deployment'ом:
- фиксы вёрстки/UX
- миграции контента
- содержательные сессии с claude / разработчиком
- explore'ы / refactor'ы

Файл по дате: `YYYY-MM-DD-<кратко-о-сессии>.md`. Содержание линейное, без P1.1/P2.x иерархий — Проблема → Решение → План в 3 абзацах.

### `proposals/`

Идеи и паттерны замеченные при работе с этим deployment'ом, которые могут стать **общесистемными** (baseline ADR):

- «У нас 3 раза подряд ломалось X — может быть, сделать Y частью платформы?»
- «Не хватает функции в `picture.twig`/`DataExtension`/scaffold — предлагаю …»
- «Контракт JSON для секции Z в нашем deployment'е, может быть, стандартизировать?»

Нумерация **локальная** (0001, 0002, …) — не конфликтует с baseline proposals (другая папка).

**Статусы proposals** (в шапке файла):
- `Status: Proposed` — на review (baseline orchestrator увидит при следующем `npm run orchestrate`)
- `Status: Migrated to baseline ADR-NNNN` — приято в baseline, имплементировано, раскатано sync'ом
- `Status: Superseded by baseline proposals/NNNN-*.md` — выросло в cross-deployment proposal в baseline
- `Status: Rejected` — отклонено (с reason в начале)

**Не удалять** proposal после миграции/отклонения — обновлять статус (см. [feedback-proposals-lifecycle](https://github.com/qbsm/ismart-platform/blob/main/CLAUDE.md)).

## ADR / архитектурные решения

ADR живут **только в baseline**: `ismart-platform/docs/architecture/decisions/`. Сюда не пишем.

Когда deployment proposal принят и реализован — baseline создаёт ADR и распространяет код через `distill sync`. Локальный proposal помечается `Migrated to baseline ADR-NNNN`.

## Cross-deployment агрегация

Baseline orchestrator (`npm run orchestrate` в `ismart-platform/`) сканирует `<deployment>/docs/proposals/*.md` всех siblings'ов, группирует похожие темы — выявляет паттерны для общесистемных ADR.

Если у вас на этом deployment'е появилось предложение — просто положите его в `docs/proposals/NNNN-*.md`. Оркестратор увидит при следующем проходе.
