# Подводные камни

- **`bootstrap/cache/*.php` нельзя в образ.** Host-кеш содержит dev-провайдер Pail, production-vendor его не знает → контейнер падает в рестарт-луп. Файлы исключены в `.dockerignore`; перед сборкой можно `rm -f bootstrap/cache/*.php`.
- **Оpcache `validate_timestamps=0`.** PHP-правки в контейнере не подхватываются без рестарта `app`; ассеты после рестарта затираются образом.
- **502 после пересоздания `app`.** nginx кеширует IP upstream; перезапустите nginx.
- **Inertia 3 title.** В `<head>` нужен `<title data-inertia>`; старый атрибут `inertia` не работает.
- **TipTap StarterKit уже содержит Link/Underline.** Повторное подключение даёт warning `Duplicate extension names`.
- **HTML из WYSIWYG и ИИ.** Храните и выводите только после `HtmlSanitizer`; `v-html` без санитайза — XSS.
- **Custom fields: дефолты ≠ значения.** `customFieldsData()` возвращает только сохранённые значения; для API/карточек используйте `CustomFieldService::customFieldsWithDefaults()` / `serializedValues()`.
- **Custom fields: типизированные колонки.** Не складывайте всё в `value_json`: фильтры и API зависят от `value_text/number/date/boolean/json`.
- **API-токен = роль пользователя.** Клиентский токен видит только свои данные; `read` не даёт `POST /assistant/chat` и другие write-маршруты.
- **Кики и деньги.** `create_invoice`/`mark_invoice_paid` создают `proposed`-действие; confirm перепроверяет роль и скоуп. Не выполняйте финансовые tool calls в обход `AgentService`.
- **AI-ключ.** Хранится зашифрованным в `settings`; не логируйте его и не отдавайте во фронтенд. Без ключа Кики отвечает понятной ошибкой, шаблонный генератор уходит в fallback.
- **MCP и stdio.** Сервер пишет в stdout только JSON-RPC; логи — в stderr. Иначе клиент ломает протокол.
- **ЮКасса shopId.** SDK ждёт `?int`; сервис приводит значение и терпит пустые ключи.
- **Прямой перевод.** Только ручное подтверждение, без рекуррента и без `pay`-роута.
- **UUID и morph.** Пользователи/задачи/счёта — UUID; `custom_field_values` использует `entity_type` + `entity_id`, morph-map задан в `AppServiceProvider`. `notifications.notifiable_id` — `uuidMorphs`.
- **`settings` без кеша.** `SettingsService` читает в рамках запроса; при добавлении кеша нужна инвалидация.
- **Промо-лендинг отдельный.** `promo/` — статический сайт в compose, не часть Inertia-приложения.
