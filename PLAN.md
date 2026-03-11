# План: стандартизация системы кнопок

## Изменения в `assets/css/base/buttons.css`

### 1. Базовый `.button` — border-radius: 0 (квадратная по умолчанию)

### 2. Четыре модификатора формы (не зависят от размера кнопки)
| Класс | border-radius | Описание |
|---|---|---|
| `.button-square` | `0` | квадратная |
| `.button-rounded` | `0.4rem` | скругленная (как form-callback) |
| `.button-pill` | `sm: 2rem, md: 2.5rem, lg: 3rem` | закругленная (зависит от размера) |
| `.button-circle` | `50%` | круглая (уже есть) |

### 3. Удалить устаревшие модификаторы
- `.button-md.button-rounded-sm`

### 4. Обновить JSON — добавить `button-rounded` к кнопкам, которые сейчас полагались на дефолтный `border-radius: 1rem`
- `data/json/global.json` — cookie-кнопки (button-sm)
- `data/json/ru/pages/contacts.json` — кнопка (button-sm)
- `data/json/ru/pages/warranty.json` — кнопка (button-sm button-3)
- `templates/sections/tires.twig` — кнопка (button-sm)
