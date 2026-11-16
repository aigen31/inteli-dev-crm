# Брифы

> Маршрут из `AGENTS.md` · Код: `app/Services/Briefs`, `app/Http/Controllers/{Admin,Client}/BriefController.php` · UI: `resources/js/Pages/{Admin,Client}/Briefs`

Клиентские брифы заменили Telegram Mini App из проекта `inteli-dev-brif-telegram-bot`. Никакого бота и deep-link-ов: доступ даёт аккаунт CRM, бриф живёт в кабинете клиента.

## Что есть

- **Шаблоны** — JSON-схемы в `database/data/brief-templates/*.json`, загружаются командой `php artisan briefs:seed`. Новый бриф = новый файл, без правок кода.
- **Бриф** — экземпляр шаблона, привязанный к клиенту и (опционально) проекту.
- **Мастер** — пошаговое заполнение с автосохранением, условными полями (show_if) и живым прогрессом.
- **Отправка** — фиксирует снимок схемы, пишет ревизию и создаёт задачу на разбор (`TaskSource::Brief`).
- **Экспорт** — JSON, Markdown и PDF (Dompdf) из карточки брифа.

## Статусы

`draft → in_progress → submitted → archived`, плюс `submitted → in_progress` (админ возвращает на доработку). Пока статус `draft`/`in_progress`, клиент может менять ответы; после отправки — только читать.

## Движок схем

`App\Services\Briefs\BriefSchema`:

| Метод | Что делает |
|---|---|
| `normalize()` | приводит секции и поля к предсказуемому виду |
| `hash()` | sha256 канонической схемы — растёт `version` у шаблона |
| `isVisible()` | вычисляет `show_if` (`eq`, `neq`, `in`, `not_in`, `contains`, `answered`, `not_answered`, `gt`, `lt`, `all`, `any`) |
| `validate()` | ошибки по ключам полей, только для видимых полей |
| `progress()` | процент по обязательным видимым полям |

Клиентское зеркало — `resources/js/Utils/briefs.js` (`isVisible`, `briefProgress`, `answerSections`). Если меняешь правила видимости, меняй оба места и тест `tests/Feature/BriefTest.php`.

## Типы полей

`text`, `textarea`, `number`, `scale`, `date`, `url`, `email`, `phone`, `single_choice`, `multi_choice`, `contact_block`, `file`.

`file` использует общий загрузчик CRM (`POST /files/upload`), в ответе хранится id вложения.

## Права

- Админ видит все брифы, создаёт, архивирует, возвращает на доработку, экспортирует.
- Клиент видит свои брифы и брифы проектов, где он участник (`Brief::scopeVisibleTo`).
- Заполнять может админ или владелец брифа, пока бриф не отправлен (`BriefPolicy::fill`).

## Проверка изменения

```bash
docker compose exec app php artisan test --filter=BriefTest
docker compose exec app vendor/bin/pint --test
npm run build
```

Смоук: `/admin/briefs` → создать бриф → открыть в кабинете клиента → заполнить, отправить → карточка админа, экспорт PDF.

## Шаблоны: кто чем управляет

| Действие | Где | Кто |
|---|---|---|
| Посмотреть каталог | `/admin/briefs/templates` | админ |
| Создать шаблон | чат с Кики, `create_brief_template` | Кики (админ) |
| Изменить шаблон | чат с Кики, `update_brief_template` (подтверждение) | Кики (админ) |
| Удалить шаблон | `/admin/briefs/templates` или `delete_brief_template` (подтверждение) | админ / Кики |
| Список для внешних агентов | MCP `list_brief_templates` | MCP (read-only) |

Редактора схемы в админке нет намеренно: правила видимости и валидации живут в `BriefSchema`, и второй UI
неизбежно разошёлся бы с движком. Кики собирает схему по описанию, `BriefSchema::validateDefinition()`
проверяет типы, пустые секции и обязательные `options`, а версия растёт автоматически.

Удалить шаблон можно только пока по нему нет брифов: `briefs.brief_template_id` — внешний ключ, а отправленные
брифы хранят снимок схемы. Используемый шаблон сначала архивируют вместе с брифами.

## Инструменты для агентов

- CRM MCP: `list_briefs`, `get_brief`, `create_brief`, `reopen_brief`, `archive_brief` (confirm=true), `list_brief_templates`.
- Кики: `list_briefs`, `get_brief` (видимость по `Brief::scopeVisibleTo`), `list_brief_templates`, `create_brief` (клиент и проект выводятся из `task_id`, подтверждение в чате), `create_brief_template`, `update_brief_template`, `delete_brief_template` (правка и удаление — с подтверждением).
- Отправка брифа и экспорт PDF/JSON/Markdown остаются в UI: файлы и подпись клиента не отдаются агентам.

PDF собирается Dompdf из `resources/views/briefs/pdf.blade.php`; шрифт — DejaVu Sans (кириллица).
