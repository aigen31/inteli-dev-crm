# Safety rules for CRM MCP

## Authority

- The MCP token is owner-level. Never expose it in chat, code samples, reports,
  screenshots or logs. A leaked token must be rotated by the owner.
- Every write is attributed to `MCP_ACTOR_ID` (or oldest admin). Do not impersonate
  a client or create a client account to bypass policy.
- Do not use direct SQL, tinker, HTTP calls to admin routes or filesystem edits to
  achieve something MCP refuses. The refusal is the policy.
- MCP and Kiki are different contracts. Do not call a Kiki-only tool
  (`update_tasks`, `distribute_project_budget`, `crm_help`) through MCP.

## Read before write

- Resolve IDs with `list_*`/`get_*`; never guess a UUID.
- For task references use `KEY-12`; for projects use key only when unique.
- Before changing an invoice, read its status and payment method.
- Before adding a member, read the project members and the user's role.
- Before changing budget/estimate, read the task and confirm the amount.

## Money and destructive actions

These require `confirm: true` in the same tool call:
`create_invoice`, `mark_invoice_paid`, `invoice.cancel`, `invoice.refund`,
`client.deactivate`, `project.archive`, `custom_field.delete`,
`task_template.delete`.

- Confirm only when the human asked for this exact action. «Сделай что нужно» is
  not confirmation for a refund or a paid mark.
- A timeout after a money call is ambiguous: read the invoice/list first. Never
  blindly retry; you can double-charge or double-mark.
- `mark_invoice_paid` is only for `transfer` and `pending`.
- Refund is only for paid YooKassa invoices; it calls the external provider.
- Creating an invoice does not charge; it creates a payable document and notifies.
- Budget is data, not a payment: setting `budget` does not charge the client.

## Consent and personal data

- `create_client` requires `consent_confirmed: true`. Do not set it unless the
  owner confirmed the client was informed and consented.
- The temporary password is returned once. Do not store it in the conversation
  summary or in a report.
- Do not attach screenshots containing other clients, tokens, passwords or
  personal data to a report.
- Keep answers minimal: return only the fields needed for the task.
- Report recipients must be active CRM users; do not send a report to an external
  email via `recipient_email` unless that email belongs to a CRM user.

## Access

- Invite links are one-time and expire. Copy the URL once; do not log it.
- `viewer` must not be used when the person needs to create tasks.
- Removing a member or revoking an invite is an access change: verify the person
  and project first.
- Project settings/archive are admin-only by design; do not work around.

## Secrets and settings

- `settings.get` returns project/transfer settings only. API tokens, YooKassa
  keys, mail credentials and arbitrary settings are not exposed through MCP.
- Never ask the user to paste a secret into the chat. If a secret is needed, the
  owner changes it in the CRM UI.

## Error recovery

| Symptom | Action |
|---|---|
| `result.isError: true` | Read the message, fix args, retry |
| 401 | Check bearer token; do not print it |
| not found | Re-list; do not guess |
| validation | Fix required field/enum; do not bypass |
| timeout after write | Read the entity/list; only retry if absent |
| partial report | `get_report`; do not resend blindly |
| Kiki-only tool unavailable | Use MCP per-task tools or hand off to Kiki; do not fake it |

## Reporting

- After a write, read back and state: entity, ID, status, URL.
- For reports: URL + item count; do not paste full content unless asked.
- For money: amount, status, invoice ID; never claim «оплачено» without reading it.
- Flag anything you could not verify as unverified; do not invent success.
