# REST API v1

## Доступ

Токены создаются только админом: **Настройки → API и MCP**. Показываются один раз, хранятся как SHA-256 хеш.

- `read` — GET-ресурсы;
- `write` — создание/изменение, а также `POST /assistant/chat`;
- `expires_at` — срок; просроченный токен не проходит;
- токен наследует роль пользователя: клиентский видит только свои данные.

Заголовок:

```http
Authorization: Bearer crm_...
Accept: application/json
```

База: `/api/v1`. Ошибки: `{ "message": "...", "code": "api_token_invalid" }`; 401 — нет/плохой токен, 403 — нет права/чужой объект, 422 — валидация.

## Ресурсы

| Метод | Путь | Право |
|---|---|---|
| GET | `/ping`, `/health` | без/auth |
| GET | `/me` | read |
| GET | `/meta/overview` | read |
| GET | `/clients`, `/clients/{id}` | read |
| POST/PUT | `/clients`, `/clients/{id}` | write (admin) |
| GET | `/tasks`, `/tasks/{id}` | read (client scoped) |
| POST/PUT | `/tasks`, `/tasks/{id}` | write |
| PATCH | `/tasks/{id}/status` | write (admin) |
| GET | `/task-templates`, `/task-templates/{id}` | read |
| POST/PUT | `/task-templates`, `/task-templates/{id}` | write (admin) |
| POST | `/task-templates/{id}/apply` | write (admin) |
| GET | `/invoices`, `/invoices/{id}` | read (client scoped) |
| POST | `/invoices` | write (admin) |
| PATCH | `/invoices/{id}/mark-paid` | write (admin, transfer) |
| GET | `/custom-fields` | read |
| GET/PUT | `/custom-fields/{entity}/{id}/values` | read / write |
| GET | `/assistant/latest` | read |
| POST | `/assistant/chat` | write |

Пагинация: `per_page` 1–100 (по умолчанию 25), ответ — Laravel `data` + `links` + `meta`.

Фильтры: `status`, `client_id` (admin), `q`, `filter[key]` для custom fields.

## Примеры

Создать клиента:

```bash
curl -X POST "$CRM_API_URL/clients" \
  -H "Authorization: Bearer $CRM_TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"Acme","email":"acme@example.com","password":"secret123"}'
```

Создать задачу из шаблона:

```bash
curl -X POST "$CRM_API_URL/task-templates/$TEMPLATE_ID/apply" \
  -H "Authorization: Bearer $CRM_TOKEN" -H 'Content-Type: application/json' \
  -d '{"client_id":"'$CLIENT_ID'"}'
```

Прочитать/записать статус приёма заявок:

```bash
curl "$CRM_API_URL/clients/$CLIENT_ID" -H "Authorization: Bearer $CRM_TOKEN" \
  | jq '.data.custom_fields.intake_status'

curl -X PUT "$CRM_API_URL/custom-fields/client/$CLIENT_ID/values" \
  -H "Authorization: Bearer $CRM_TOKEN" -H 'Content-Type: application/json' \
  -d '{"fields":{"intake_status":"paused"}}'
```

Поля отдаются объектом `key → {field_id,label,type,value,display}`; дефолты включены, даже если значение ещё не сохранено.

## OpenAPI

Спецификация — [docs/api/openapi.yaml](../api/openapi.yaml); краткий старт — [docs/api/README.md](../api/README.md). Контракт ресурсов задают `app/Http/Resources/*`; при изменении полей обновляйте spec и MCP-схемы.
