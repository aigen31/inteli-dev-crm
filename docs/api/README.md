# REST API v1

Базовый URL: `http://localhost:8099/api/v1` (локально) или `https://crm.inteli-dev.ru/api/v1`.

## 1. Токен

**Настройки → API и MCP → Новый API-токен**. Выберите права:

- `read` — чтение;
- `write` — создание/изменение и `POST /assistant/chat`.

Токен показывается один раз. Передавайте его только в заголовке:

```http
Authorization: Bearer crm_...
Accept: application/json
```

Токен наследует роль пользователя: клиентский токен видит только свои задачи, счета и поля.

## 2. Быстрый старт

```bash
export CRM_API_URL="http://127.0.0.1:8099/api/v1"
export CRM_TOKEN="crm_..."

curl -s "$CRM_API_URL/me" -H "Authorization: Bearer $CRM_TOKEN" | jq
curl -s "$CRM_API_URL/clients" -H "Authorization: Bearer $CRM_TOKEN" | jq
curl -s "$CRM_API_URL/tasks?status=in_progress" -H "Authorization: Bearer $CRM_TOKEN" | jq
```

Создать задачу:

```bash
curl -X POST "$CRM_API_URL/tasks" \
  -H "Authorization: Bearer $CRM_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "client_id": "'$CLIENT_ID'",
    "title": "Правка на сайте",
    "description": "<p>Поменять заголовок на главной</p>",
    "priority": "medium"
  }'
```

Создать из шаблона:

```bash
curl -X POST "$CRM_API_URL/task-templates/$TEMPLATE_ID/apply" \
  -H "Authorization: Bearer $CRM_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"client_id":"'$CLIENT_ID'"}'
```

## 3. Пользовательские поля

Определения:

```bash
curl -s "$CRM_API_URL/custom-fields?entity_type=client" \
  -H "Authorization: Bearer $CRM_TOKEN" | jq '.data[] | {key,type,options}'
```

Значения клиента (включая дефолты):

```bash
curl -s "$CRM_API_URL/clients/$CLIENT_ID" \
  -H "Authorization: Bearer $CRM_TOKEN" | jq '.data.custom_fields'
```

Запись (admin):

```bash
curl -X PUT "$CRM_API_URL/custom-fields/client/$CLIENT_ID/values" \
  -H "Authorization: Bearer $CRM_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"fields":{"intake_status":"paused","preferred_channel":"telegram"}}'
```

`intake_status` — пример поля «Статус приёма заявок» (`open|limited|paused|closed`). Оно доступно и в REST, и через MCP `get_custom_field_values`.

## 4. Пагинация и фильтры

- `per_page` — 1–100, по умолчанию 25; ответ содержит `data`, `links`, `meta`.
- `q` — поиск по имени/названию.
- `status` — статус задачи/счёта.
- `client_id` — только для admin-токена.
- `filter[key]=value` — фильтр по пользовательскому полю (например, `filter[intake_status]=open`).

## 5. Ошибки

```json
{ "message": "У токена нет права «write».", "code": "api_ability_denied" }
```

- `401` — нет/просрочен токен;
- `403` — нет права или чужой объект;
- `422` — валидация (`errors`);
- `429` — rate limit (120 запросов/мин).

## 6. OpenAPI и MCP

- Спецификация: [openapi.yaml](openapi.yaml).
- MCP-сервер: [mcp/README.md](../../mcp/README.md) — те же данные и действия для ИИ-клиентов.
- Ассистент: `POST /assistant/chat` (нужен `write`), `GET /assistant/latest`.
