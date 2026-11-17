# Playbooks

## 1. Daily triage

1. `crm_overview` — active/overdue tasks, pending invoices.
2. `list_tasks` with `status=awaiting_review` and `status=in_progress`.
3. `list_invoices` with `status=pending`; flag overdue.
4. Summarize 3–7 items with links; do not dump all records.

## 2. New client → project → invite

1. Confirm the owner has the client's consent.
2. `create_client` with `consent_confirmed: true`; capture `id` and one-time
   `temporary_password`.
3. `create_project` with `client_id`, name, key, site_url.
4. `create_project_invite` with role and days; copy the URL once.
5. Verify `get_client` and `get_project`; report IDs and invite expiry.

Do not send the invite to a different email than the client's without permission.

## 3. Task with budget/template → report

1. `get_client`/`list_clients` to resolve `client_id`.
2. Either `create_task` directly, or `apply_task_template` with `project_id`,
   `budget`, `deadline`, `assignee_id`.
3. Fill `budget`, `estimated_minutes`, `custom_fields` when the owner gave them.
4. When work is done, `create_report`:
   - `recipient_id`/`recipient_email` = active client;
   - `task_id` = task;
   - items: description + link per result.
5. `get_report`; verify `items` and URL.
6. Report the URL and one-line summary.

If a task already exists, do not duplicate it; read first.

## 4. Invoice and payment

1. Read `get_task`/`list_tasks`; ensure tasks belong to one client and are not rejected.
2. `create_invoice` with `task_ids`, `amount` (or task budgets), `payment_method`,
   `confirm: true`.
3. Read `get_invoice`; give the client URL.
4. For transfer, after money arrives: `mark_invoice_paid` with `confirm: true`.
5. Read `get_invoice`; only then say «оплачен».
6. Cancel only pending invoices; refund only paid YooKassa invoices.

## 5. Access audit

1. `get_project`.
2. Check owner, members, roles, active invites.
3. Add/update/remove member via `crm_operation`; revoke stale invite if asked.
4. Re-read `get_project`; report changes.

Do not change the project owner or archive without explicit confirmation.

## 6. Templates, forms and custom fields

1. `list_forms`, `list_task_templates`, `list_custom_fields`.
2. Create/update via `create_task_template`/`crm_operation`; templates can carry
   `budget` only when applied, not as a template default.
3. Apply with `apply_task_template`; read the created task.
4. Custom values: `get_custom_field_values` → `set_custom_field_values`.
5. Do not delete a field/template without `confirm: true`.

## 7. Project study

1. `get_project`; confirm the site URL/instructions.
2. `crm_operation project.study.start` with optional `focus`.
3. Poll `crm_operation project.study.status` with `study_id`.
4. Cancel with `project.study.cancel` only if asked.
5. Do not claim a study completed without a terminal status.

## 8. Mass task edits (Kiki, not MCP)

If the owner asks to change many tasks with **different** descriptions/links or
to distribute a project budget:

1. Use the CRM Kiki assistant, not MCP.
2. Kiki calls `update_tasks` with `updates: [{task_id, values}]` and
   `append_description`, or `distribute_project_budget`.
3. MCP can still do per-task `crm_operation task.update` one by one; this is
   slower and has no bulk budget distribution.

## 9. Recovery

- If a call times out, run `get_*`/`list_*` for the target before retrying.
- If a report was created but URL is missing, `list_reports` by recipient/task.
- If an invoice status is unclear, `get_invoice`; never infer from a notification.
- If a tool is unavailable, report the exact tool and error; do not switch to SQL.
