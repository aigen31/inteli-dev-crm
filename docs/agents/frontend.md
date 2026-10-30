# Фронтенд

## Стек и структура

- Inertia 3 + Vue 3.5 + Vite 8 + Tailwind 4, без Vue Router.
- Страницы: `resources/js/Pages/Admin`, `resources/js/Pages/Client`.
- Оболочка: `Components/Layout/{AdminLayout,ClientLayout,GuestLayout}.vue`.
- UI-кит: `Components/UI` (Button, Card, Badge, Modal, Pagination), формы: `Components/Form`.
- Утилиты: `Utils/constants.js` (лейблы статусов и способов оплаты), `Utils/formatters.js` (даты, деньги).

Переиспользуйте существующие компоненты вместо локальных стилей: кнопки, поля, бейджи и таблицы уже приведены к теме.

## Роутинг и ссылки

- Глобальный `route()` из Ziggy; данные приходят через `HandleInertiaRequests`, `@routes` не нужен.
- Активный пункт меню считайте от `page.url`, а не от `route().current()`: на полной перезагрузке Ziggy `current()` не всегда инициализирован.
- Алиас `@` → `resources/js` (`vite.config.js`).

## Тема

Бренд — тёмный фиолетовый `inteli-dev.ru`. Токены объявлены в `resources/css/app.css` через `@theme`:

`canvas`, `surface`, `surface-2`, `line`, `line-strong`, `ink`, `ink-soft`, `muted`, `faint`, `primary-soft`, `success`, `warning`, `danger`.

Шрифты: Inter (текст), JetBrains Mono (метки, суммы, даты). `tailwind.config.js` в проекте нет — тему задают токены `@theme` и утилиты.

## Оболочка и заголовки

Плавающая стеклянная «пилюля»: бренд, nav-ссылки, пользователь, выход; на мобиле — выпадающая панель. Заголовок страницы и eyebrow рендерит layout. Действия страницы передавайте слотом, а не отдельной строкой под заголовком:

```vue
<AdminLayout title="Клиенты">
    <template #actions>
        <Link :href="route('admin.clients.create')"><Button>Создать клиента</Button></Link>
    </template>
    ...
</AdminLayout>
```

Не дублируйте заголовок страницы внутри контента (`<h2>` с тем же текстом): layout уже его вывел.

## Таблицы, пустые состояния, статусы

- Шапки таблиц — моноширинные, верхний регистр.
- Для пустого списка добавляйте строку с `colspan` и моно-надписью («Записей пока нет»).
- Статусы и способ оплаты — только через `Badge` (`type="task" | "invoice" | "payment" | "priority"`), цвета берутся из карт компонента.
- `Badge` должен оставаться реактивным: его цвета вычисляются от props через `computed`, чтобы статус менялся без перезагрузки.

## Сборка

```bash
npm run build   # public/build, не коммитится
npm run dev     # Vite dev server
```

После правок Vue/CSS пересоберите ассеты. В Docker они раздаются из общего volume; финальную версию закрепляйте пересборкой образа (см. [deployment.md](deployment.md)).

Root-view — `resources/views/app.blade.php` (`@vite`, `@inertia`); фон задан на `html`, чтобы длинные страницы не давали белых полос. В `<head>` используйте `<title data-inertia>` — в Inertia 3 старый атрибут `inertia` больше не работает.

## Дизайн-источник

`.superdesign/design-system.md` — актуальная дизайн-система и токены; `.superdesign/init/` — снимок компонентов и страниц для Superdesign. `.github/instructions/design.instructions.md` описывает прежнюю светлую тему — для новых работ не используйте.
