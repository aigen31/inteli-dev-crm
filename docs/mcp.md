# CRM MCP server

The CRM exposes a small MCP server for DeepSeek Harness and other agents. It
uses the same report service as the web UI, so the format and delivery rules
cannot drift.

## Endpoint and auth

```
POST /api/mcp
Authorization: Bearer $MCP_TOKEN
Content-Type: application/json
```

`MCP_TOKEN` is configured in the CRM `.env`. The endpoint is stateless and
speaks JSON-RPC 2.0 over HTTP (streamable HTTP transport).

## Tools

| Tool | Purpose |
|---|---|
| `create_task_report` | Create a report and deliver it to a user. |
| `list_users` | Active users with ids and emails, to choose a recipient. |
| `list_tasks` | Recent tasks with references (optional `query`, `project_id`, `limit`). |
| `list_reports` | Recent reports. |

`create_task_report` arguments:

```json
{
  "title": "Исправил форму заявки",
  "items": [
    {
      "summary": "Что сделано и как проверить",
      "test_url": "https://example.com/page",
      "screenshot_base64": "iVBORw0KGgo...",
      "screenshot_mime": "image/png"
    }
  ],
  "recipient_id": "uuid или recipient_email",
  "task_id": "uuid (optional)"
}
```

`items` is repeatable: the report body is one or more groups of
**explanation → optional test URL → optional screenshot**. There is no fixed
limit in validation; the HTTP body limit is the practical one. A top-level
`summary` is still accepted as a single-group fallback for old clients.

The report is attributed to `MCP_ACTOR_ID` (or the oldest administrator) and
the recipient gets an in-app report plus a mail notification.

## DeepSeek Harness

The harness sidecar receives the server through two environment variables:

```dotenv
CRM_MCP_URL=http://crm-nginx/api/mcp
CRM_MCP_TOKEN=change-me-mcp
```

It writes a `dsh-mcp-client` patch (`/data/mcp.yml`) at startup and passes it
to `dsh` after the lean study patch. The agent sees tools named
`mcp__crm__create_task_report` etc. The token must match the CRM `MCP_TOKEN`.

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
