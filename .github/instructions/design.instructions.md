---
description: UI-кит и дизайн-система CRM. Загружать при создании/редактировании Vue-компонентов, стилей, страниц.
applyTo: '**/*.{vue,css,js,ts,blade.php}'
---

# CRM для самозанятого — Дизайн-система (UI Kit)

> Фреймворк: **Tailwind CSS 3.x**. Все значения ниже — Tailwind-классы и кастомные токены для `tailwind.config.js`.

---

## 1. Цветовая палитра

### 1.1. Основные цвета (Brand)

| Токен | HEX | Tailwind-класс | Назначение |
|-------|-----|----------------|------------|
| `primary-50` | `#EEF2FF` | `bg-primary-50` | Фон выделенных элементов, hover-состояния |
| `primary-100` | `#E0E7FF` | `bg-primary-100` | Лёгкий фон карточек, выделение строк |
| `primary-200` | `#C7D2FE` | `bg-primary-200` | Бордер активных элементов |
| `primary-300` | `#A5B4FC` | `bg-primary-300` | Иконки второстепенные |
| `primary-400` | `#818CF8` | `bg-primary-400` | Hover кнопок |
| `primary-500` | `#6366F1` | `bg-primary-500` | **Основной акцент** — кнопки, ссылки, активный пункт меню |
| `primary-600` | `#4F46E5` | `bg-primary-600` | Hover основных кнопок |
| `primary-700` | `#4338CA` | `bg-primary-700` | Active/pressed кнопок |
| `primary-800` | `#3730A3` | `bg-primary-800` | Текст на светлом фоне (акцент) |
| `primary-900` | `#312E81` | `bg-primary-900` | Сайдбар фон |

### 1.2. Нейтральные (Gray)

| Токен | HEX | Назначение |
|-------|-----|------------|
| `gray-50` | `#F9FAFB` | Фон страницы |
| `gray-100` | `#F3F4F6` | Фон карточек, альтернативные строки таблиц |
| `gray-200` | `#E5E7EB` | Границы, разделители |
| `gray-300` | `#D1D5DB` | Placeholder текст input-ов |
| `gray-400` | `#9CA3AF` | Иконки неактивные, вспомогательный текст |
| `gray-500` | `#6B7280` | Вторичный текст |
| `gray-600` | `#4B5563` | Подписи, метки |
| `gray-700` | `#374151` | Основной текст |
| `gray-800` | `#1F2937` | Заголовки |
| `gray-900` | `#111827` | Максимальный контраст |

### 1.3. Семантические цвета

| Назначение | Цвет фона | Цвет текста/иконки | Цвет бордера | Применение |
|------------|-----------|---------------------|--------------|------------|
| Success | `green-50` | `green-700` | `green-200` | Оплачено, завершено, уведомления об успехе |
| Warning | `amber-50` | `amber-700` | `amber-200` | Ожидает оплаты, приближается дедлайн |
| Danger | `red-50` | `red-700` | `red-200` | Отклонено, ошибки валидации, удаление |
| Info | `blue-50` | `blue-700` | `blue-200` | Информационные сообщения, подсказки |

### 1.4. Цвета статусов задач

| Статус | Бейдж bg | Бейдж text | Dot-цвет |
|--------|----------|------------|----------|
| `pending_review` | `bg-amber-100` | `text-amber-800` | `bg-amber-400` |
| `queued` | `bg-blue-100` | `text-blue-800` | `bg-blue-400` |
| `in_progress` | `bg-indigo-100` | `text-indigo-800` | `bg-indigo-400` |
| `awaiting_review` | `bg-purple-100` | `text-purple-800` | `bg-purple-400` |
| `rejected_by_client` | `bg-red-100` | `text-red-800` | `bg-red-400` |
| `completed` | `bg-green-100` | `text-green-800` | `bg-green-400` |
| `rejected_by_admin` | `bg-gray-100` | `text-gray-800` | `bg-gray-400` |

### 1.5. Цвета статусов счетов

| Статус | Бейдж bg | Бейдж text |
|--------|----------|------------|
| `pending` | `bg-amber-100` | `text-amber-800` |
| `paid` | `bg-green-100` | `text-green-800` |
| `cancelled` | `bg-gray-100` | `text-gray-600` |
| `refunded` | `bg-red-100` | `text-red-800` |

### 1.6. Цвета приоритетов задач

| Приоритет | Бейдж bg | Бейдж text | Dot-цвет |
|-----------|----------|------------|----------|
| `low` | `bg-gray-100` | `text-gray-600` | `bg-gray-400` |
| `medium` | `bg-amber-100` | `text-amber-700` | `bg-amber-400` |
| `high` | `bg-red-100` | `text-red-700` | `bg-red-500` |

---

## 2. Типографика

### 2.1. Шрифт

| Параметр | Значение | Tailwind |
|----------|----------|----------|
| Основной шрифт | Inter | `font-sans` |
| Моноширинный | JetBrains Mono | `font-mono` |
| Fallback | system-ui, sans-serif | — |

**Подключение в `app.css`:**
```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
```

**`tailwind.config.js`:**
```js
fontFamily: {
  sans: ['Inter', 'system-ui', 'sans-serif'],
  mono: ['JetBrains Mono', 'monospace'],
}
```

### 2.2. Размеры текста

| Роль | Tailwind-класс | Размер | Вес | Использование |
|------|----------------|--------|-----|---------------|
| Заголовок страницы (h1) | `text-2xl font-bold` | 24px | 700 | Заголовок каждой страницы |
| Заголовок секции (h2) | `text-xl font-semibold` | 20px | 600 | Заголовки карточек, секций |
| Заголовок карточки (h3) | `text-lg font-semibold` | 18px | 600 | Название задачи в списке |
| Подзаголовок (h4) | `text-base font-medium` | 16px | 500 | Подразделы, метки форм |
| Основной текст | `text-sm` | 14px | 400 | Весь контент, описания |
| Вспомогательный текст | `text-xs text-gray-500` | 12px | 400 | Даты, мета-информация |
| Кнопка | `text-sm font-medium` | 14px | 500 | Текст внутри кнопок |
| Бейдж | `text-xs font-medium` | 12px | 500 | Статусы, теги |

---

## 3. Сетка и отступы

### 3.1. Общая сетка страницы

```
┌─────────────────────────────────────────────────────┐
│ Header (h-16, sticky top-0)                         │
├──────────┬──────────────────────────────────────────┤
│ Sidebar  │ Content area                              │
│ w-64     │ flex-1, p-6                              │
│ (admin)  │                                          │
│          │                                          │
│ w-0      │                                          │
│ (mobile) │                                          │
└──────────┴──────────────────────────────────────────┘
```

- **Sidebar:** `w-64` (256px), скрывается на мобильных (`hidden lg:block`)
- **Content area:** `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`
- **Header:** `h-16` (64px), `sticky top-0 z-30`

### 3.2. Система отступов

| Назначение | Tailwind | Пиксели |
|------------|----------|---------|
| Отступ между элементами формы | `space-y-4` | 16px |
| Отступ между секциями страницы | `space-y-6` | 24px |
| Padding карточки | `p-6` | 24px |
| Padding секции контента | `px-4 sm:px-6 lg:px-8 py-6` | 16–32px / 24px |
| Gap в grid-сетках | `gap-4 lg:gap-6` | 16–24px |
| Margin между кнопками | `space-x-3` | 12px |
| Отступ label от input | `mb-1` | 4px |

### 3.3. Сетки карточек

```
Дашборд (карточки со статистикой):   grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4
Список задач / клиентов:             Таблица (Table) на десктопе, карточки на мобильном
Детальная страница:                  grid grid-cols-1 lg:grid-cols-3 gap-6
                                     (основной контент: col-span-2, боковая панель: col-span-1)
```

---

## 4. Скругления и тени

| Элемент | Border-radius | Тень |
|---------|---------------|------|
| Карточка | `rounded-lg` (8px) | `shadow-sm` |
| Карточка hover | `rounded-lg` | `shadow-md` (transition) |
| Модальное окно | `rounded-xl` (12px) | `shadow-xl` |
| Кнопка | `rounded-md` (6px) | нет |
| Input / Select | `rounded-md` (6px) | `shadow-sm` |
| Бейдж | `rounded-full` (9999px) | нет |
| Аватар / Иконка | `rounded-full` | нет |
| Dropdown-меню | `rounded-lg` (8px) | `shadow-lg` |
| Тултип | `rounded-md` (6px) | `shadow-md` |

---

## 5. Компоненты UI

### 5.1. Button (Кнопка)

**Варианты:**

| Вариант | Классы |
|---------|--------|
| Primary | `bg-primary-500 hover:bg-primary-600 active:bg-primary-700 text-white` |
| Secondary | `bg-white hover:bg-gray-50 text-gray-700 border border-gray-300` |
| Danger | `bg-red-600 hover:bg-red-700 text-white` |
| Ghost | `bg-transparent hover:bg-gray-100 text-gray-700` |
| Link | `text-primary-600 hover:text-primary-700 underline-offset-2 hover:underline` |

**Размеры:**

| Размер | Классы |
|--------|--------|
| sm | `px-3 py-1.5 text-xs` |
| md (default) | `px-4 py-2 text-sm` |
| lg | `px-6 py-3 text-base` |

**Общие классы всех кнопок:**
```
inline-flex items-center justify-center font-medium rounded-md
transition-colors duration-150 ease-in-out
focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2
disabled:opacity-50 disabled:cursor-not-allowed
```

### 5.2. Input (Текстовое поле)

```
w-full rounded-md border border-gray-300 shadow-sm
px-3 py-2 text-sm text-gray-700 placeholder-gray-400
focus:border-primary-500 focus:ring-1 focus:ring-primary-500
disabled:bg-gray-100 disabled:text-gray-500
```

**Состояние ошибки:**
```
border-red-500 focus:border-red-500 focus:ring-red-500
```

**Label:**
```
block text-sm font-medium text-gray-700 mb-1
```

**Сообщение об ошибке:**
```
mt-1 text-xs text-red-600
```

### 5.3. Select (Выпадающий список)

```
w-full rounded-md border border-gray-300 shadow-sm
px-3 py-2 text-sm text-gray-700
focus:border-primary-500 focus:ring-1 focus:ring-primary-500
```

### 5.4. Textarea

```
w-full rounded-md border border-gray-300 shadow-sm
px-3 py-2 text-sm text-gray-700 placeholder-gray-400
focus:border-primary-500 focus:ring-1 focus:ring-primary-500
resize-y min-h-[100px]
```

### 5.5. Badge (Бейдж статуса)

```vue
<!-- Структура -->
<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium">
  <span class="w-1.5 h-1.5 rounded-full {dot-color}"></span>
  {label}
</span>
```

Цвета — по таблицам из раздела 1.4–1.6.

### 5.6. Card (Карточка)

```
bg-white rounded-lg shadow-sm border border-gray-200
```

**Card с заголовком:**
```vue
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
  <div class="px-6 py-4 border-b border-gray-200">
    <h3 class="text-lg font-semibold text-gray-800">{title}</h3>
  </div>
  <div class="p-6">
    {content}
  </div>
</div>
```

### 5.7. Table (Таблица)

```vue
<table class="min-w-full divide-y divide-gray-200">
  <thead class="bg-gray-50">
    <tr>
      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
        {column}
      </th>
    </tr>
  </thead>
  <tbody class="bg-white divide-y divide-gray-200">
    <tr class="hover:bg-gray-50 transition-colors">
      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
        {value}
      </td>
    </tr>
  </tbody>
</table>
```

### 5.8. Modal (Модальное окно)

```
Overlay:   fixed inset-0 bg-gray-900/50 z-40
Container: fixed inset-0 z-50 flex items-center justify-center p-4
Panel:     bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto
Header:    px-6 py-4 border-b border-gray-200
Body:      px-6 py-4
Footer:    px-6 py-4 border-t border-gray-200 flex justify-end space-x-3
```

### 5.9. Sidebar (Боковая панель)

```
Контейнер:          w-64 bg-primary-900 text-white min-h-screen flex flex-col
Лого:               px-6 py-5 border-b border-primary-800
Навигация:          flex-1 px-3 py-4 space-y-1
Пункт меню:         flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium
                    text-primary-200 hover:bg-primary-800 hover:text-white transition-colors
Активный пункт:     bg-primary-800 text-white
Иконка пункта:      w-5 h-5
Разделитель секций:  border-t border-primary-800 my-3
```

### 5.10. Header (Шапка)

```
h-16 bg-white border-b border-gray-200 sticky top-0 z-30
flex items-center justify-between px-4 sm:px-6 lg:px-8
```

**Элементы:**
- Слева: заголовок текущей страницы (`text-lg font-semibold text-gray-800`)
- Справа: уведомления + аватар пользователя

### 5.11. NotificationBell (Колокольчик уведомлений)

```
Иконка:      w-6 h-6 text-gray-400 hover:text-gray-600 cursor-pointer relative
Счётчик:     absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px]
             font-bold rounded-full flex items-center justify-center
Dropdown:    w-80 max-h-96 overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200
Элемент:     px-4 py-3 hover:bg-gray-50 border-b border-gray-100
             непрочитанный: bg-primary-50
```

### 5.12. Pagination (Пагинация)

```
Контейнер:   flex items-center justify-between px-6 py-3
Кнопка:      px-3 py-1.5 text-sm rounded-md border border-gray-300
             hover:bg-gray-50
Активная:    bg-primary-500 text-white border-primary-500
Disabled:    opacity-50 cursor-not-allowed
Инфо-текст:  text-sm text-gray-500 ("Показано 1–10 из 42")
```

### 5.13. FileUploader (Загрузка файлов)

```
Зона drop:   border-2 border-dashed border-gray-300 rounded-lg p-6 text-center
             hover:border-primary-400 hover:bg-primary-50 transition-colors cursor-pointer
Иконка:      w-10 h-10 text-gray-400 mx-auto mb-2
Текст:       text-sm text-gray-600 "Перетащите файл или нажмите для выбора"
Подтекст:    text-xs text-gray-400 "JPG, PNG, PDF, DOC до 20 МБ"
Файл в списке: flex items-center justify-between p-3 bg-gray-50 rounded-md
               имя: text-sm text-gray-700, размер: text-xs text-gray-400
```

### 5.14. Empty State (Пустое состояние)

```
Контейнер:   flex flex-col items-center justify-center py-12
Иконка:      w-12 h-12 text-gray-300 mb-4
Заголовок:   text-base font-medium text-gray-600 mb-1
Подтекст:    text-sm text-gray-400 mb-4
Кнопка:      Button (primary, sm)
```

### 5.15. Stat Card (Карточка статистики на дашборде)

```vue
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-sm font-medium text-gray-500">{label}</p>
      <p class="mt-1 text-2xl font-bold text-gray-800">{value}</p>
    </div>
    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
      <Icon class="w-5 h-5 text-primary-600" />
    </div>
  </div>
  <div class="mt-3 text-xs text-gray-500">
    {meta — например: "+12% за месяц"}
  </div>
</div>
```

### 5.16. Alert / Toast (Уведомления)

**Inline alert:**
```
Контейнер:   rounded-md p-4 flex gap-3
              success: bg-green-50 border border-green-200
              warning: bg-amber-50 border border-amber-200
              error:   bg-red-50 border border-red-200
              info:    bg-blue-50 border border-blue-200
Иконка:      w-5 h-5 flex-shrink-0 (цвет по семантике)
Текст:       text-sm font-medium (цвет по семантике, -700)
```

**Toast (всплывающее):**
```
Позиция:     fixed top-4 right-4 z-50
Контейнер:   bg-white rounded-lg shadow-lg border border-gray-200 p-4 w-80
             animate: slide-in-right + fade-out через 5 сек
```

---

## 6. Иконки

- **Библиотека:** Heroicons v2 (outline стиль основной, solid для активных состояний)
- **Размер по умолчанию:** `w-5 h-5`
- **Размер в sidebar:** `w-5 h-5`
- **Размер в кнопках:** `w-4 h-4` рядом с текстом, `-ml-0.5 mr-1.5`
- **Размер для empty state:** `w-12 h-12`
- **Цвет:** наследует `currentColor`

---

## 7. Анимации и переходы

| Элемент | Transition |
|---------|------------|
| Кнопка hover/focus | `transition-colors duration-150 ease-in-out` |
| Карточка hover | `transition-shadow duration-200 ease-in-out` |
| Sidebar пункт | `transition-colors duration-150` |
| Modal появление | `transition-opacity duration-200` + `transition-transform duration-200 scale-95→scale-100` |
| Toast появление | `transition-all duration-300 ease-out translate-x-full→translate-x-0` |
| Dropdown | `transition-all duration-150 ease-out opacity-0→opacity-100 scale-95→scale-100` |
| Skeleton loading | `animate-pulse bg-gray-200 rounded` |

---

## 8. Адаптивность (Breakpoints)

| Breakpoint | Tailwind | Поведение |
|------------|----------|-----------|
| Mobile | `< sm` (< 640px) | Sidebar скрыт, 1 колонка, hamburger-меню |
| Tablet | `sm` (640px) | 2 колонки на дашборде, таблица с горизонтальным скроллом |
| Desktop | `lg` (1024px) | Sidebar видим, полная таблица, 3–4 колонки на дашборде |
| Wide | `xl` (1280px) | `max-w-7xl` контейнер, дополнительные колонки |

---

## 9. tailwind.config.js — кастомная тема

```js
import defaultTheme from 'tailwindcss/defaultTheme';

export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50:  '#EEF2FF',
          100: '#E0E7FF',
          200: '#C7D2FE',
          300: '#A5B4FC',
          400: '#818CF8',
          500: '#6366F1',
          600: '#4F46E5',
          700: '#4338CA',
          800: '#3730A3',
          900: '#312E81',
          950: '#1E1B4B',
        },
      },
      fontFamily: {
        sans: ['Inter', ...defaultTheme.fontFamily.sans],
        mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
};
```

---

## 10. Темы ролей

| Элемент | Администратор | Клиент |
|---------|---------------|--------|
| Sidebar фон | `bg-primary-900` | `bg-primary-900` |
| Sidebar лого | «CRM — Управление» | «CRM — Личный кабинет» |
| Пункты меню | Клиенты, Задачи, Счета, Настройки | Мои задачи, Мои счета, Профиль |
| Дашборд карточки | 4 карточки статистики | 3 карточки (задачи, счета, ожидание) |
| Цвет акцента дашборда | `primary-500` | `primary-500` |

---

## 11. Правила для генерации компонентов

1. **Все цвета** — через Tailwind utility-классы, **не** inline `style` и не raw HEX
2. **Кастомные цвета** — только через `primary-*` токен из `tailwind.config.js`
3. **Шрифт** — всегда `font-sans` (Inter), моно только для кода / денежных сумм
4. **Spacing** — только стандартная шкала Tailwind (`1, 1.5, 2, 3, 4, 5, 6, 8, 10, 12`)
5. **Не создавать** отдельные CSS-файлы для компонентов — все стили через классы Tailwind
6. **Dark mode** — не реализуется в v1
7. **Фокус** — все интерактивные элементы **обязаны** иметь `focus:ring` для доступности
8. **Hover** — все кликабельные элементы **обязаны** иметь hover-состояние
9. **Disabled** — `opacity-50 cursor-not-allowed` для всех заблокированных элементов
10. **Skeleton** — использовать `animate-pulse` при загрузке данных