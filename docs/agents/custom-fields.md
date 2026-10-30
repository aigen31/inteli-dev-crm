# Пользовательские поля

## Модель

`custom_fields` — определение, `custom_field_values` — значение для конкретной сущности.

Определение:

- `entity_type`: `client|task|invoice`;
- `key` (уникален в рамках сущности), `label`, `type`;
- `options` для select/multiselect (`{value,label,color?}`);
- `default_value`, `is_required`, `is_active`, `show_in_list`, `client_editable`;
- `help_text`, `placeholder`, `sort_order`, `validation`.

Типы: `text`, `textarea`, `number`, `currency`, `date`, `select`, `multiselect`, `checkbox`, `url`, `email`, `phone`.

Значение хранится типизированно:

| Колонка | Типы |
|---|---|
| `value_text` | text, textarea, select, url, email, phone |
| `value_number` | number, currency |
| `value_date` | date |
| `value_boolean` | checkbox |
| `value_json` | multiselect |

Это даёт индексируемые фильтры и предсказуемый API вместо одного JSON-блоба.

## Сервис

`App\Services\CustomFieldService`:

- `definitions(entity)` / `definitionByKey`;
- `sync(model, values, actor)` — валидация, typed storage, required-check, удаление пустых;
- `setValue(model, key, value)` — точечно (использует Кики);
- `serializedDefinitions` / `serializedValues` — для UI;
- `customFieldsWithDefaults(model)` — API-объект с дефолтами;
- `applyFilters(query, entity, filters)` — фильтры `filter[key]=value`.

Валидация: правила зависят от типа; required проверяется после записи. Ошибки приходят как `custom_fields.<key>`.

## Пример: статус приёма заявок

В сиде создаётся поле:

- `entity_type=client`, `key=intake_status`, тип `select`;
- варианты: `open` («Принимаю заявки»), `limited`, `paused`, `closed`;
- `default_value=open`, `show_in_list=true`, `is_required=true`.

API:

```bash
curl -H "Authorization: Bearer $CRM_TOKEN" \
  "$CRM_API_URL/clients/$CLIENT_ID" | jq '.data.custom_fields.intake_status'
# { "field_id": "...", "label": "Статус приёма заявок", "type": "select",
#   "value": "open", "display": "Принимаю заявки" }

curl -X PUT -H "Authorization: Bearer $CRM_TOKEN" -H 'Content-Type: application/json' \
  "$CRM_API_URL/custom-fields/client/$CLIENT_ID/values" \
  -d '{"fields":{"intake_status":"paused"}}'
```

MCP: `get_custom_field_values`, `set_custom_field_values`.

## Добавление поля

UI: **Настройки → Поля → Новое поле**. Ключ можно оставить пустым — сгенерируется `field_xxx`; лучше задать осмысленный (`intake_status`). Для select добавьте варианты, иначе валидация не пропустит.

Код: `CustomField::create([...])` + `CustomFieldService::assertKeyAvailable()`. Не пишите значения напрямую в `custom_field_values` — теряется типизация.

## Клиентские поля

`client_editable` показывается в клиентской форме заявки; админские поля клиент не меняет. API-клиент читает только свои значения; запись — только админ.

## Фильтры и списки

- `show_in_list` выводит поле в карточке клиента.
- API: `GET /api/v1/tasks?filter[acceptance_criteria]=...` (для multiselect — `whereJsonContains`).
- Не добавляйте поля с ключами, занятыми в других сущностях, если не уверены: уникальность — в рамках `entity_type`.
