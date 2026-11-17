# CRM localization catalog

## Files

- `resources/js/i18n/messages.json` — the only translation catalog.
- `resources/js/i18n/index.js` — supported locales, DOM translator, `t()`.
- `app/Http/Middleware/HandleInertiaRequests.php` — shares `locale`/`locales`.
- `app/Http/Controllers/LocaleController.php` — `/locale/{locale}`.
- `resources/js/Utils/formatters.js` — `Intl` via `localeTag()`.

## Shape

```json
{
  "Да": { "en": "Yes", "zh": "是" },
  "Проект :name": { "en": "Project :name", "zh": "项目 :name" }
}
```

- Key is the exact Russian source string after DOM trimming.
- Columns: `en`, `zh` (the UI code `zh_CN` maps to `zh`).
- No `ru` column: Russian is the key.
- Placeholders are part of the key and must survive translation.

## Runtime behavior

`translateDom` translates text nodes and the attributes `placeholder`, `title`,
`aria-label`, `alt`. It skips `[data-i18n-skip]`, `.prose-kiki`, `script`,
`style`, `noscript`, `textarea`, `[contenteditable]`.

`t()` tries exact key, lowercase first letter, and title-case first letter — so
those three variants are considered the same string. Do not rely on this for new
strings; use the exact visible text.

## Not localized by the catalog

- Client/task names, amounts, dates, URLs, e-mail addresses.
- AI prompts and model answers inside `.prose-kiki`.
- Code/technical values (`ru-RU`, emit names, CSS classes).
- Brand names and abbreviations: `ИНН`, `БИК`, `Windows XP` may stay identical.
