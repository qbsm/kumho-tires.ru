# Сессия 2026-03-06: Создание Git-репозитория и пуш на GitHub

**Дата:** 2026-03-06
**Проект:** iSmart Platform (Kumho Tires)
**Среда:** Windows 11 Pro, bash (Git Bash), Node 24.14.0, PHP 8.5.1
**Продолжение:** `session-2026-03-06-filter-and-images.md`

---

## Содержание

1. [Контекст сессии](#1-контекст-сессии)
2. [Промт 1: Инициализация репозитория](#2-промт-1-инициализация-репозитория)
3. [Подготовка .gitignore](#3-подготовка-gitignore)
4. [Создание .gitkeep для пустых директорий сборки](#4-создание-gitkeep-для-пустых-директорий-сборки)
5. [Настройка git identity](#5-настройка-git-identity)
6. [Первый коммит](#6-первый-коммит)
7. [Промт 2: Пуш на GitHub](#7-промт-2-пуш-на-github)
8. [Настройка удалённого репозитория](#8-настройка-удалённого-репозитория)
9. [Проблема с SSH: Host key verification failed](#9-проблема-с-ssh-host-key-verification-failed)
10. [Промт 3: Personal Access Token](#10-промт-3-personal-access-token)
11. [Пуш через HTTPS с токеном](#11-пуш-через-https-с-токеном)
12. [Промт 4: Документирование](#12-промт-4-документирование)
13. [Полный список изменений](#13-полный-список-изменений)
14. [Как работать с репозиторием](#14-как-работать-с-репозиторием)

---

## 1. Контекст сессии

Продолжение сессии `session-2026-03-06-filter-and-images.md`. К началу:
- Фильтр шин на `/tires/` исправлен
- Изображения шин загружены в `data/img/tires/`
- Проект запущен на `http://localhost:8080`
- Git-репозиторий отсутствовал

---

## 2. Промт 1: Инициализация репозитория

```
данную версию продукта нужно зафиксировать в гите в виде нового
репозитория. что мне делать?
```

Выполнена инициализация:

```bash
cd /c/raznoe/orch
git init
```

---

## 3. Подготовка .gitignore

`.gitignore` в проекте уже существовал и был хорошо настроен. Добавлены два новых блока:

```diff
+# Локальные настройки Claude Code
+.claude/
+
+# Личный gitconfig (email, имя)
+.gitconfig
```

**Почему `.claude/` и `.gitconfig` исключены:**
- `.claude/` содержит локальные настройки Claude Code (история, кэш) — не нужны в репозитории
- `.gitconfig` может содержать персональный email и имя — исключён по явной просьбе пользователя

Также выполнено `git rm --cached` для файлов, которые уже попали в индекс до обновления `.gitignore`:

```bash
git rm --cached .gitconfig
git rm --cached -r .claude/
```

### Существующие правила .gitignore (ключевые)

| Правило | Что исключает |
|---------|--------------|
| `vendor/` | PHP-зависимости Composer |
| `node_modules/` | Node.js-зависимости npm |
| `.env`, `.env.*` | Переменные окружения (пароли, ключи API) |
| `assets/css/build/*` | Хешированные CSS-файлы сборки |
| `assets/js/build/*` | Хешированные JS-файлы сборки |
| `!assets/css/build/.gitkeep` | Исключение из исключения — .gitkeep отслеживается |
| `!assets/js/build/.gitkeep` | Исключение из исключения — .gitkeep отслеживается |
| `data/img/tires/` | Крупные медиафайлы шин (27 папок, ~сотни MB) |
| `logs/` | Файлы логов сервера |
| `cache/` | Кэш |

---

## 4. Создание .gitkeep для пустых директорий сборки

Git не отслеживает пустые директории. Чтобы `assets/css/build/` и `assets/js/build/`
попали в репозиторий (и `npm run build` мог в них писать), созданы пустые файлы-маркеры:

```bash
touch assets/css/build/.gitkeep
touch assets/js/build/.gitkeep
```

Соответствующие исключения уже присутствовали в `.gitignore`:

```
assets/css/build/*
!assets/css/build/.gitkeep
assets/js/build/*
!assets/js/build/.gitkeep
```

---

## 5. Настройка git identity

На машине отсутствовал глобальный `~/.gitconfig`. Git отказывается делать коммит
без `user.email` и `user.name`.

**Первая попытка (отклонена пользователем):**
```
fedorovich.d@gmail.com / Danil Fedorovich — исключить
```

Пользователь явно отказался от этих данных. Предоставлены корректные:

```
roman.skrypka@gmail.com / Roman (romanskrypka)
```

Настроены локально (только для этого репозитория, не глобально):

```bash
cd /c/raznoe/orch
git config user.email "roman.skrypka@gmail.com"
git config user.name "Roman (romanskrypka)"
```

**Почему локально, не глобально:**
- Глобальный `~/.gitconfig` попал бы под исключение `.gitignore` (он же там прописан)
- Локальная конфигурация хранится в `.git/config` — внутри `.git/`, который тоже не попадает в репозиторий
- Это стандартная практика для машин с несколькими аккаунтами

---

## 6. Первый коммит

```bash
cd /c/raznoe/orch
git add .
git commit -m "iSmart Platform"
```

Результат:

```
[master (root-commit) d14cb0c] iSmart Platform
 506 files changed, 46847 insertions(+)
```

В коммит вошло 506 файлов: PHP-код, Twig-шаблоны, CSS, JS, JSON-данные, инструменты сборки,
документация. Бинарные файлы (изображения шин, vendor, node_modules, .env) остались исключены.

---

## 7. Промт 2: Пуш на GitHub

```
нет ещё раз мне нужно на https://github.com/romanskrypka/orch.git
/ git@github.com:romanskrypka/orch.git / Как мне настроить репозиторий?
```

Пользователь заранее создал пустой репозиторий на GitHub:
**https://github.com/romanskrypka/orch**

---

## 8. Настройка удалённого репозитория

```bash
git remote add origin git@github.com:romanskrypka/orch.git
git branch -M main
```

Ветка переименована из `master` в `main` (современное соглашение GitHub).

---

## 9. Проблема с SSH: Host key verification failed

```bash
git push -u origin main
# Host key verification failed.
# fatal: Could not read from remote repository.
```

**Причина:** На машине отсутствует директория `~/.ssh` и SSH-ключи. GitHub не в `known_hosts`.

Два возможных решения:

| Метод | Плюсы | Минусы |
|-------|-------|--------|
| SSH-ключ | Постоянный, не нужен токен каждый раз | Нужно сгенерировать ключ, добавить в GitHub |
| HTTPS + Personal Access Token | Быстро, без настройки SSH | Токен нужно хранить или вводить при каждом пуше |

Выбран **HTTPS** как более быстрый вариант.

```bash
git remote set-url origin https://romanskrypka@github.com/romanskrypka/orch.git
```

---

## 10. Промт 3: Personal Access Token

```
я создал token classic
```

Пользователь создал токен через:
**GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic) → Generate new token**

Необходимый scope: **`repo`** (полный доступ к репозиторию).

---

## 11. Пуш через HTTPS с токеном

```bash
git push https://romanskrypka:<TOKEN>@github.com/romanskrypka/orch.git main
```

После пуша URL удалённого репозитория сброшен без токена (для безопасности):

```bash
git remote set-url origin https://romanskrypka@github.com/romanskrypka/orch.git
git fetch origin
git branch --set-upstream-to=origin/main main
```

Результат:

```
To https://github.com/romanskrypka/orch.git
 * [new branch]      main -> main
branch 'main' set up to track 'origin/main'
```

**Репозиторий:** https://github.com/romanskrypka/orch

---

## 12. Промт 4: Документирование

```
Задокументируй подробно в новом md файле C:\raznoe\orch\docs произведённые
изменения, процесс запуска и настройки проекта. Обязательно фиксируй промты
в документе включая этот промт.
```

Создан данный файл: `docs/session-2026-03-06-git-setup.md`.

---

## 13. Полный список изменений

| Файл | Тип | Описание |
|------|-----|----------|
| `.gitignore` | Правка | Добавлены `.claude/` и `.gitconfig` в исключения |
| `assets/css/build/.gitkeep` | Добавлено | Маркер для отслеживания пустой директории |
| `assets/js/build/.gitkeep` | Добавлено | Маркер для отслеживания пустой директории |
| `.git/config` | Создано git | Локальная конфигурация: user.email, user.name, remote origin, upstream |

---

## 14. Как работать с репозиторием

### Клонирование на новую машину

```bash
git clone https://romanskrypka@github.com/romanskrypka/orch.git /c/raznoe/orch
cd /c/raznoe/orch

# Настроить локальный git identity
git config user.email "roman.skrypka@gmail.com"
git config user.name "Roman (romanskrypka)"
```

### Последующие пуши изменений

```bash
cd /c/raznoe/orch
git add путь/к/файлу
git commit -m "Описание изменений"
git push
# При запросе пароля — вставить Personal Access Token
```

Токен можно передать прямо в URL команды (одна строка, без интерактивного запроса пароля):

```bash
git push https://romanskrypka:ВАШ_ТОКЕН@github.com/romanskrypka/orch.git main
```

Пример с конкретным токеном:

```bash
git push https://romanskrypka:ghp_ABC123@github.com/romanskrypka/orch.git main
```

Чтобы Git запомнил токен и не спрашивал его при каждом пуше (Windows Credential Manager):

```bash
# Включить хранение токена — один раз
git config credential.helper manager

# Затем обычный push — токен запросит один раз и сохранит
git push
```

### Создание нового Personal Access Token

Если текущий токен истёк или был отозван:

1. GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate new token → scope: `repo`
3. Скопировать токен (показывается один раз)
4. Использовать при `git push` как пароль

### Безопасность токена

- **Не хранить токен в коде или конфигах** — только вводить вручную при пуше
- После случайного раскрытия токена (например, в чате) — немедленно отозвать:
  GitHub → Settings → Developer settings → Personal access tokens → Revoke
- Создать новый токен

---

## 15. Справочник: Что попадает в репозиторий, что нет

| Включено в репозиторий | Исключено из репозитория |
|------------------------|--------------------------|
| PHP-код (`src/`, `config/`, `public/`) | `vendor/` (восстанавливается через `composer install`) |
| Twig-шаблоны (`templates/`) | `node_modules/` (восстанавливается через `npm install`) |
| CSS/JS-исходники (`assets/`) | `assets/css/build/*.css` (пересобирается через `npm run build:css`) |
| JSON-данные (`data/json/`) | `assets/js/build/*.js` (пересобирается через `npm run build:js`) |
| Инструменты сборки (`tools/`) | `.env` (создаётся из `.env.example`) |
| Документация (`docs/`) | `data/img/tires/` (загружается отдельно из kumho-tires.ru.git) |
| `.gitkeep`-маркеры | `.claude/` (локальные настройки Claude Code) |
| `webpack.config.js`, `postcss.config.js`, `composer.json`, `package.json` | `.gitconfig` (персональные данные) |
| `.env.example` | `logs/`, `cache/` |

### Восстановление проекта после клонирования

```bash
# 1. PHP-зависимости
composer install

# 2. Node.js-зависимости
npm install

# 3. Окружение
cp .env.example .env
# Отредактировать .env:
#   APP_BASE_URL=http://localhost:8080
#   APP_ENV=development
#   APP_DEBUG=1

# 4. Симлинки public/assets и public/data (junction, без прав администратора)
node -e "
const fs = require('fs');
const root = 'C:/raznoe/orch';
[
  [root + '/assets', root + '/public/assets'],
  [root + '/data',   root + '/public/data'],
].forEach(([target, link]) => {
  if (!fs.existsSync(link)) {
    fs.symlinkSync(target, link, 'junction');
    console.log('junction: ' + link);
  }
});
"

# 5. Сборка ассетов
npm run build:css
npm run build:js

# 6. Изображения шин (крупные файлы, не в репозитории)
git clone --depth=1 --filter=blob:none --sparse \
  https://github.com/romanskrypka/kumho-tires.ru.git \
  /c/raznoe/kumho-tmp
cd /c/raznoe/kumho-tmp
git sparse-checkout set data/img/tires
cp -r /c/raznoe/kumho-tmp/data/img/tires /c/raznoe/orch/data/img/
rm -rf /c/raznoe/kumho-tmp
cd /c/raznoe/orch

# 7. Запуск сервера
/c/php85/php.exe -S localhost:8080 -t public/
```
