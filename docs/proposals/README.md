# proposals

Deployment-инициированные proposals — идеи и паттерны замеченные при работе с этим deployment'ом, кандидаты на cross-deployment baseline ADR.

## Naming convention

`NNNN-<topic-kebab>.md` (4-значная нумерация, локальная — не пересекается с baseline).

## Когда писать proposal

- Замечен повторяющийся паттерн (3+ раз одинаковая проблема в работе с deployment'ом)
- Не хватает функции в platform-ядре, можно было бы добавить в `picture.twig` / `DataExtension` / scaffold
- Контракт JSON в этом deployment'е, может быть, стандартизировать?

## Статусы

В шапке файла обновляется `Status:`:

- `Status: Proposed` — на review (baseline orchestrator увидит при `npm run orchestrate`)
- `Status: Migrated to baseline ADR-NNNN` — принято в baseline
- `Status: Superseded by baseline proposals/NNNN-*.md` — выросло в cross-deployment proposal
- `Status: Rejected` — отклонено, с reason в начале файла

**Не удалять** proposal — обновлять Status. См. memory `feedback-proposals-lifecycle`.

## Cross-deployment агрегация

`tools/orchestrator/analyzers/deployment-proposals.mjs` в baseline сканирует proposals всех siblings, группирует по keyword overlap. Когда 2+ deployments на схожую тему — паттерн → кандидат на baseline ADR.
