# Theme — CRM

## Part 1 — Token summary (current implementation)

CSS approach: **Tailwind CSS 4, CSS-first**. There is no `tailwind.config.js`; theme tokens live in `resources/css/app.css` inside `@theme`.

Palette (Tailwind theme colors, `--color-primary-*`):
- primary-50 `#EEF2FF`, 100 `#E0E7FF`, 200 `#C7D2FE`, 300 `#A5B4FC`
- primary-500 `#6366F1` (main accent), 600 `#4F46E5`, 700 `#4338CA`
- primary-900 `#312E81` (sidebar), 950 `#1E1B4B`
- Neutrals: Tailwind `gray-*` (page bg `gray-50`, cards `white`, text `gray-500/700/800/900`, borders `gray-200/300`)
- Semantic status colors (light pastel badges): amber/blue/indigo/purple/red/green 100/400/800

Typography:
- sans: `Inter` (bunny.net in `app.blade.php`), fallback system-ui
- mono: `JetBrains Mono`
- scale: page h1 `text-lg` (layout header), card title `text-lg`, body `text-sm`, caption `text-xs`

Spacing/shape:
- 4px base, page padding `px-4 sm:px-6 lg:px-8 py-6`
- radii: `rounded-md` inputs/buttons, `rounded-lg` cards, `rounded-xl` guest card/modal
- shadows: `shadow-sm` on cards, `shadow-xl` on modals
- layout: fixed 256px sidebar (`w-64`, `bg-primary-900`), 64px sticky white header, content offset `lg:pl-64`

## Part 2 — Raw source

### `resources/css/app.css`

```css
@import 'tailwindcss';
@plugin '@tailwindcss/forms';
@plugin '@tailwindcss/typography';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';
@source '../**/*.vue';

@theme {
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
    --font-mono: 'JetBrains Mono', ui-monospace, monospace;

    --color-primary-50: #EEF2FF;
    --color-primary-100: #E0E7FF;
    --color-primary-200: #C7D2FE;
    --color-primary-300: #A5B4FC;
    --color-primary-400: #818CF8;
    --color-primary-500: #6366F1;
    --color-primary-600: #4F46E5;
    --color-primary-700: #4338CA;
    --color-primary-800: #3730A3;
    --color-primary-900: #312E81;
    --color-primary-950: #1E1B4B;
}
```

### Root view `resources/views/app.blade.php`

Loads Inter from fonts.bunny.net, mounts Inertia at `#app`.
