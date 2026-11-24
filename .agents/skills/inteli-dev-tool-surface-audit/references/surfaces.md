# Tool surface map

## Contracts

| Surface | Endpoint | Auth | Owner |
|---|---|---|---|
| CRM MCP | `POST /api/mcp` | `MCP_TOKEN` | CRM `CrmMcpService` |
| CRM internal provider | `/api/internal/assistant/{profile,tools,files}` | `KERNEL_PROVIDER_TOKEN`, `X-Kernel-Channel: crm` | CRM `CrmToolbox` |
| Site MCP | `POST /mcp` | public read-only | site `mcp::TOOLS` |
| Site internal provider | `/internal/assistant/{profile,tools,prices}` | `KERNEL_SERVICE_TOKEN`, `X-Kernel-Channel: site` | site `api::kernel` |
| Kernel API | `/v1/chat`, `/v1/conversations`, `/v1/studies`, `/v1/knowledge/pricing` | `KERNEL_TOKEN` | kernel |
| Kernel native tools | model tool loop | shared | `get_price_list` |

## Current counts (audit 2026-11-24)

- CRM MCP: 41 tools; `list_tasks` принимает `date` (YYYY-MM-DD) для выборки задач на день (briefs: `list_briefs`, `get_brief`, `create_brief`, `reopen_brief`, `archive_brief`, `list_brief_templates`; balance: `get_balance`, `adjust_balance`).
- CRM Kiki provider: 54 catalog entries, including read-only `list_briefs` / `get_brief`.
- Site MCP: 8 read-only tools.
- Site kernel provider: all 8 site MCP tools (generated from `mcp::TOOLS`) + alias `site_lookup`.
- Kernel native: `get_price_list`; provider ceiling 64 tools.
- Production CRM MCP: 30 tools; DSH web profile uses the streamable HTTP endpoint
  `https://crm.inteli-dev.ru/api/mcp` with `MCP_TOKEN`; site MCP: 8 read-only.

## Boundaries

- Site MCP is intentionally read-only: admin writes stay behind the admin token and are not exposed to public MCP agents. This is a deliberate security exception, not a missing tool.
- CRM MCP is owner-level and includes writes; money/destructive actions require `confirm: true`.
- Kiki tools are the CRM/site provider tools; they are not the same as MCP tools. A tool added only to MCP is not automatically available to Kiki.
- Secrets/API tokens/payment keys are not exposed through either MCP or provider.
- Assistant-only tools (documents, task plans, forms, site study, crm_help) do not need an MCP twin unless the capability is part of the public domain API.
- Admin moderation is UI-only: report edit/delete and feedback/bug edit, archive/restore, or reject are intentionally not exposed to MCP/Kiki.

## Known differences

- MCP-only: project create/invite, client update, project/invoice/users/forms reads, custom field value tools, generic `crm_operation`, brief reopen/archive (`reopen_brief`, `archive_brief`) and the MCP-side template catalog alias.
- Brief creation is available in both MCP (`create_brief`) and Kiki (`create_brief`, with a confirmation and `task_id` auto-resolving client/project).
- Briefs read (`list_briefs`, `get_brief`) is shared by MCP and Kiki; Kiki scopes it with `Brief::scopeVisibleTo`, so a client sees only their own briefs and project briefs.
- Research delegation (`research`, `research_status`) is Kiki-only: the kernel exposes `/v1/harness/runs` for the CRM provider, MCP has no such tool.
- Brief template writes are Kiki-only (`create_brief_template`, `update_brief_template`, `delete_brief_template`); MCP has only read `list_brief_templates`, and the admin panel deletes but never authors a schema.
- Brief submit is client-side by design: the client fills it in CRM, so MCP/Kiki never submit on their behalf. Brief PDF/JSON/Markdown export stays UI-only (files, not domain reads).
- Kiki-only: document reading, plan proposals, form templates, `crm_help`, payment link, task mass/budget tools.
- These are expected unless the capability is needed by both audiences; record the decision in the audit.
