# CRM data model for agents

## Identifiers

- **Client** — `users.id` UUID; find by UUID or email. Role `client`.
- **Project** — UUID or human `key` (e.g. `KOR`). A project belongs to one client
  and has members with independent roles.
- **Task** — UUID or `KEY-N` (e.g. `KOR-12`). A task belongs to a client,
  optionally to a project.
- **Invoice** — UUID; linked to one or more tasks of one client.
- **Report** — UUID; groups (`items`) with description/link/screenshot.
- **Task template** — UUID; defaults for new tasks.
- **Form template** — UUID or key; public intake form schema.
- **Custom field** — UUID; key/label/type, scoped to client, task or invoice.
- **User** — admin or client; MCP writes are admin-attributed.

## Relations

```
User (client) 1─* Task
Project 1─* Task
Project 1─* ProjectMember *─1 User
Project 1─* ProjectInvite
Task 1─* TaskComment
Task 1─* TaskReport 1─* TaskReportItem
Task 1─* Invoice *─* Task
CustomField 1─* CustomFieldValue *─1 entity
```

A client sees own tasks/invoices and projects where they are the owner or a
member. `viewer` cannot create tasks; `member`/`manager` can; project settings
stay admin-only.

## Enums

- Project status: `active`, `on_hold`, `completed`, `draft`, `archived`.
- Project member role: `manager`, `member`, `viewer`.
- Task status: `pending_review`, `queued`, `in_progress`, `awaiting_review`,
  `completed`, `rejected_by_client`, `rejected_by_admin`.
- Task type: `one_time`, `maintenance`.
- Priority: `low`, `medium`, `high`.
- Invoice status: `pending`, `paid`, `cancelled`, `refunded`.
- Payment method: `yookassa`, `transfer`.
- Recurring interval: `monthly`, `quarterly`, `yearly`.
- Custom field type: `text`, `textarea`, `number`, `currency`, `date`, `select`,
  `multiselect`, `checkbox`, `url`, `email`, `phone`.

## Task fields

- `budget` — numeric, rubles, ≥ 0; may be absent.
- `estimated_minutes` — 5…10 000.
- `checklist` — array of `{text, done}`.
- `custom_fields` — object `key => value` on create/update.
- Status changes only through `update_task_status` (transition rules apply).

## CRM pages

- Admin: `/admin/dashboard`, `/admin/clients`, `/admin/projects`,
  `/admin/tasks`, `/admin/invoices`, `/admin/reports`, `/admin/forms`,
  `/admin/task-templates`, `/admin/settings`, `/help`, `/assistant`.
- Client: `/dashboard`, `/projects`, `/tasks`, `/invoices`, `/reports`,
  `/profile`, `/help`, `/assistant`.
- Shared report page: `/reports/{report}`; invite registration: `/invite/{token}`.
- Legal: `/legal/privacy`, `/legal/consent`, `/legal/requisites`.

## Report body

`TaskReport` keeps the first group in legacy fields (`content`, `test_url`,
`screenshot_id`) and every group in `task_report_items` (`content`, `test_url`,
`screenshot_id`, `position`). Always treat `items` as the source of truth.

## Consent and money

- `consent_logs`: user, form, document, version, accepted_at, ip, user_agent, meta.
- MCP client creation: `form=mcp-client-create`, `document=privacy`,
  `basis=contract`, `source=mcp`.
- Money actions are domain actions, not direct model updates. Do not write invoice
  statuses via SQL.
