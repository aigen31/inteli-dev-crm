# CRM — Design System (inteli-dev brand, dark violet)

## 1. Product context

Single-tenant CRM for a self-employed specialist. Two roles: **admin** (manages clients, tasks, invoices, payments) and **client** (sees own tasks/invoices, accepts work, pays via YooKassa). No sign-up, no chat; admin creates client accounts. Russian UI, money in RUB, dates dd.mm.yyyy.

Core jobs:
- Admin: see today's workload and revenue; triage incoming requests; move tasks through statuses; issue and track invoices.
- Client: submit a request, follow status, review the result, pay an invoice.

Key screens: login, admin dashboard (KPIs + queues), clients list/detail, tasks list/detail (status, comments, reports, files), invoices list/detail, settings; client dashboard, tasks, invoices, profile.

## 2. Brand direction — adopt inteli-dev.ru

The CRM must look like the owner's site `inteli-dev.ru`: near-black canvas with violet glow, deep indigo surfaces, lavender accent, Inter for text and JetBrains Mono for metadata/labels. Calm, technical, premium; generous dark space; thin 1px borders; pill nav; no bright white panels.

Source of truth for the reference: `assets/style.css` of the site (`--color-bg`, `--color-surface`, `--color-primary`, radii, typography).

### Colour tokens (exact values from the site)

| Token | Value | Use |
|---|---|---|
| `--color-canvas` | `#0a0a14` | app background base |
| `--color-bg` | `#2b2c7a` | violet glow layer (radial gradients only) |
| `--color-surface` | `#16133a` | cards, sidebar, header |
| `--color-surface-2` | `#1b1845` | raised rows, hover, inputs |
| `--color-border` | `rgba(255,255,255,0.12)` | default 1px border |
| `--color-border-strong` | `rgba(255,255,255,0.22)` | hover / active border |
| `--color-text` | `#f4f4ff` | primary text |
| `--color-text-secondary` | `#c6c5f2` | secondary text |
| `--color-muted` | `rgba(255,255,255,0.66)` | captions, metadata |
| `--color-primary` | `#8b7ff5` | accent, primary button, active nav |
| `--color-primary-hover` | `#9d92fa` | hover |
| `--color-primary-soft` | `rgba(139,127,245,0.18)` | active pill, badge bg |
| success | `#4cd964` | paid, completed |
| warning | `#ffbd44` | awaiting review, unpaid |
| danger | `#ff5f56` / text `#ff7b7b` | rejected, refunded, errors |

Status pills on dark: translucent tint background (`rgba(color,0.14)`) + colored text + 6px dot. Never pastel `*-100` backgrounds.

### Typography

- Sans: **Inter** (400/500/600/700) for everything.
- Mono: **JetBrains Mono** for eyebrows, IDs, dates, amounts, status labels.
- Display: page title 28–32px/700; section title 18–20px/600; body 14px; caption 12px; eyebrow 11px uppercase letter-spacing 0.12em mono.
- Headings and KPI values are white `#f4f4ff`; secondary copy `#c6c5f2`; metadata at 66% white.

### Shape, elevation, motion

- Radii: buttons/inputs 10px, cards 14px, modals 20px, pills 999px.
- Borders: 1px `rgba(255,255,255,0.12)`; hover `0.22`.
- Shadows: soft black (`0 16px 40px rgba(0,0,0,.4)`), optional violet glow `0 0 40px rgba(139,127,245,.25)` for focal cards/active nav.
- Background: `#0a0a14` with 1–2 large radial gradients of `#2b2c7a`/`#8b7ff5` at 10–15% opacity, fixed, never busy.
- Motion: 180ms ease; hover lift −2px on cards; spotlight radial under cursor is optional and subtle.

## 3. Layout for the CRM shell

- **Sidebar** 248–264px, `--color-surface`, right border; brand wordmark "inteli dev" + small mono caption "CRM". Nav items: 40px, radius 10px; inactive text secondary; active = `primary-soft` background, white text, 2px lavender left bar or glow dot. Section eyebrow "УПРАВЛЕНИЕ" in mono above the group. Bottom: user block + logout.
- **Top bar** 64px, translucent surface with blur (`rgba(22,19,58,.72)`, backdrop blur 12px), bottom 1px border. Left: page title (18px/600); optional mono breadcrumb/eyebrow above. Right: user name + role chip.
- **Content** max-width ~1240px, padding 24–32px; vertical rhythm 24px.
- **Mobile**: sidebar becomes drawer over a dark scrim; header keeps burger.

## 4. Components

- **Card**: surface bg, 1px border, 14px radius, 20–24px padding; header row separated by 1px border; title 16px/600 white. Optional mono eyebrow above title.
- **Stat card (KPI)**: mono uppercase label 11px muted; value 28px/700 white; icon inside 40px rounded square with `primary-soft` bg and lavender icon. Add a tiny mono delta/context line.
- **Button**: primary = lavender bg `#8b7ff5`, white text, 10px radius, 40px height; hover `#9d92fa`. Secondary = transparent with `border-strong` and white text; ghost = text secondary, hover white; danger = `#ff5f56` bg or outline. Focus ring: 2px `#8b7ff5` offset 2px.
- **Inputs / select / textarea**: dark `--color-surface-2` bg, 1px border, 10px radius, 40px height, white text, placeholder 40% white; focus border lavender + 1px ring. Labels 12px/500 secondary; errors `#ff7b7b` 12px.
- **Table**: no heavy card chrome; header row mono 11px uppercase muted, 1px border bottom; rows 56px, hover `rgba(255,255,255,0.04)`; cell text 14px; numeric/amount cells mono, right aligned.
- **Badges**: pill, 6px dot, translucent tint, 12px/500.
- **Tabs / filters**: pill or underline lavender active; inactive secondary text.
- **Modal**: surface, 20px radius, 1px border, backdrop `rgba(6,5,18,.7)` blur.
- **Empty state**: centered mono caption muted, no illustrations.
- **Alerts/flash**: translucent tint (success/danger) with 1px tinted border, 10px radius.

## 5. Page patterns

- **Login**: full-bleed dark canvas with violet glow; centered 440px card, brand wordmark above; mono eyebrow "ВХОД В СИСТЕМУ"; inputs and full-width primary button; footer mono "inteli dev · CRM".
- **Dashboard**: eyebrow "ОБЗОР" + title "Дашборд"; 4 KPI cards in a row; below a 2-column grid of queue cards (pending tasks / unpaid invoices) with mono meta and status pills; rows hover surface-2.
- **Lists**: toolbar with title + primary action; filters row (selects, search); table in surface card; pagination in mono at the bottom.
- **Detail**: two columns — main (description, comments, reports) and side (status, client, dates, amount, actions) as stacked cards.
- **Settings**: single column of cards with 2-column form grid.

## 6. Fidelity constraints

Use ONLY these fonts, colours, radii, spacing and component styles. No white panels, no pastel Tailwind `*-100` surfaces, no purple gradients as fills (only radial glow on background), no serif or decorative fonts, no emoji icons. Icons: Heroicons outline (already in the codebase), 20/24px, stroke 1.5, lavender or secondary text.
