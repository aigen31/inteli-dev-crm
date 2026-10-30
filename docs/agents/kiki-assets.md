# Иллюстрации Кики

## Канон

Кики — аниме-ведьма с сайта `inteli-dev.ru`: огромная чёрная остроконечная шляпа с широкими полями, коричневая лента и латунная пряжка, длинные волнистые тёмно-каштановые волосы, зелёные глаза, чёрное платье с высоким воротом и бантом, чёрные шнурованные ботинки. Лицо и одежда не меняются между кадрами.

Исходный референс — `kiki_master.png` (полный рост, прозрачный фон) из проекта ComfyUI. Он же использован как `<image2>` в i2i.

## Как сгенерировано

- ComfyUI, Qwen Image 2.1 Image Edit, i2i.
- Workflow: `/home/aigen31/Work/ComfyUI/user/default/workflows/image_qwen_image_2_1_image_edit.json`; рабочие копии — `storage/app/kiki/*-workflow.json`.
- `<image1>` — поза/композиция-референс (полёт, levitation, nouveau и т. п.), `<image2>` — `kiki_master.png` для идентичности.
- Параметры: steps 30, cfg 1, euler/simple, seed индивидуальный, prompt enhancer off.
- Результат Qwen сохраняет прозрачный фон (alpha); для веба дополнительно чистится/кадрируется Pillow (GrabCut для сложных фонов, кроп для badge/avatar).

Промпты сохраняются в `storage/app/kiki/*-workflow.json` (`459.prompt`). Они явно перечисляют шляпу, ленту, пряжку, волосы, глаза, платье и ботинки, просят не менять лицо/одежду и оставить левую часть холста пустой.

Для сцен-композиций (`templates`, `invoices`, `assistant`, `success`) персонаж вырезан/собран из i2i-рендера, а карточки-реквизит и конфетти нарисованы кодом в токенах дизайн-системы. Это не «генерация фона», а вёрстка сцены: лицо и одежда остаются из Qwen, свет и палитра — из макета. Поэтому такие файлы прозрачные и ложатся на бумажный градиент без прямоугольной подложки.

## Новый набор

| Файл | Формат | Где используется |
|---|---|---|
| `kiki-flight.webp` | 1280×704, RGBA | вход, правая панель (полный полёт) |
| `kiki-flight-portrait.webp` | 768×1024, RGBA | вертикальный герой, логин/промо |
| `kiki-flight-hero.webp` | 900×620, RGBA | герой дашборда админа/клиента |
| `kiki-flight-avatar.webp` | 640×640, RGBA | аватар Кики в панели/сайдбаре |
| `kiki-flight-badge.webp` | 640×640, RGBA | empty states, компактные подсказки |
| `kiki-standing.webp` | 271×820, RGBA | исходник для сцен: стоит, руки сложены |
| `kiki-templates.webp` | 1200×900, RGBA | шапка «Шаблоны задач» |
| `kiki-invoices.webp` | 1200×900, RGBA | сводка «Счета» |
| `kiki-assistant.webp` | 1100×850, RGBA | приветствие в панели Кики |
| `kiki-success.webp` | 1200×900, RGBA | успех/пустое состояние (сцена) |
| `kiki-success-badge.webp` | 640×640, RGBA | компактный success в empty state |
| `kiki-desk.webp` | 1152×1010, RGB | клиентский дашборд, framed-карточка «за работой» |
| `kiki-hero.webp` | 1024×576 | старый спокойный кадр (резерв) |
| `kiki-empty.webp` | 640×480 | старый empty-кадр (резерв) |
| `kiki-thinking.webp` | 384×512 | размышление |
| `kiki-avatar.webp` | 640×640 | классический аватар (резерв) |

Все файлы — в `public/images/kiki/`. Старые ассеты не удалены, чтобы можно было откатиться.

## Правила использования

- Не растягивать, не перекрашивать, не добавлять текст внутрь изображения.
- Не ставить лицо вплотную к краю контейнера: у flight-кадра есть хвост метлы и развевающиеся волосы.
- Для маленьких размеров (≤120px) использовать `flight-avatar` или `flight-badge`, не полный 16:9.
- На светлом фоне использовать прозрачные WebP; не класть на тёмную подложку.
- Композиция: персонаж справа, левая треть пустая под заголовок/кнопки.

## Код

- `resources/js/Components/Kiki/KikiAvatar.vue` — варианты `avatar`, `hero`, `flight`, `empty`, `thinking`, `classic`.
- `resources/js/Pages/Auth/Login.vue` — `kiki-flight-portrait.webp`.
- `resources/js/Pages/Admin/Dashboard.vue` — `kiki-flight-hero.webp`; empty state — `kiki-success-badge.webp`.
- `resources/js/Pages/Client/Dashboard.vue` — `kiki-desk.webp` в рамке с тенью.
- `resources/js/Pages/Admin/TaskTemplates/Index.vue` — `kiki-templates.webp`.
- `resources/js/Pages/Admin/Invoices/Index.vue` — `kiki-invoices.webp`.
- `resources/js/Components/Assistant/AssistantPanel.vue` — `kiki-assistant.webp` в приветствии, `avatar` в шапке.
- `EmptyState.vue` + `variant="flight"` — `kiki-flight-badge.webp`.
- `AppShell.vue` — `avatar` (flight face).
- `kiki-desk.webp` — сцена «за работой» на клиентском дашборде; обрезана по столу, чтобы не показывать обувь.

Дизайн-токены и правила — [.superdesign/design-system.md](../../.superdesign/design-system.md).
