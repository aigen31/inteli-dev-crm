# Extractable components — CRM

## AdminSidebar
- Source: `resources/js/Components/Layout/AdminLayout.vue`
- Category: layout
- Description: Dark indigo fixed sidebar with brand title, 5 nav items, logout, mobile drawer.
- Extractable props: activeItem (string: dashboard|clients|tasks|invoices|settings), userName (string)
- Hardcoded: logo text "CRM — Управление", nav labels/icons (heroicons outline), all CSS

## ClientSidebar
- Source: `resources/js/Components/Layout/ClientLayout.vue`
- Category: layout
- Description: Same sidebar shell for client area with 4 nav items.
- Extractable props: activeItem (string: dashboard|tasks|invoices|profile), userName (string)
- Hardcoded: brand text "CRM — Личный кабинет", nav labels/icons, CSS

## AppHeader
- Source: `resources/js/Components/Layout/AdminLayout.vue`
- Category: layout
- Description: 64px sticky white top bar with page title, mobile menu button, user name.
- Extractable props: title (string), userName (string)
- Hardcoded: burger icon, typography, spacing

## GuestCard
- Source: `resources/js/Components/Layout/GuestLayout.vue`
- Category: layout
- Description: Centered auth card with brand wordmark above.
- Extractable props: title (string)
- Hardcoded: brand text, max-width, card styling

## TaskStatusBadge
- Source: `resources/js/Components/UI/Badge.vue`
- Category: basic
- Description: Pill badge with dot, color keyed by task/invoice/priority status.
- Extractable props: status (string), type (task|invoice|priority), label (string)
- Hardcoded: color maps, dot, pill shape

## StatCard
- Source: `resources/js/Pages/Admin/Dashboard.vue`
- Category: basic
- Description: KPI card with label, big value and tinted round icon.
- Extractable props: label (string), value (string|number), tone (primary|indigo|purple|green), icon
- Hardcoded: card padding, icon circle, typography

## PrimaryButton
- Source: `resources/js/Components/UI/Button.vue`
- Category: basic
- Description: Button with primary/secondary/danger/ghost/link variants and 3 sizes.
- Extractable props: variant (string), size (string), disabled (boolean)
- Hardcoded: radius, focus ring, colors
