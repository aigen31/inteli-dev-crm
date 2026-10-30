# MCP-сервер

`mcp/` — самостоятельный Node-сервер без зависимостей. Не ходит в БД: вызывает REST API с токеном, поэтому права, валидация и аудит остаются в Laravel.

## Запуск

```bash
cd mcp
CRM_API_URL="http://127.0.0.1:8099/api/v1" \
CRM_API_TOKEN="crm_..." \
node src/server.mjs
```

HTTP-режим:

```bash
node src/server.mjs --http 7331
# POST http://127.0.0.1:7331/mcp
```

Claude Desktop / Cursor — `mcp/README.md` содержит готовый JSON конфигурации. Токен создаётся в **Настройки → API и MCP**.

## Протокол

- stdio — JSON-RPC 2.0, по одному объекту на строку; stdout только протокол, логи в stderr;
- `initialize` → `2025-06-18`, capabilities tools/resources/prompts;
- `tools/list`, `tools/call`, `resources/list`, `resources/read`, `prompts/list`, `prompts/get`, `ping`;
- HTTP: `POST /mcp`, `GET /health`.

## Инструменты

20 инструментов: `crm_overview`, `list_clients`, `get_client`, `create_client`, `update_client`, `list_tasks`, `get_task`, `create_task`, `update_task_status`, `list_task_templates`, `apply_task_template`, `create_task_template`, `list_invoices`, `get_invoice`, `create_invoice`, `mark_invoice_paid`, `list_custom_fields`, `get_custom_field_values`, `set_custom_field_values`, `ask_assistant`.

Ресурсы: `crm://overview`, `crm://clients`, `crm://tasks`, `crm://task-templates`, `crm://invoices`, `crm://custom-fields`.

Промпты: `daily_brief`, `client_brief` (аргумент `client_id`).

## Безопасность

- Токен определяет роль и скоуп; read-only токен не выполнит write-инструмент (API вернёт 403).
- `create_invoice` и `mark_invoice_paid` помечены `destructiveHint` — клиент должен подтверждать.
- `ask_assistant` требует `write`: ассистент может менять данные.
- Не публикуйте HTTP-порт наружу; запускайте на localhost/в доверенной сети.
- Не передавайте токен в аргументах инструментов — только env.

## Тесты

```bash
cd mcp && npm test
```

5 тестов: handshake/tools/list/call по stdio, чтение клиентов, создание счёта, custom fields, отсутствие токена. Mock API не требует живого CRM.

## Добавить инструмент

1. `mcp/src/tools.mjs`: `tool(name, description, properties, required, annotations, handler)`.
2. Handler вызывает `api.get/post/...`; не дублируйте бизнес-логику.
3. Добавьте тест в `mcp/test/tools.test.mjs` (mock endpoint в `helpers.mjs`).
4. Обновите `mcp/README.md`, `docs/agents/api.md` и матрицу в `docs/agents/ai.md`, если инструмент связан с Кики.
