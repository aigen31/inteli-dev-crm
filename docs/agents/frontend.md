# Фронтенд

## Стек и структура

- Inertia 3 + Vue 3.5 + Vite 8 + Tailwind 4, без Vue Router.
- Страницы: `resources/js/Pages/Admin`, `resources/js/Pages/Client`.
- Оболочка: `Components/Layout/AppShell.vue`; `AdminLayout` и `ClientLayout` — тонкие обёртки с ролью.
- UI: `Components/UI` (Button, Card, Badge, StatCard, EmptyState, Segmented, Pagination, EarlyAccessBanner); формы: `Components/Form` (TextInput, SelectInput, TextArea, RichTextEditor).
- Домен: `Components/Task/TaskFormFields.vue`, `Components/CustomFields/*`, `Components/FormTemplates/*`, `Components/Assistant/AssistantPanel.vue`, `Components/Kiki/KikiAvatar.vue`.
- Early access: `EarlyAccessBanner` в `AppShell` (dismissible, localStorage), бейдж `beta` в шапке/сайдбаре, статичная заметка на `/login`.
- Формы заявок: `Pages/Client/Tasks/Create.vue` (библиотека + live-форма), `Pages/Client/Tasks/Scenarios.vue` (классические сценарии), `Pages/Admin/Forms/Index.vue` (библиотека форм).
- Утилиты: `Utils/constants.js`, `Utils/formatters.js`, `Composables/useAssistant.js`.

## Навигация

Короткая: 4 раздела у админа (Обзор, Задачи, Клиенты, Счета) и 3 у клиента (Обзор, Задачи, Счета). Настройки/Профиль — внизу. Шаблоны живут внутри «Задач» и на дашборде, а не отдельным пунктом. Мобильная навигация — нижний бар, assistant — кнопка «Кики».

Активный пункт считайте от `page.url`, а не `route().current()`: на полной перезагрузке Ziggy `current()` не всегда инициализирован.

## Тема и дизайн

Светлая «бумажная» тема inteli-dev: тёплый canvas, белые карточки, фиолетовый акцент, крупные радиусы, много воздуха. Токены — в `resources/css/app.css` (`canvas`, `surface`, `surface-2`, `paper`, `line`, `ink`, `muted`, `primary-*`, `accent`, `sage`, `sky`).

Шрифты: Manrope (текст), Unbounded (заголовки/цифры), JetBrains Mono (метки, ID, суммы). Дизайн-система — [.superdesign/design-system.md](../../.superdesign/design-system.md).

## Заголовки и действия

Заголовок и eyebrow рендерит `AppShell`; страница передаёт действия слотом:

```vue
<AdminLayout title="Клиенты">
    <template #actions>
        <Link :href="route('admin.clients.create')"><Button>Новый клиент</Button></Link>
    </template>
    ...
</AdminLayout>
```

Не дублируйте заголовок внутри контента.

## WYSIWYG

`RichTextEditor.vue` — TipTap. `StarterKit` уже включает Link/Underline; не подключайте их повторно. Сервер санитайзит HTML через `HtmlSanitizer`; на клиенте HTML из БД выводится через `v-html`, но только после серверной очистки. Превью в редакторе шаблона — локальное, без сохранения.

## Поля и Кики

- `CustomFieldsForm` / `CustomFieldsView` / `CustomFieldInput` — единый рендер типов; не пишите отдельные поля под каждый тип.
- `AssistantPanel` — глобальная панель (Teleport), открывается из сайдбара, `⌘K` или мобильной кнопки. Состояние — `useAssistant`.
- `KikiAvatar` — варианты `avatar`, `hero`, `flight`, `empty`, `thinking`, `seated`, `classic`; остальные иллюстрации подключаются напрямую (`kiki-seated`, `kiki-flight-portrait`, `kiki-desk`). Не рисуйте маскота и реквизит заново CSS/вектором. Новый набор и правила — [kiki-assets.md](kiki-assets.md).

## Сборка

```bash
npm run build   # public/build, не коммитится
npm run dev     # Vite dev server
```

В Docker ассеты раздаются из общего volume; после рестарта `app` они затираются версией из образа — финальную версию закрепляйте пересборкой (см. [deployment.md](deployment.md)). Root-view — `resources/views/app.blade.php`; в `<head>` обязателен `<title data-inertia>` (Inertia 3).
