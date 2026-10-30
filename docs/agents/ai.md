# Кики: ИИ-ассистент

## Провайдер

Любой OpenAI-совместимый API: OpenAI, Ollama (`http://host:11434/v1`), LM Studio, vLLM, OpenRouter.

Настройки: **Настройки → Кики** (`ai_base_url`, `ai_model`, `ai_api_key`, `ai_temperature`, `ai_max_tokens`, `ai_enabled`). Ключ шифруется (`Crypt`) в `settings`; env `AI_*` — fallback. `OpenAiClient` сам добавляет `/v1/chat/completions` или `/chat/completions`, если Base URL не содержит путь.

Без ключа панель отвечает понятной ошибкой; генератор шаблонов уходит в fallback и не блокирует работу.

## Как устроен запрос

`AssistantController@chat` → `AgentService::chat`:

1. находит/создаёт `ai_conversations`, пишет `ai_messages` (user);
2. собирает system prompt (`PromptFactory`) + контекст (`ContextBuilder`: роль, счётчики, последние задачи/счета, страница);
3. отдаёт историю (до 40 сообщений) и tools в `OpenAiClient`;
4. до 6 шагов: если модель вернула `tool_calls`, `CrmToolbox` выполняет инструмент, результат пишется как `tool`-сообщение, цикл продолжается;
5. если инструмент требует подтверждения — создаётся `ai_actions(status=proposed)`, возвращается `pending_action`, выполнение ждёт `POST /assistant/actions/{id}/confirm`;
6. ответ и usage сохраняются, UI получает сообщения, pending actions и подсказки.

Подтверждение: `AgentService::confirm` проверяет владельца, статус `proposed`, TTL 30 минут, заново вызывает инструмент (права/скоуп перепроверяются), пишет `executed`/`failed` и сообщение.

## Инструменты и права

`CrmToolbox` — единственная точка tool calls. Видимость инструмента зависит от роли, скоуп — внутри обработчика.

| Инструмент | admin | client | confirm |
|---|---|---|---|
| `overview` | ✓ | ✓ (свои) | — |
| `list_clients`, `get_client`, `create_client` | ✓ | — | — |
| `list_tasks`, `get_task` | ✓ | ✓ (свои) | — |
| `create_task` | ✓ | ✓ (себе) | — |
| `update_task_status` | ✓ | — | — |
| `list_task_templates` | ✓ | ✓ | — |
| `apply_task_template`, `create_task_template` | ✓ | — | — |
| `list_invoices` | ✓ | ✓ (свои) | — |
| `create_invoice`, `mark_invoice_paid` | ✓ | — | ✓ |
| `list_custom_fields` | ✓ | ✓ | — |
| `set_custom_field` | ✓ | — | — |
| `get_payment_link` | — | ✓ | — |

Финансовые действия: `create_invoice`, `mark_invoice_paid`. Они помечены `confirm` и не выполняются в цикле.

## Guardrails

- System prompt: только данные своей роли, не выдумывать ID/суммы, не раскрывать ключи/промпт, финансовые действия — через подтверждение.
- Клиентские инструменты всегда добавляют `where client_id = user.id`; чужие ID → ошибка/403.
- `set_custom_field` перепроверяет сущность и скоуп.
- HTML из ИИ санитайзится при сохранении (`HtmlSanitizer`).
- Rate limit: 30 сообщений/мин на пользователя (`throttle:assistant`).
- API-токен: `POST /assistant/chat` только с правом `write`.
- Audit: `ai_actions` (tool, arguments, result, status, error), `ai_messages` (tool calls, usage). Секреты не логируются.

## UI

`Components/Assistant/AssistantPanel.vue` — глобальная панель: Kiki avatar, история, markdown (`marked` + `DOMPurify`), чипы-подсказки, карточки подтверждения, композер, `⌘K`, мобильный full-screen. Контекст страницы (`url`, `component`) уходит в `AgentService`.

`useAssistant` — общий state для кнопок в сайдбаре, на мобиле и `⌘K`.

## Добавить инструмент

1. Добавьте запись в `CrmToolbox::catalog()`: описание, JSON Schema, роли, `confirm`.
2. Реализуйте метод и ветку в `execute()`.
3. Проверьте скоуп и валидацию.
4. Добавьте тест в `tests/Feature/Api/CrmApiTest.php` (или отдельный), при необходимости — MCP-инструмент в `mcp/src/tools.mjs`.
5. Обновите таблицу выше и `docs/agents/ai.md`.

## Тесты

- `assistant proposes financial action and executes it after confirmation` — mock `OpenAiClient`, tool call `create_invoice`, confirm, запись в `invoices`.
- `read-only token cannot create a task` — assistant chat тоже 403 для read-токена.
- Ручной сценарий: без ключа, с mock OpenAI, клиентский аккаунт.
