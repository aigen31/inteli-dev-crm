# CRM — Design System (light paper & violet)

## 1. Product context

Single-tenant CRM + project management for a self-employed specialist. Two roles: **admin** (clients, tasks, templates, invoices, payments, settings, API, Kiki) and **client** (own tasks/invoices, request from template, review, pay). No sign-up. Russian UI, RUB, dd.mm.yyyy.

New jobs: choose a task template instead of writing chaos; see custom fields; ask Kiki; connect via API/MCP.

## 2. Brand direction

Quiet, warm, editorial — not a dark admin panel. Paper canvas, white cards, violet accent, one recognizable mascot (Kiki). Feel: calm workspace, generous whitespace, short navigation, soft depth.

Source of truth: `resources/css/app.css` `@theme`. Kiki canon: `public/images/kiki/` (generated from `inteli-dev.ru` reference).

### Colour tokens

| Token | Value | Use |
|---|---|---|
| `--color-canvas` | `#f6f3ec` | app background |
| `--color-surface` | `#fffdf8` | cards, panels |
| `--color-surface-2` | `#f2ece1` | inputs, chips, quiet rows |
| `--color-paper` | `#eee7da` | secondary paper blocks |
| `--color-line` | `#e4ddcf` | 1px borders |
| `--color-line-strong` | `#cfc4b1` | hover/active border |
| `--color-ink` | `#1c1823` | primary text |
| `--color-ink-soft` | `#4d4656` | body |
| `--color-muted` | `#7d7484` | secondary |
| `--color-faint` | `#a89fae` | metadata |
| `--color-primary-500` | `#6d5ae6` | primary action |
| `--color-primary-600` | `#5b48d6` | hover |
| `--color-primary-soft` | `#ece6fd` | active nav, selected chips |
| `--color-accent` | `#e9956f` | warm accent |
| `--color-sage` | `#8fb89a` | money/positive quiet |
| `--color-sky` | `#8db8d8` | info/transfer |
| success | `#2f8f62` | paid, completed |
| warning | `#b7791f` | pending review, unpaid |
| danger | `#c2453d` / text `#a33a33` | rejected, errors |

Status pills: translucent tint + colored text + 6px dot (see `Badge.vue`). No heavy saturated fills.

### Typography

- Sans: **Manrope** 400/500/600/700/800.
- Display: **Unbounded** 400/500/600 — page titles, KPI numbers, logo.
- Mono: **JetBrains Mono** — eyebrows, IDs, dates, amounts, code.
- Page title 30–36px/500, tracking −0.03em; section 14–19px/700; body 12–14px; caption 10–11px.
- Eyebrow: 10px mono, uppercase, tracking 0.18em.

### Shape, elevation, motion

- Radii: inputs/buttons 13–14px, cards 24px, hero 30px, pills 999px, modals 26px.
- Border: 1px `--color-line`; hover `--color-line-strong`.
- Shadow: `0 18px 45px rgba(28,24,35,0.055)` + inset white highlight. Hover lift −2px.
- Background: paper + soft radial lilac (top right) and peach (bottom left), fixed; never busy.
- Motion: 150–180ms ease; fade/slide for drawers, no bounce except Kiki typing dots.

## 3. Shell and navigation

- Desktop sidebar 236px, translucent surface, 4 nav items (admin) / 3 (client), 40px rows, icon 19px. Active = `primary-soft` + `primary-800` + dot.
- Sidebar bottom: settings/profile + user card + logout. Role label: «Владелец» / «Клиент».
- Top strip: breadcrumb (mono), «Найти или спросить ⌘K», settings icon. No second nav.
- Content max 1280px, padding 24–40px, title block + actions slot.
- Mobile: top bar with burger/logo/Kiki; bottom bar 4 items + Kiki; drawer menu.
- Assistant: right drawer 430px (desktop), full-screen sheet (mobile); floating access from sidebar and bottom bar.

## 4. Components

- **Card** `.surface-card` 24px, border, soft shadow; header 14px bold; padding 20–24px.
- **StatCard**: eyebrow, 25px display number, hint, 36px tone tile.
- **Button**: primary violet with soft glow, secondary white/border, ghost, dark, danger; 40px height, 14px radius, 12px/600.
- **Inputs**: 40px, white/70, 13px radius; focus violet ring; labels 11px/600.
- **Table/list rows**: 1px dividers, 13px semibold title, 10–11px meta; hover `ink/[0.025]`.
- **Badge**: 10px/600 pill with dot.
- **Segmented**: pill track, active dark ink, counts mono.
- **EmptyState**: Kiki `empty` 92–132px + 17–21px display title + one action.
- **RichTextEditor**: toolbar 7px buttons, 18px radius, min-height 190px, placeholder; prose styling.
- **Assistant panel**: header Kiki + online dot, markdown bubbles (user violet/right, Kiki white/left), suggestion chips, confirm card (warning), composer pill.
- **Modal**: 26px, surface, 1px border, dark scrim `ink/25`.

## 5. Page patterns

- **Login**: split card; left brand + headline + form; right Kiki hero on lilac gradient, one status chip; no clutter.
- **Admin dashboard**: hero with greeting + 3 actions + Kiki hero; 4 stats; focus list + money/activity column; two quick links.
- **Templates**: intro card with Kiki + stats; segmented scope; 3-col cards with checklist chips, apply/edit/toggle.
- **Template editor**: 1.4/0.8 grid; AI draft card, base, checklist, defaults; sticky live preview.
- **Tasks**: segmented statuses, search/type/client, list rows with template chip; empty state.
- **Task detail**: title/status/priority, description prose, checklist progress, comments/reports; sidebar info, status form, fields, invoices, history.
- **Clients**: card grid with initials, contact, custom field chips, counts.
- **Client detail**: hero contact + intake status; tasks/invoices; contacts + editable fields.
- **Settings**: left tab rail (Оплата, Поля, API и MCP, Кики); field list grouped by entity; token cards; AI form + test + actions log.
- **Client dashboard**: hero greeting + intake status; 3 stats; own tasks/invoices; template quick start.

## 6. Kiki

- Canon: oversized black pointed hat with brown band/brass buckle, long wavy dark chestnut hair, green eyes, black high-neck dress with bow, black lace-up boots.
- Assets: `kiki-flight.webp` (16:9 flight, transparent), `kiki-flight-portrait.webp` (3:4), `kiki-flight-hero.webp` (dashboard 900×620), `kiki-flight-avatar.webp` (1:1), `kiki-flight-badge.webp` (1:1 for small empty states), scene set `kiki-standing`, `kiki-templates`, `kiki-invoices`, `kiki-assistant`, `kiki-success`, `kiki-success-badge`; legacy `kiki-hero.webp`, `kiki-empty.webp`, `kiki-avatar.webp`, `kiki-thinking.webp`.
- Use: flight portrait on login, flight hero on dashboards, standing scenes in templates/invoices/assistant, badge/avatar in empty states; do not stretch, do not recolor, do not add text inside image.
- Keep face/clothes consistent; new poses are generated by i2i from `kiki_master.png` in ComfyUI Qwen Image 2.1 (see [docs/agents/kiki-assets.md](../docs/agents/kiki-assets.md)).

## 7. Fidelity constraints

- Use only these tokens/fonts/radii; no dark panels, no pastel `*-100` walls, no purple gradient fills (only soft radial washes), no emoji icons.
- Icons: Heroicons outline 20/24px, stroke 1.5.
- Status/money always use `Badge`/`formatMoney`; dates `formatDate`.
- New UI must be responsive at 1440 and 390 and keep the 4-item navigation.
