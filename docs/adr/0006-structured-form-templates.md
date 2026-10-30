# ADR 0006: Structured form templates for task intake

## Context

Task templates as narrative HTML + checklist solve repeatability for the admin, but clients still face an empty textarea. The intake needs typed, required fields and repeatable groups (for example bug-report reproduction steps, or “description + image” pairs).

## Decision

- Add `form_templates` with a JSON schema of top-level fields and repeatable groups.
- Store submissions on `tasks.form_data` as `{fields, groups}` and link via `form_template_id`.
- Validate dynamically from the schema in `FormTemplateService`; images are uploaded through the existing file endpoint and stored as attachment UUIDs.
- Keep classic `task_templates` as a separate **Scenarios** section.
- Client primary intake is `/tasks/create` (forms); classic scenarios remain at `/tasks/scenarios`.
- Admin library splits into `/admin/forms` and `/admin/task-templates`.

## Consequences

- Structured data is queryable/renderable without parsing prose.
- Schema validation and UI share one source of truth.
- Repeat groups are flexible; new forms are seeded/configurable without new database columns.
- Classic scenarios remain for text-heavy workflows.
