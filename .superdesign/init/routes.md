# Routes — CRM

Routing: Laravel `routes/web.php` (no Vue Router). Inertia page components resolved in `resources/js/app.js` from `./Pages/**/*.vue`; Ziggy exposes named routes to Vue via global `route()`.

## Layout mapping

| Area | URL prefix | Middleware | Layout |
|---|---|---|---|
| Guest | `/login` | `guest` | `GuestLayout` |
| Admin | `/admin/*` | `auth`, `is_active`, `is_admin` | `AdminLayout` |
| Client | `/dashboard`, `/tasks*`, `/invoices*`, `/profile` | `auth`, `is_active` | `ClientLayout` |
| Webhook | `POST /webhooks/yookassa` | `yookassa_webhook`, no CSRF | JSON |

## Admin routes

| Path | Name | Page component |
|---|---|---|
| `/admin/dashboard` | `admin.dashboard` | `Admin/Dashboard.vue` |
| `/admin/clients` | `admin.clients.index` | `Admin/Clients/Index.vue` |
| `/admin/clients/create` | `admin.clients.create` | `Admin/Clients/Create.vue` |
| `/admin/clients/{client}` | `admin.clients.show` | `Admin/Clients/Show.vue` |
| `/admin/clients/{client}/edit` | `admin.clients.edit` | `Admin/Clients/Edit.vue` |
| `/admin/tasks` | `admin.tasks.index` | `Admin/Tasks/Index.vue` |
| `/admin/tasks/create` | `admin.tasks.create` | `Admin/Tasks/Create.vue` |
| `/admin/tasks/{task}` | `admin.tasks.show` | `Admin/Tasks/Show.vue` |
| `/admin/tasks/{task}/edit` | `admin.tasks.edit` | `Admin/Tasks/Edit.vue` |
| `/admin/invoices` | `admin.invoices.index` | `Admin/Invoices/Index.vue` |
| `/admin/invoices/create` | `admin.invoices.create` | `Admin/Invoices/Create.vue` |
| `/admin/invoices/{invoice}` | `admin.invoices.show` | `Admin/Invoices/Show.vue` |
| `/admin/settings` | `admin.settings.edit` | `Admin/Settings/Index.vue` |

## Client routes

| Path | Name | Page component |
|---|---|---|
| `/dashboard` | `client.dashboard` | `Client/Dashboard.vue` |
| `/tasks` | `client.tasks.index` | `Client/Tasks/Index.vue` |
| `/tasks/create` | `client.tasks.create` | `Client/Tasks/Create.vue` |
| `/tasks/{task}` | `client.tasks.show` | `Client/Tasks/Show.vue` |
| `/invoices` | `client.invoices.index` | `Client/Invoices/Index.vue` |
| `/invoices/{invoice}` | `client.invoices.show` | `Client/Invoices/Show.vue` |
| `/profile` | `client.profile.edit` | `Client/Profile/Edit.vue` |

## Auth / misc

| Path | Name | Page |
|---|---|---|
| `/login` (GET/POST) | `login` | `Auth/Login.vue` |
| `POST /logout` | `logout` | redirect |
| `PUT /password` | `password.update` | — |
| `/` | — | redirect to `login` |
| `/up` | — | health JSON |

## Key page renders

- **Admin/Dashboard** — 4 stat cards (total tasks, in progress, awaiting review, monthly revenue), then two list cards: pending tasks and pending invoices.
- **Admin/*/Index** — filter bar + table + pagination, Card wrapper.
- **Admin/Tasks/Show** — task detail, status change form, comments, reports, attachments.
- **Admin/Settings** — YooKassa credentials form and profile.
- **Auth/Login** — centered guest card with email/password/remember.
