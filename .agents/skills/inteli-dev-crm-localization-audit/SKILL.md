---
name: inteli-dev-crm-localization-audit
description: Use when checking or changing CRM localizations (ru source, en, zh_CN) — audit every messages.json key, find missing/untranslated/placeholder-broken strings, scan Vue/PHP UI for hardcoded Russian text, verify locale routes and runtime locale sharing. Invoke after adding UI text, changing a form/page, or before a release with localization changes.
license: MIT
metadata:
  version: "1.0.0"
  domain: crm
  triggers: localization, i18n, перевод, локализация, en, zh_CN, messages.json, непереведённые строки
  role: auditor
  scope: crm
---

# Перепроверка локализаций CRM

CRM переводит **русский исходный текст** на `en` и `zh_CN` через
`resources/js/i18n/messages.json` и DOM-переводчик `translateDom`. Ключ — сама
русская строка; автоперевод применяется к текстовым узлам и атрибутам
`placeholder`, `title`, `aria-label`, `alt`.

## Когда использовать

- Добавили/изменили UI-текст, форму, страницу, письмо или подсказку.
- Перед релизом с изменениями в `resources/js/i18n/`, `resources/js/Pages/`,
  `resources/js/Components/`.
- При жалобе «в EN/ZH осталась русская строка» или «перевод пропал».
- При изменении списка локалей или `LocaleController`.

## Workflow

1. Запустите аудит:
   ```bash
   python3 .agents/skills/inteli-dev-crm-localization-audit/scripts/audit_localizations.py \
     --crm /mnt/disk1/work/inteli-dev-infrastructure/inteli-dev-crm
   ```
2. Сначала разберите **critical**:
   - нет колонки `en`/`zh`;
   - пустой перевод;
   - не совпадают `:placeholders`/`{placeholders}`/`%s`;
   - сломаны HTML-теги.
3. Затем **warnings**:
   - значение совпадает с русским ключом (может быть намеренно: имена, аббревиатуры);
   - в шаблоне/атрибуте есть русский текст, которого нет в каталоге;
   - динамическая строка не проверяется автоматически.
4. Для каждой находки:
   - если это пользовательский текст — добавьте ключ в `messages.json` с `en` и `zh`;
   - если это техническая строка/эмодзи/URL/имя — добавьте в allowlist
     `references/allowlist.txt` с комментарием;
   - не переводите пользовательские данные (имена клиентов, суммы, URL).
5. Проверьте вручную:
   - `/locale/en`, `/locale/zh_CN`, `/locale/ru`;
   - одна страница списка, одна форма, одно пустое состояние;
   - переключение туда-обратно без потери русского текста.
6. Запишите результат в `references/audit-YYYY-MM-DD.md`.

## Правила качества

- Ключ — ровно видимая русская строка, без обрезанных слов и лишних пробелов.
- Не добавляйте перевод для динамических данных: имя клиента, название задачи,
  сумма, дата. Их локализует `Intl`, а не словарь.
- Сохраняйте плейсхолдеры **точно**: `:name`, `:count`, `{name}`, `%s`.
- HTML-теги в переводе должны совпадать по составу; не ломайте ссылки/`strong`.
- Один и тот же смысл — одна строка. Дубли с разным регистром/пунктуацией
  приводят к расхождению перевода.
- Аббревиатуры (`ИНН`, `БИК`, `Windows XP`) могут совпадать с оригиналом —
  это допустимо, но фиксируйте в allowlist.
- `ru` — исходный язык, отдельной колонки нет. Не «переводите» русский на
  русский.

## Runtime-контракт

- Список локалей должен совпадать в:
  - `resources/js/i18n/index.js` (`locales`);
  - `app/Http/Middleware/HandleInertiaRequests.php` (`locales`);
  - `app/Http/Controllers/LocaleController.php` (`SUPPORTED`).
- `/locale/{locale}` сохраняет локаль в сессию и в `users.locale`.
- `translateDom` пропускает `[data-i18n-skip]`, `.prose-kiki`, `script`,
  `style`, `noscript`, `textarea`, `[contenteditable]` — это осознанные
  исключения, не баг.
- Даты/деньги/числа идут через `Intl` и `localeTag()`.

## Reference guide

| Файл | Когда |
|---|---|
| `references/catalog.md` | Структура каталога, формат ключей и плейсхолдеров |
| `references/audit-2026-11-13.md` | Последний прогон и остаточные находки |
| `references/allowlist.txt` | Технические/брендовые строки, которые не переводятся |
| `scripts/audit_localizations.py` | Машинная проверка каталога и UI |
