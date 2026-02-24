# Отчёт о соответствии html-rules.md и css-rules.md

Проверка выполнена по состоянию репозитория. Ниже — нарушения и рекомендации.

---

## 1. Идентификаторы (`id`) — html-rules.md, §3

**Правило:** для всех `id` используется **camelCase**.

### Нарушения

| Файл                                        | Текущее значение                                                                | Как должно быть                                                                                                                                                                                                                                                                                                                          |
| ------------------------------------------- | ------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| **templates/components/accordion.twig**     | `id="accordion-{{ accordion_id\|default('faq') }}"`                             | `id="accordion{{ (accordion_id\|default('faq'))                                                                                                                                                                                                                                                                                          | capitalize }}"`→ например`accordionFaq`(camelCase без дефисов). Для триггера/панели:`accordionTriggerFaq0`, `accordionPanelFaq0`. |
| **templates/components/accordion.twig**     | `id="accordion-trigger-{{ accordion_id }}-{{ loop.index0 }}"`                   | В camelCase: например `accordionTriggerFaq0` (собирать без дефисов: accordion + Trigger + Id + index).                                                                                                                                                                                                                                   |
| **templates/components/accordion.twig**     | `id="accordion-panel-{{ accordion_id }}-{{ loop.index0 }}"`                     | Аналогично: `accordionPanelFaq0`.                                                                                                                                                                                                                                                                                                        |
| **templates/components/form-callback.twig** | `form_uid = 'form-callback-' ~ random(1000000)`; id вида `{{ form_uid }}-phone` | По гайду: один экземпляр — `formCallbackPhone`, `formCallbackName` и т.д.; несколько — `{formId}{Field}` в camelCase. Нужно: `form_uid = 'formCallback' ~ random(1000000)` и id без дефиса: `id="{{ form_uid }}Phone"`, `id="{{ form_uid }}Name"`, `id="{{ form_uid }}Square"`, `id="{{ form_uid }}Policy"`, `id="{{ form_uid }}Error"`. |

Исправление потребует правок в JS, которые обращаются к этим id (селекторы, `aria-controls`, `aria-labelledby`, `for` у label).

---

## 2. Хардкод цветов в CSS — css-rules.md, §4 и §5

**Правило:** в стилях использовать переменные `var(--color-*)`, не hex в компонентах. Hex допустим только в `base/variables.css`.

### Нарушения

| Файл                               | Строки / фрагмент                                                               | Рекомендация                                                                                                                      |
| ---------------------------------- | ------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| **components/form-callback.css**   | `color: #6C6B6B;`, `#f44336`, `#4CAF50`, `#666`                                 | Вынести оттенки в `variables.css` (например `--color-placeholder`, `--color-error`, `--color-success`) и использовать `var(...)`. |
| **components/custom-checkbox.css** | `border-color: #888;`, `stroke: #fff;`                                          | `#fff` → `var(--color-1)`; для `#888` добавить переменную или использовать существующий цвет.                                     |
| **base/helpers.css**               | `border-color: #888;`, `stroke: #fff;` (в блоке, связанном с иконкой/чекбоксом) | Аналогично — перейти на переменные.                                                                                               |
| **sections/intro.css**             | `--swiper-navigation-color: #fff;`, `background: #fff !important;`              | Заменить на `var(--color-1)`.                                                                                                     |
| **components/card-gradient.css**   | `#000` в gradient                                                               | Использовать `var(--color-2)` (или подходящий цвет из палитры).                                                                   |
| **components/button-section.css**  | `#000`, `#3A9550`, `#008ACF`, `#C72229`                                         | Вынести в переменные или использовать `--color-*` из палитры, где подходит.                                                       |

Файл **base/variables.css** и значения в нём (в т.ч. hex) — соответствуют гайду.

---

## 3. Класс `.button-icon` в form-callback.css — css-rules.md, §1 и §4

**Правило:** один блок = один файл; селекторы по BEM внутри компонента.

В **components/form-callback.css** объявлен класс **`.button-icon`** (строка ~231) — он не входит в блок `form-callback` (не `form-callback__...`). Если это элемент формы — переименовать в `form-callback__button-icon` (или аналогичный элемент) и оставить в этом файле. Если это глобальная утилита — перенести в **base/helpers.css** или в общий файл кнопок.

---

## 4. Компонент card-restaurant и префикс элементов — html-rules.md, §1

**Правило:** в компоненте нейминг строго **`component__element`** (имя компонента в kebab-case + `__` + элемент).

В **components/card-restaurant.css** и, вероятно, в разметке карточки используются элементы с префиксом **`card__`** (например `card__item`, `card__bg`, `card__icon`, `card__logo`), а не **`card-restaurant__`**. Для полного соответствия гайду стоит использовать `card-restaurant__item`, `card-restaurant__bg` и т.д. Если короткий префикс `card__` оставляют намеренно (общий подблок «карточка»), это стоит явно зафиксировать в html-rules.md как исключение.

---

## 5. Что проверено и соответствует

- **Классы:** BEM-подобная схема, kebab-case, использование `section__item`, `section__subitem`, `section__inner` в секциях.
- **Обёртки:** классы на `-wrap` (heading-wrap, title-wrap, button-wrap и т.д.) используются вместе с элементами секции/компонента.
- **Утилиты:** нет устаревших `link-color-*`, `font-1-*`; используются `color-*`, `weight-*`.
- **Контейнеры:** используются `container`, `container-sm`, `container-md`, `container-left`, `container-left offset` (по гайду).
- **Data-атрибуты:** в проверенных фрагментах не встречены имена в snake_case или с заглавными буквами.
- **Имена секций в JSON:** в `sections` используются kebab-case (`header`, `content-container`, `form-container`, `footer`, `restaurants` и т.д.).
- **Медиа-запросы в CSS:** в исходных файлах используются кастомные медиа из **base/mq.css** (`--lg`, `--xl` и т.д.), хардкода пикселей в условиях не найдено.
- **Структура CSS:** один блок — один файл в `sections/` и `components/`; порядок импортов в main.css соответствует css-rules.md (base → sections → components → pages).
- **Состояние:** используется составной селектор вида `.form-callback__error.hidden` (блок + состояние).

---

## Рекомендуемый порядок правок

1. **id в camelCase** — accordion.twig и form-callback.twig + обновить JS и атрибуты `aria-controls`, `aria-labelledby`, `for`.
2. **Цвета в CSS** — заменить hex на переменные в перечисленных файлах; при необходимости расширить `variables.css`.
3. **Класс .button-icon** — либо переименовать в элемент form-callback, либо вынести в helpers/base.
4. **card-restaurant** — при желании привести элементы к префиксу `card-restaurant__*` и обновить шаблоны и стили; иначе зафиксировать исключение в гайде.

После изменений имеет смысл снова прогнать линтер и визуально проверить форму, аккордеон и карточки.
