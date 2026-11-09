# CRM MCP server

The CRM exposes an owner-level MCP server for DeepSeek Harness and other
agents. It uses the same services as the web UI, so validation, permissions and
delivery rules do not drift.

## Endpoint and auth

```
POST /api/mcp
Authorization: Bearer $MCP_TOKEN
Content-Type: application/json
```

`MCP_TOKEN` is configured in the CRM `.env`. The endpoint is stateless and
speaks JSON-RPC 2.0 over HTTP (streamable HTTP transport). The actor is
`MCP_ACTOR_ID` or the oldest active administrator; the token is a machine
credential and must not be given to clients.

## Tool surface

All tools use the MCP `tools/list` / `tools/call` contract and declare
`readOnlyHint` / `destructiveHint` annotations.

### Read

| Tool | Purpose |
|---|---|
| `crm_overview` | Clients, projects, active/overdue tasks, pending invoices, monthly revenue. |
| `list_clients` / `get_client` | Client list or card with tasks, invoices and custom fields. |
| `list_projects` / `get_project` | Project list or card with members, invites, tasks and progress. |
| `list_tasks` / `get_task` | Task list or full card; `get_task` accepts UUID or `KEY-12`. |
| `list_invoices` / `get_invoice` | Invoice list or card with tasks and client links. |
| `list_reports` / `get_report` | Report list or full report with description/link groups. |
| `list_users` | Active users and roles. |
| `list_forms` | Client form templates with their field schema. |
| `list_task_templates` | Task templates and defaults. |

### Write

| Tool | Purpose |
|---|---|
| `create_client` | Create a client; requires `consent_confirmed: true`, returns a one-time temporary password. |
| `update_client` | Update client contacts by UUID or email. |
| `create_task_template` / `apply_task_template` | Create a reusable template or make a task from it. |
| `list_custom_fields` / `get_custom_field_values` / `set_custom_field_values` | Custom fields for clients, tasks and invoices. |
| `create_project` | Create a project for a client. |
| `create_task` | Create a task, optionally from `task_template_id`. |
| `update_task_status` | Move a task through an allowed transition with a comment. |
| `create_invoice` | Create an invoice for tasks; requires `confirm: true`. |
| `mark_invoice_paid` | Confirm a direct transfer; requires `confirm: true`. |
| `create_project_invite` | One-time invite link with role and expiry. |
| `create_report` | Create a report in the **description + link** template. |
| `create_task_report` | Backward-compatible alias; accepts `summary`/`test_url`. |
| `crm_operation` | Remaining writes, listed below. |

`crm_operation` covers:
`client.update`, `client.deactivate`; `project.update`, `project.archive`,
`project.member.add/update/remove`; `project.study.start/status/cancel`, `project.invite.revoke`; `task.update`, `task.checklist`,
`task.comment`; `invoice.cancel`, `invoice.refund`; `task_template.create/update/apply/toggle/delete`;
`form_template.toggle`; `custom_field.list/create/update/delete/set/reorder`;
`settings.get`.

```json
{
  "operation": "project.member.add",
  "id": "project-uuid",
  "payload": { "user_id": "user-uuid", "role": "viewer" }
}
```

## Reports: description and link template

Each report is one or more groups:

```json
{
  "title": "Починил форму заявки",
  "recipient_email": "client@example.com",
  "task_id": "task-uuid (optional)",
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

`description` and `link` are mapped to the domain fields `summary` and
`test_url`; `summary`/`test_url` are still accepted. `items` is repeatable.
`create_report` returns the report URL, group list and screenshot flags.
The recipient gets an in-app report and a mail notification; the report is
attributed to the MCP actor.

## Safety rules

- Financial and destructive tools require `confirm: true` in the same call:
  `create_invoice`, `mark_invoice_paid`, `invoice.cancel`, `invoice.refund`,
  `client.deactivate`, `project.archive`, `custom_field.delete`.
- `create_client` requires `consent_confirmed: true`; a privacy acknowledgement
  is written to `consent_logs` with `basis=contract` and `source=mcp`.
- MCP never reads or changes API tokens, payment-gateway secrets or other
  credentials. `settings.get` returns only project and transfer settings.
- Validation errors come back as `result.isError: true` with a readable
  message; a wrong bearer token is HTTP 401.

## DeepSeek Harness

The harness sidecar receives the server through two environment variables:

```dotenv
CRM_MCP_URL=http://crm-nginx/api/mcp
CRM_MCP_TOKEN=change-me-mcp
```

It writes a `dsh-mcp-client` patch (`/data/mcp.yml`) at startup and passes it
to `dsh` after the lean study patch. The agent sees tools named
`mcp__crm__crm_overview`, `mcp__crm__create_report`, etc. The token must match
the CRM `MCP_TOKEN`.

The patch uses an insert list, because `--patch` overlays can only target
existing entries:

```yaml
- insert:
    - id: mcp-crm
      name: '@deepseek-ai/dsh-mcp-client'
      config:
        transport: streamable-http
        serverName: crm
        url: "http://crm-nginx/api/mcp"
        headers:
          Authorization: "Bearer <token>"
```
