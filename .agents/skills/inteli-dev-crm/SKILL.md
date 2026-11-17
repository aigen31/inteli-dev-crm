---
name: inteli-dev-crm
description: Use when an AI agent works with the inteli-dev CRM through its MCP server — clients, projects, tasks, invoices, reports, project access and custom fields. Covers the current 39-tool MCP surface (briefs included), crm_operation, budgets/estimates, report description+link, consent and confirmation rules, and the boundary between MCP and Kiki.
license: MIT
metadata:
  version: "2.0.0"
  domain: crm
  triggers: CRM, MCP, клиенты, проекты, задачи, счета, отчёты, приглашения, кастомные поля, Kiki
  role: operator
  scope: inteli-dev-crm
---

# inteli-dev CRM через MCP

Практический skill для агента с owner-level доступом к CRM через MCP. Он
описывает актуальный контракт, порядок действий и границы, которые нельзя
нарушать. Модель данных и код — в `AGENTS.md`; здесь — **как действовать**.

## Когда использовать

- Посмотреть/создать клиента, проект, задачу, счёт или отчёт.
- Выдать доступ к проекту, пригласить участника, сменить роль, отозвать invite.
- Разобрать заявку, обновить статус, чек-лист, комментарий, бюджет или оценку.
- Создать отчёт клиенту в формате «описание + ссылка».
- Настроить шаблон задачи, кастомные поля, запустить study проекта.
- Понять, какой MCP-tool вызвать и какие поля обязательны.

Не использовать для изменения кода CRM, миграций или инфраструктуры.

## Два разных контура: MCP и Кики

Это важно: **MCP-инструменты и tools Кики — не одно и то же**.

| Контур | Кто использует | Точка входа | Что видит |
|---|---|---|---|
| MCP | внешний агент (DSH, Claude, Cursor, n8n) | `POST /api/mcp` | 30 CRM-tools |
| Кики | ассистент внутри CRM | kernel → `/api/internal/assistant` | provider-tools `CrmToolbox` |

- Массовое изменение задач (`update_tasks`, разные `updates` по задачам,
  `append_description`) и `distribute_project_budget` — **только у Кики**, не в
  MCP. Через MCP меняйте задачи по одной (`crm_operation task.update`) или
  используйте Кики.
- `crm_help` и чтение документов — только у Кики.
- Если инструмента нет в MCP, не пытайтесь вызывать его как MCP-tool и не
  обходите MCP через SQL/admin HTTP. Это другой контракт.

## Подключение

- Endpoint: `POST /api/mcp` (в Docker-сети — `http://crm-nginx/api/mcp`).
- Auth: `Authorization: Bearer $MCP_TOKEN`; токен owner-level. Не показывать в
  чате, логах, отчётах и скриншотах.
- Транспорт: JSON-RPC 2.0 over HTTP, stateless.
- Протокол: `2025-06-18` (клиент может прислать свою версию — сервер её
  подтверждает).
- Актёр: `MCP_ACTOR_ID` или старейший активный админ. Все записи атрибутируются
  ему.
- DSH: инструменты видны как `mcp__crm__<tool>`, например
  `mcp__crm__create_report`. После изменения сервера нужна новая DSH-сессия.

Если `tools/list` пуст или старый — проверьте `CRM_MCP_URL`, `CRM_MCP_TOKEN` и
перезапустите MCP-клиент/сессию. Не ходите в БД напрямую вместо MCP.

## Главный цикл

1. **Поймите задачу и роль.** Это админская операция? Клиентские данные? Деньги?
2. **Прочитайте перед записью.** `crm_overview`, `list_*`, `get_*`; не угадывайте
   UUID.
3. **Сопоставьте сущности.** Клиент — UUID или email; проект — UUID или key;
   задача — UUID или `KEY-12`; отчёт/счёт — UUID.
4. **Выберите tool.** Частые операции — именованные; остальное — `crm_operation`.
5. **Проверьте обязательные поля и ограничения** (`references/tool-catalog.md`).
6. **Для денег/разрушения — `confirm: true`** только после явного решения
   человека или прямого поручения. Не подставляйте `confirm` «на всякий случай».
7. **Выполните и перечитайте.** После записи вызовите `get_*` и проверьте
   ID/статус/URL.
8. **Отчитайтесь коротко:** что создано/изменено, ID, ссылка, следующий шаг.

## Карта инструментов (38)

| Задача | Инструменты |
|---|---|
| Сводка | `crm_overview` |
| Клиенты | `list_clients`, `get_client`, `create_client`, `update_client`, `crm_operation client.deactivate` |
| Проекты | `list_projects`, `get_project`, `create_project`, `create_project_invite`, `crm_operation project.update/archive/member.*/invite.revoke/study.*` |
| Задачи | `list_tasks`, `get_task`, `create_task`, `update_task_status`, `create_task_template`, `apply_task_template`, `crm_operation task.update/checklist/comment/task_template.*` |
| Счета | `list_invoices`, `get_invoice`, `create_invoice`, `mark_invoice_paid`, `crm_operation invoice.cancel/refund` |
| Отчёты | `list_reports`, `get_report`, `create_report` (алиас `create_task_report`), `update_report`, `delete_report` |
| Шаблоны/поля | `list_forms`, `list_task_templates`, `list_custom_fields`, `get_custom_field_values`, `set_custom_field_values`, `crm_operation form_template.toggle/custom_field.*` |
| Брифы | `list_briefs`, `get_brief`, `create_brief`, `update_brief`, `reopen_brief`, `archive_brief`, `list_brief_templates` |
| Люди | `list_users` |

Полные схемы и операции — `references/tool-catalog.md`.

## Новое в задачах: бюджет, оценка, кастомные поля

`create_task` и `crm_operation task.update` принимают:

- `budget` — число ≥ 0, рубли;
- `estimated_minutes` — 5…10 000;
- `custom_fields` — объект `key => value`;
- `type`, `priority`, `assignee_id`, `deadline`, `project_id`, `client_id`.

`apply_task_template` дополнительно принимает `project_id` и `budget`.
`task.update` не меняет статус — для статуса есть `update_task_status`.
`task.checklist` заменяет чек-лист целиком; `task.comment` добавляет комментарий.

Массовое изменение задач с разными значениями — **Кики**, не MCP.

## Отчёты: шаблон «описание + ссылка»

Основной формат:

```json
{
  "title": "Починил форму заявки",
  "recipient_email": "client@example.com",
  "task_id": "uuid (optional)",
  "template": "description_link",
  "items": [
    {
      "description": "Что сделано и как проверить",
      "link": "https://example.com/page",
      "screenshot_base64": "iVBORw0KGgo...",
      "screenshot_mime": "image/png"
    }
  ]
}
```

- `description` → доменное `summary`, `link` → `test_url`; алиасы `summary` и
  `test_url` приняты, `url` — тоже ссылка.
- `items` повторяемые; одна группа — одна законченная работа/проверка.
- Получатель (`recipient_id` или `recipient_email`) обязателен по смыслу и
  должен быть активным CRM-пользователем.
- Отчёт создаётся и отправляется сразу; письмо уходит получателю.
- Возвращаются `id`, `url`, `items`, `recipient`, `task`.
- После создания обязательно `get_report` и проверка ссылок/групп.
- `update_report` меняет отчёт: незаданные поля сохраняются, `items` заменяет
  группы, сохранённый `id` группы сохраняет её скриншот.
- `delete_report` удаляет отчёт и группы; обязателен `confirm=true`.

Подробно — `references/report-template.md`.

## Деньги и доступы

- `create_invoice`, `mark_invoice_paid`, `invoice.cancel`, `invoice.refund`,
  `client.deactivate`, `project.archive`, `custom_field.delete`,
  `task_template.delete` — только с `confirm: true`.
- `create_invoice`: задачи одного клиента; сумма > 0; для прямого перевода
  рекуррентность запрещена; создание не списывает деньги.
- `mark_invoice_paid`: только прямой перевод и только статус «ожидает оплаты».
- `create_client`: обязателен `consent_confirmed: true`; согласие пишется в
  `consent_logs` (`basis=contract`, `source=mcp`). Не подтверждайте за клиента.
- Приглашение одноразовое; ссылка показывается один раз. Роль — `manager`,
  `member` или `viewer`; срок 1–30 дней. После регистрации приглашение гаснет.
- Роли: `viewer` только смотрит; `member`/`manager` создают задачи; управление
  проектом остаётся у админа.

## Безопасность и данные

- Не выводите чужие клиентские данные без необходимости; не объединяйте
  клиентов в одном ответе.
- Не вставляйте токены, пароли, ключи, base64-скриншоты в публичные сообщения.
- Пароль нового клиента возвращается один раз (`temporary_password`); передайте
  его безопасным каналом и не сохраняйте в истории.
- Скриншоты могут содержать персональные данные — прикладывайте только по делу.
- Не читайте и не меняйте секреты, API-токены и ключи платёжного шлюза через MCP;
  `settings.get` отдаёт только проект/реквизиты переводов.
- Не обходите `confirm`, политики и валидацию прямыми SQL/HTTP-запросами.

## Ошибки и повтор

- `result.isError: true` — прочитайте текст, исправьте аргументы, повторите.
- `Unauthorized` — проверьте bearer-токен; не меняйте токен в коде.
- `ModelNotFoundException`/«не найдено» — перечитайте список, не угадывайте ID.
- Сетевой таймаут после денежного вызова — **сначала** `get_invoice`, потом
  решайте, повторять ли; не создавайте второй счёт/платёж вслепую.
- После любой записи — read-back; для отчёта проверьте URL и `items`.

## Reference guide

| Файл | Когда читать |
|---|---|
| `references/tool-catalog.md` | Схемы, обязательные поля, операции `crm_operation` |
| `references/playbooks.md` | Пошаговые сценарии: триаж, клиент+проект, отчёт, счёт, доступы, шаблоны |
| `references/data-model.md` | ID, связи, enum-статусы, бюджеты, страницы CRM |
| `references/safety.md` | Полные правила денег, согласий, PII и восстановления |
| `references/report-template.md` | Формат отчёта, примеры, ограничения скриншотов |
