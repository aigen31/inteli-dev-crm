# Иллюстрации Кики

## Канон

Кики — аниме-ведьма с сайта `inteli-dev.ru`: огромная чёрная остроконечная шляпа с широкими полями, коричневая лента и латунная пряжка, длинные волнистые тёмно-каштановые волосы, зелёные глаза, чёрное платье с высоким воротом и бантом, чёрные шнурованные ботинки. Лицо и одежда не меняются между кадрами.

Исходный референс — `kiki_master.png` (полный рост, прозрачный фон) из проекта ComfyUI. Он же используется как identity-референс в i2i.

## Как сгенерировано

- ComfyUI, Qwen Image 2.1 Image Edit, i2i.
- Workflow: `/home/aigen31/Work/ComfyUI/user/default/workflows/image_qwen_image_2_1_image_edit.json`; рабочие копии — `storage/app/kiki/*-workflow.json`.
- `<image1>` — изображение, которое редактируется; `<image2>` — identity-кроп или плоский цветовой референс. Для релайта и предметных правок второй референс — сознательно **не персонаж**, иначе Qwen дублирует Кики.
- Параметры: steps 30, cfg 1, euler/simple, seed индивидуальный, prompt enhancer off.
- Промпты сохраняются в `storage/app/kiki/*-workflow.json` (`459.prompt`).

**Важно:** финальные иллюстрации — только растровые рендеры Qwen. Векторные карточки, монеты, чат-баблы и конфетти не рисуются кодом. Допустима только постобработка растра: кроп, alpha-cutout, выравнивание света.

## Набор

| Файл | Формат | Где используется |
|---|---|---|
| `kiki-flight.webp` | 1280×704, RGBA | светлый вариант полёта после тёмного, источник кропов |
| `kiki-flight-portrait.webp` | 768×1024, RGBA | вход, правая панель |
| `kiki-flight-hero.webp` | 900×620, RGBA | hero админ-дашборда |
| `kiki-flight-avatar.webp` | 640×640, RGB | аватар из светлого полёта; полная высокая шляпа |
| `kiki-flight-badge.webp` | 640×640, RGBA | empty states; шляпа целиком |
| `kiki-seated.webp` | 1154×1263, RGBA | шаблоны задач, приветствие ассистента, empty; высокая шляпа и боковые поля |
| `kiki-desk.webp` | 1152×1024, RGB | клиентский дашборд, framed-карточка «за работой»; высокая шляпа |
| `kiki-standing.webp` | 544×1632, RGBA | резерв: стоячая поза в светлой теме |
| `kiki-hero.webp` | 1024×576, RGBA | старый спокойный кадр (резерв) |
| `kiki-empty.webp` | 640×480, RGBA | старый empty-кадр (резерв) |
| `kiki-thinking.webp` | 384×512, RGBA | размышление |
| `kiki-avatar.webp` | 640×640, RGBA | классический аватар (резерв) |

Все файлы — в `public/images/kiki/`.

## Свет и кадр

- Свет — мягкий тёплый дневной, бумажный фон, лавандовое свечение и персиковый акцент; без ночной темноты.
- Полёт оставлен в одобренном варианте: полная шляпа, прозрачный фон, мягкий свет; кропы только с запасом.
- Остальные сцены пересобраны i2i с отдельным **hat-reference**: высокая остроконечная шляпа с изогнутым кончиком, широкими полями, коричневой лентой и пряжкой; шляпа всегда целиком.
- Шляпа всегда входит в кадр целиком, с запасом над кончиком; для аватара — тоже (не «face crop» без шляпы).
- Композиция: персонаж справа, левая часть пустая под заголовок/кнопки.
- Прозрачный фон; не растягивать, не перекрашивать, не добавлять текст поверх персонажа.

## Код

- `resources/js/Components/Kiki/KikiAvatar.vue` — варианты `avatar`, `hero`, `flight`, `empty`, `thinking`, `seated`, `classic`.
- `resources/js/Pages/Auth/Login.vue` — `kiki-flight-portrait.webp`.
- `resources/js/Pages/Admin/Dashboard.vue` — `kiki-flight-hero.webp`; empty — `flight` badge.
- `resources/js/Pages/Admin/TaskTemplates/Index.vue` — `kiki-seated.webp`.
- `resources/js/Pages/Admin/Invoices/Index.vue` — `kiki-flight-portrait.webp`.
- `resources/js/Pages/Client/Dashboard.vue` — `kiki-desk.webp` в рамке.
- `resources/js/Components/Assistant/AssistantPanel.vue` — `kiki-seated.webp` в приветствии, `avatar` в шапке.

Дизайн-токены и правила — [.superdesign/design-system.md](../../.superdesign/design-system.md).
