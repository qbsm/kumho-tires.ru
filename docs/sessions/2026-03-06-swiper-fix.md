# Сессия 2026-03-06: Исправление героического баннера (Swiper v12)

**Дата:** 2026-03-06
**Проект:** iSmart Platform (Kumho Tires)
**Среда:** Windows 11 Pro, bash (Git Bash), Node 24.14.0, PHP 8.5.1
**Продолжение:** `session-2026-03-06-setup-and-refactor.md`

---

## Содержание

1. [Контекст сессии](#1-контекст-сессии)
2. [Промт 1: Обнаружение проблемы](#2-промт-1-обнаружение-проблемы)
3. [Диагностика](#3-диагностика)
4. [Промт 2: Баннер всё ещё узкий](#5-промт-2-баннер-всё-ещё-узкий)
5. [Вторая волна диагностики](#6-вторая-волна-диагностики)
6. [Итоговые изменения](#7-итоговые-изменения)
7. [Пересборка и проверка](#8-пересборка-и-проверка)
8. [Промт 3: Документирование](#9-промт-3-документирование)
9. [Как запустить проект повторно](#10-как-запустить-проект-повторно)

---

## 1. Контекст сессии

Сессия является прямым продолжением `session-2026-03-06-setup-and-refactor.md`. К началу этой сессии:

- Проект запущен на `http://localhost:8080`
- PHP-сервер: `php -S localhost:8080 -t public/`
- Симлинки настроены через Node.js junction
- CSS/JS собраны командами `npm run build:css && npm run build:js`
- Рефакторинг платформа/контент завершён и задокументирован

**Стек:**
- PHP 8.5.1 (ручная установка из ZIP в `C:\php85`)
- Slim 4 + Twig 3 + PHP-DI
- Webpack 5 + PostCSS
- Swiper **v12.1.2** (установлен через npm)

---

## 2. Промт 1: Обнаружение проблемы

```
на главной странице блок с анимацией типа главного баннера очень узкий
и мне кажется располагается не там где нужно. Например вот здесь он
корректно https://kumho-tires.ru/
```

**Что видел пользователь:** секция `.intro` с видео-баннером отображалась как
тонкая горизонтальная полоска вместо полноэкранного hero-блока.

---

## 3. Диагностика

### 3.1 Прочитанные файлы

| Файл | Цель |
|------|------|
| `templates/sections/intro.twig` | Структура секции баннера |
| `templates/components/slider.twig` | Компонент слайдера |
| `templates/sections/logoline.twig` | Логотипная лента (тоже Swiper) |
| `assets/css/sections/intro.css` | CSS баннера |
| `assets/css/components/slider.css` | CSS Swiper |
| `assets/css/base/mq.css` | Медиазапросы |
| `assets/css/base/general.css` | Базовые стили, `overflow-x: hidden` |
| `assets/js/components/slider.js` | JS инициализация общих слайдеров |
| `assets/js/sections/intro.js` | JS инициализация intro-слайдера |
| `templates/base.twig` | Структура HTML страницы |
| `templates/pages/page.twig` | Рендеринг секций |

### 3.2 Первичная гипотеза: устаревший CSS-класс Swiper

Поиском по шаблонам обнаружено:

```bash
grep -r "swiper-container" templates/
```

```
templates/components/slider.twig:    class="swiper-container ..."
templates/sections/intro.twig:  <div class="swiper-container" id="...
templates/sections/logoline.twig:    <div class="swiper-container" id="logolineSlider"
```

**Причина:** В Swiper **v9+** контейнер переименован:

| Версия | Класс контейнера |
|--------|-----------------|
| v6, v7, v8 | `.swiper-container` |
| v9, v10, v11, v12 | `.swiper` |

В проекте установлен Swiper **v12.1.2**, но все шаблоны использовали
устаревший класс `.swiper-container`. Swiper v12 не применял свои стили
и не инициализировал слайдеры на этих элементах.

Аналогичная проблема в CSS — все правила были написаны под `.swiper-container`.

---

## 4. Первое исправление: переименование класса

### 4.1 Шаблоны

**`templates/sections/intro.twig`** — строка 2:

```diff
- <div class="swiper-container" id="{{ data.slider.id|default('introSlider') }}" ...>
+ <div class="swiper" id="{{ data.slider.id|default('introSlider') }}" ...>
```

**`templates/components/slider.twig`** — строка 25:

```diff
-     class="swiper-container {% if sliderSettings.pagination is defined and not sliderSettings.pagination.enabled %}no-pagination{% endif %}"
+     class="swiper {% if sliderSettings.pagination is defined and not sliderSettings.pagination.enabled %}no-pagination{% endif %}"
```

**`templates/sections/logoline.twig`** — строка 2:

```diff
- <div class="swiper-container" id="logolineSlider">
+ <div class="swiper" id="logolineSlider">
```

### 4.2 CSS

**`assets/css/components/slider.css`** — глобальная замена:

```diff
- .swiper-container { ... }
- .slider .swiper-container { ... }
- .swiper-container:hover .swiper-button-next { ... }
+ .swiper { ... }
+ .slider .swiper { ... }
+ .swiper:hover .swiper-button-next { ... }
```
*(все вхождения `.swiper-container` → `.swiper`, replace_all)*

**`assets/css/sections/intro.css`** — строка 31:

```diff
- .intro .swiper-container {
+ .intro .swiper {
```

**`assets/css/sections/logoline.css`** — строка 10:

```diff
- .logoline .swiper-container {
+ .logoline .swiper {
```

### 4.3 JavaScript

**`assets/js/components/slider.js`** — строка 13:

```diff
- const sliders = document.querySelectorAll('.swiper-container');
+ const sliders = document.querySelectorAll('.swiper');
```

### 4.4 Сборка после первого исправления

```bash
npm run build:css && npm run build:js
```

Сборка прошла успешно. Новые хэши ассетов:
- `assets/css/build/main.aea7df32.css`
- `assets/js/build/main.js`

---

## 5. Промт 2: Баннер всё ещё узкий

```
баннер с видео как был узким так и остался
```

Пользователь прислал HTML из браузера:

```html
<section class="intro">
  <div class="swiper" id="introSlider" data-settings="[]">
    <div class="swiper-wrapper">
      <div class="section__item slide-wrap swiper-slide">
        <div class="section__subitem cover-wrap">
          <video class="intro__video cover" autoplay="" muted="" loop="" ...>
            <source src="http://localhost:8080/data/video/intro/cover.mp4" type="video/mp4">
          </video>
        </div>
        <div class="container">
          <h1 class="h1 heading ...">Шины Kumho...</h1>
        </div>
      </div>
    </div>
    ...
  </div>
</section>
```

Класс `.swiper` уже применился корректно, но баннер по-прежнему узкий.

---

## 6. Вторая волна диагностики

### 6.1 Проверка скомпилированного CSS

```bash
grep -n "\.intro" assets/css/build/main.aea7df32.css | head -30
```

Нашли: `.intro .swiper { height: 68.4rem; padding-bottom: 0; }` — правило есть.

```bash
grep -A5 "\.swiper-wrapper" assets/css/build/main.aea7df32.css
```

Результат:

```css
.swiper-wrapper {
  display: flex;
  width: 100%;
  transition-property: transform;   /* ← height: 100% ОТСУТСТВУЕТ */
}
```

### 6.2 Корневая причина

**Проблема:** `.swiper-wrapper` не имеет `height: 100%`.

Цепочка высот:

```
.intro .swiper          → height: 68.4rem   ✓  (задан явно)
  .swiper-wrapper       → height: auto      ✗  (нет правила!)
    .swiper-slide       → height: 100%      ✗  (100% от auto = 0/auto)
      .cover-wrap       → position: absolute; inset: 0  ✗  (нет опорного размера)
        <video>         → width/height: 100%  ✗  (схлопывается)
```

**Почему это работало раньше (до обновления Swiper):**
Swiper v6/v7 при инициализации через `.swiper-container` применял свой
собственный CSS (`swiper-bundle.min.css`), который включал:

```css
.swiper-wrapper {
  height: 100%;  /* ← это правило из бандла Swiper v6/v7 */
}
```

В Swiper v9+ CSS разделён на модули (`swiper/css`, `swiper/css/navigation` и т.д.)
и **не импортируется автоматически** при `import Swiper from 'swiper'`.
В проекте импортируется только JS-класс (`expose-vendors.js`), но не CSS.

Собственный CSS в `slider.css` не содержал `height: 100%` для `.swiper-wrapper`,
полагаясь на бандл старой версии.

### 6.3 Почему не импортируем Swiper CSS из пакета

Swiper v12 CSS содержит общие стили для `.swiper`, `.swiper-wrapper` и т.д.
Импорт `swiper/css` создал бы конфликты с уже написанными кастомными стилями
в `slider.css`. Правильнее добавить недостающее правило в наш кастомный файл.

---

## 7. Итоговые изменения

### `assets/css/components/slider.css`

```diff
  .swiper-wrapper {
    display: flex;
    width: 100%;
+   height: 100%;
    transition-property: transform;
  }
```

**Почему `height: 100%` безопасен:**
- Для `logoline` — у слайдов уже есть `.logoline .swiper-slide { height: auto }`,
  что переопределит высоту контента правильно
- Для `intro` — теперь wrapper растягивается до 68.4rem (или 85rem на десктопе),
  заполняя `.intro .swiper`
- Для других слайдеров с фиксированной высотой — корректно

---

## 8. Пересборка и проверка

### Пересборка CSS

```bash
cd /c/raznoe/orch
npm run build:css
```

Вывод:
```
CSS обработан и сохранен: C:\raznoe\orch\assets\css\build\main.d836c36c.css
Создан манифест: C:\raznoe\orch\assets\css\build\css-manifest.json
Содержимое манифеста: { 'main.css': 'assets/css/build/main.d836c36c.css' }
Удален старый CSS файл: main.ba27e6cd.css
```

### Проверка скомпилированного CSS

```bash
grep -A5 "\.swiper-wrapper" assets/css/build/main.d836c36c.css
```

```css
.swiper-wrapper {
  display: flex;
  width: 100%;
  height: 100%;              /* ← добавлено */
  transition-property: transform;
}
```

---

## 9. Промт 3: Документирование

```
Задокументируй подробно в новом md файле C:\raznoe\orch\docs произведённые
изменения, процесс запуска и настройки проекта. Обязательно фиксируй промты
в документе.
```

Создан данный файл: `docs/session-2026-03-06-swiper-fix.md`.

---

## 10. Полный список изменённых файлов

| Файл | Тип изменения | Описание |
|------|--------------|----------|
| `templates/sections/intro.twig` | Правка | `swiper-container` → `swiper` |
| `templates/components/slider.twig` | Правка | `swiper-container` → `swiper` |
| `templates/sections/logoline.twig` | Правка | `swiper-container` → `swiper` |
| `assets/css/components/slider.css` | Правка | Все `.swiper-container` → `.swiper` + добавлен `height: 100%` на `.swiper-wrapper` |
| `assets/css/sections/intro.css` | Правка | `.intro .swiper-container` → `.intro .swiper` |
| `assets/css/sections/logoline.css` | Правка | `.logoline .swiper-container` → `.logoline .swiper` |
| `assets/js/components/slider.js` | Правка | `querySelectorAll('.swiper-container')` → `.swiper` |

---

## 11. Как запустить проект повторно

### Предварительные требования

```
C:\php85\php.exe          ← PHP 8.5.1 (ручная установка из ZIP)
node / npm                ← Node 24+
composer                  ← PHP-зависимости
```

### Полный запуск с нуля

```bash
# 1. Зависимости
cd /c/raznoe/orch
composer install
npm install

# 2. Настройка окружения
cp .env.example .env
# Отредактировать .env:
#   APP_BASE_URL=http://localhost:8080
#   APP_ENV=development
#   APP_DEBUG=1

# 3. Симлинки (без прав администратора — junction)
node -e "
const fs = require('fs');
const path = require('path');
const root = 'C:/raznoe/orch';
const targets = [
  [root + '/assets', root + '/public/assets'],
  [root + '/data',   root + '/public/data'],
];
targets.forEach(([target, link]) => {
  if (!fs.existsSync(link)) {
    fs.symlinkSync(target, link, 'junction');
    console.log('Created junction: ' + link + ' -> ' + target);
  } else {
    console.log('Already exists: ' + link);
  }
});
"

# 4. Сборка ассетов
npm run build:css
npm run build:js

# 5. Запуск сервера
/c/php85/php.exe -S localhost:8080 -t public/
```

### Только пересборка ассетов (после изменений)

```bash
npm run build:css          # только CSS (быстро, ~1 с)
npm run build:js           # только JS (~1 с в dev-режиме)
npm run build:css && npm run build:js   # оба
```

---

## 12. Справочник: Swiper v9+ vs v6/v7

| Аспект | Swiper v6/v7 | Swiper v9+ |
|--------|-------------|-----------|
| Класс контейнера | `.swiper-container` | `.swiper` |
| CSS | В бандле (`swiper-bundle.min.css`) | Разделён на модули (`swiper/css`) |
| Импорт CSS | Автоматически через бандл | Нужно явно: `import 'swiper/css'` |
| Инициализация | `new Swiper('.swiper-container', opts)` | `new Swiper('.swiper', opts)` или `new Swiper(el, opts)` |
| `.swiper-wrapper height` | Из бандла CSS (`height: 100%`) | Нужно задать самостоятельно |

**Вывод:** При обновлении Swiper с v6/v7 до v9+ необходимо:
1. Переименовать класс контейнера `.swiper-container` → `.swiper` во всех шаблонах и CSS
2. Добавить `height: 100%` для `.swiper-wrapper` в кастомном CSS (либо импортировать `swiper/css`)
3. Обновить `querySelectorAll('.swiper-container')` в JS-инициализаторах
