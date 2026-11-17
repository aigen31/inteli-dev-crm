# Report template: description + link

A report is a deliverable sent to a client. Each group is one completed piece of
work with a short explanation and, optionally, a test link and screenshot.

## Shape

```json
{
  "title": "Исправил форму заявки",
  "recipient_id": "user-uuid",
  "recipient_email": "client@example.com",
  "task_id": "task-uuid",
  "template": "description_link",
  "items": [
    {
      "description": "Форма на главной снова отправляет заявку; проверьте на https://example.com",
      "link": "https://example.com",
      "screenshot_base64": "iVBORw0KGgo...",
      "screenshot_mime": "image/png"
    },
    {
      "description": "Письмо о заявке приходит на contact@example.com"
    }
  ]
}
```

- Recipient: `recipient_id` or `recipient_email`; must be an active CRM user.
- `title` optional; fallback is «Отчёт от DD.MM.YYYY».
- `template` optional; `description_link` documents the intended format.
- One group = one verifiable result; do not paste a whole task description.
- `description` 3–5000 chars; `link` must be a valid URL (max 500).
- Alias fields `summary`/`test_url`/`url` are accepted by `create_report`;
  `create_task_report` is the legacy alias.

## Screenshots

- Base64 without data prefix or with `data:image/...;base64,`.
- Max 10 MB; png/jpeg/webp/gif.
- A screenshot is evidence, not decoration: no secrets, tokens, other clients.
- If several groups have screenshots, each group carries its own.

## After creation

1. Read `url` from `structuredContent.url`.
2. Call `get_report` and compare `items` with what you sent.
3. Confirm recipient and task; do not resend if the first call succeeded.
4. Report the URL to the human; do not paste the full report body unless asked.

## Editing and deletion

`update_report` edits an existing report. Omit a field to keep its current value;
pass `items` only when the group list should be replaced. Keep an item `id` to
preserve its screenshot, or pass a new `screenshot_base64` to replace it.
`recipient_email` is resolved to an active CRM user, just like `create_report`.

`delete_report` is destructive and requires `confirm=true`. It deletes the report
and its groups; there is no undo. Do not call it on a timeout retry before
checking `get_report`.

## Anti-patterns

- One group «Сделал всё» for five unrelated works.
- A link to localhost/internal admin instead of the client-facing page.
- A screenshot without context or with personal data.
- Creating a second report after a timeout without checking `list_reports`.
