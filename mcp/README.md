# MCP-сервер inteli-dev CRM

Локальный MCP-сервер без внешних зависимостей. Даёт ИИ-клиенту (Claude Desktop, Cursor, Codex, собственный агент) доступ к клиентам, задачам, шаблонам, счетам, пользовательским полям и Кики.

## Что умеет

- **20 инструментов**: `crm_overview`, `list_clients`, `get_client`, `create_client`, `update_client`, `list_tasks`, `get_task`, `create_task`, `update_task_status`, `list_task_templates`, `apply_task_template`, `create_task_template`, `list_invoices`, `get_invoice`, `create_invoice`, `mark_invoice_paid`, `list_custom_fields`, `get_custom_field_values`, `set_custom_field_values`, `ask_assistant`.
- **Ресурсы** `crm://overview`, `crm://clients`, `crm://tasks`, `crm://task-templates`, `crm://invoices`, `crm://custom-fields`.
- **Промпты** `daily_brief` и `client_brief`.
- **stdio** по умолчанию и **HTTP** (`--http 7331`, POST `/mcp`) для локальных клиентов.

## Настройка

1. Создайте токен в CRM: **Настройки → API и MCP → Новый API-токен**. Для чтения достаточно `read`, для действий — `write`.
2. Задайте окружение:

```bash
export CRM_API_URL="http://127.0.0.1:8099/api/v1"
export CRM_API_TOKEN="crm_..."
node mcp/src/server.mjs
```

3. Claude Desktop (`claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "inteli-crm": {
      "command": "node",
      "args": ["/mnt/disk1/work/inteli-dev-crm/mcp/src/server.mjs"],
      "env": {
        "CRM_API_URL": "http://127.0.0.1:8099/api/v1",
        "CRM_API_TOKEN": "crm_..."
      }
    }
  }
}
```

## HTTP-режим

```bash
node mcp/src/server.mjs --http 7331
curl -s http://127.0.0.1:7331/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'
```

## Безопасность

- Токен наследует роль пользователя: клиентский токен видит только свои данные, административный — всё.
- Денежные инструменты (`create_invoice`, `mark_invoice_paid`) помечены `destructiveHint`; MCP-клиент должен подтверждать их.
- `ask_assistant` использует те же права и подтверждения, что и панель Кики.
- Сервер не хранит токен и не пишет в stdout ничего, кроме JSON-RPC.

## Тесты

```bash
cd mcp
npm test
```

Тесты не требуют живого CRM: поднимают локальный mock API и проверяют протокол, инструменты и права.
