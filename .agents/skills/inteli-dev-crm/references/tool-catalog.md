# MCP tool catalog

32 tools: 16 read, 16 write (including `crm_operation`). JSON-RPC envelope:

```json
{"jsonrpc":"2.0","id":1,"method":"tools/call",
 "params":{"name":"get_task","arguments":{"task":"KOR-12"}}}
```

Result: `result.structuredContent` (object), `result.content[0].text` (JSON),
`result.isError` (boolean). Validation errors are readable text with `isError`.
Wrong token → HTTP 401.

## Read tools

| Tool | Arguments | Returns |
|---|---|---|
| `crm_overview` | — | counts, overdue, pending/paid amounts, recent tasks |
| `list_clients` | `query?`, `active?`, `limit?` | clients with task/invoice counts |
| `get_client` | `client` (UUID/email) | client, recent tasks/invoices, custom fields |
| `list_projects` | `query?`, `client_id?`, `include_archived?`, `limit?` | projects |
| `get_project` | `project` (UUID/key) | project, members, invites, recent tasks, progress |
| `list_tasks` | `query?`, `project_id?`, `client_id?`, `status?`, `limit?` | tasks |
| `get_task` | `task` (UUID/`KEY-12`) | task, checklist, comments, reports, invoices |
| `list_invoices` | `status?`, `client_id?`, `limit?` | invoices |
| `get_invoice` | `invoice` (UUID) | invoice, tasks, client, payment URLs |
| `list_reports` | `recipient_id?`, `task_id?`, `limit?` | reports |
| `get_report` | `report` (UUID) | report with description/link groups |
| `list_users` | `role?` (`admin`/`client`) | active users |
| `list_forms` | `active_only?` | form templates + schema |
| `list_task_templates` | `client_id?`, `active_only?` | templates |
| `list_custom_fields` | `entity_type?` | field definitions |
| `get_custom_field_values` | `entity_type`, `entity_id` | typed values |

`limit` caps at 100; `get_project`/`get_task` include only recent nested items —
use the corresponding list for the full set.

## Named write tools

| Tool | Required | Notes |
|---|---|---|
| `create_client` | `name`, `email`, `consent_confirmed=true` | consent logged; temp password returned once |
| `update_client` | `client` + `name` + `email` | service requires name/email; phone/contacts optional |
| `create_project` | `name` | `client_id`, `key`, `site_url`, status, priority, dates, instructions optional |
| `create_task` | `client_id`, `title`, `description` | type/status/priority/assignee/deadline/`budget`/`estimated_minutes`/checklist/`task_template_id`/`custom_fields` optional |
| `update_task_status` | `task`, `status` | allowed transition; optional comment |
| `create_invoice` | `payment_method`, `confirm=true` | `task_ids` or `task_id`; money action |
| `mark_invoice_paid` | `invoice`, `confirm=true` | only transfer + pending |
| `create_project_invite` | `project`, `role` | `days` 1–30; optional email; one-time URL |
| `create_report` | `items` | recipient required by service; description/link groups |
| `update_report` | `report` | omitting fields keeps them; `items` replaces groups |
| `delete_report` | `report`, `confirm=true` | destructive; removes the report and its groups |
| `create_task_template` | `name`, `default_type`, `default_priority`, `default_status` | checklist/defaults/client/shared optional |
| `apply_task_template` | `template`, `client_id` | optional title/deadline/assignee/`project_id`/`budget` |
| `set_custom_field_values` | `entity_type`, `entity_id`, `fields` | sync typed values |

`create_report` item properties: `description`, `link`, `summary` (alias),
`test_url` (alias), `url` (alias), `screenshot_base64`, `screenshot_mime`.
`create_task_report` is a legacy alias with top-level `summary`/`test_url`.

`update_report` accepts the same item aliases. Omit `title`, `task_id`,
`recipient_id`/`recipient_email` or `items` to keep the current value. Keeping an
item `id` preserves its screenshot; pass a new `screenshot_base64` to replace it.

## `crm_operation`

Payload: `{"operation":"...", "id":"uuid/key", "payload":{...}, "confirm":true?}`.
`id` may also be inside `payload.id`.

| Operation | Target / payload |
|---|---|
| `client.update` | client; `name` + `email` required, phone/contacts optional |
| `client.deactivate` | client; `confirm` |
| `project.update` | project; `name` required + optional fields, including `agents_md` |
| `project.archive` | project; `confirm` |
| `project.member.add` | project; `user_id`, `role` |
| `project.member.update` | project; `member_id`, `role` |
| `project.member.remove` | project; `member_id` |
| `project.invite.revoke` | project; `invite_id` |
| `project.study.start` | project; optional `focus` |
| `project.study.status` | project; `study_id` |
| `project.study.cancel` | project; `study_id` |
| `task.update` | task; `client_id`, `project_id`, `title`, `description`, `type`, `priority`, `assignee_id`, `deadline`, `budget`, `estimated_minutes`, `custom_fields` |
| `task.checklist` | task; `checklist[]` |
| `task.comment` | task; `content`, optional `attachments` |
| `invoice.cancel` | invoice; `confirm` |
| `invoice.refund` | invoice; `confirm` (YooKassa) |
| `task_template.create` | template payload |
| `task_template.update` | template; template payload |
| `task_template.apply` | template; `client_id` + overrides |
| `task_template.toggle` | template |
| `task_template.delete` | template; `confirm` |
| `form_template.toggle` | form UUID or key |
| `custom_field.list` | optional `entity_type` |
| `custom_field.create` | field payload |
| `custom_field.update` | field; field payload |
| `custom_field.delete` | field; `confirm` |
| `custom_field.set` | `entity_type`, `entity_id`, `key`, `value` |
| `custom_field.reorder` | `ids[]` |
| `settings.get` | no payload; project/transfer only |

## Limits and gotchas

- `create_task` accepts `budget` and `estimated_minutes`; task update does too.
- `task.update` does **not** change status; use `update_task_status`.
- `task.checklist` replaces the entire checklist.
- `project.update` requires `name`; `update_client` requires `name` and `email`.
- `create_invoice`: one client, amount > 0, recurrence not allowed for transfer.
- `create_client`: unique email; password min 8, generated if omitted.
- Screenshot base64 max ~10 MB; png/jpeg/webp/gif.
- `create_report` sends mail immediately; test with your own admin recipient.
- Mass update with per-task values and budget distribution are **Kiki provider
  tools**, not MCP.
