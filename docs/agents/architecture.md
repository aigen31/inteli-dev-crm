# Архитектура и домен

## Поток запроса

`routes/web.php` → Controller → FormRequest (валидация) → Action/Service (бизнес-логика) → Eloquent-модель → Event → Listener (уведомления, история).

Контроллер тонкий: принимает запрос, авторизует через Policy и вызывает Action/Service. Бизнес-правила живут в Action/Service, а не в контроллере или Vue.

## Слои

- `app/Actions/` — одноразовые операции: `Client/`, `Invoice/` (создание, отмена, возврат, ручное подтверждение оплаты), `Payment/` (создание платежа, вебхук, рекуррент), `Task/`.
- `app/Services/` — `TaskService`, `InvoiceService`, `FileUploadService`, `YooKassaService`, `SettingsService` (реквизиты прямого перевода).
- `app/Http/Requests/` — вся валидация, включая проверки, зависящие от способа оплаты.
- `app/Policies/` — авторизация по моделям; проверки ролей и владения счётом/задачей не дублируются в контроллерах.
- `app/Enums/` — `UserRole`, `TaskStatus`, `TaskType`, `TaskPriority`, `InvoiceStatus`, `PaymentMethod`, `RecurringInterval`; в БД хранятся строковые значения.
- `app/Events` + `app/Listeners` — побочные эффекты: уведомления, `TaskStatusHistory`, клонирование maintenance-задачи, отмена счетов. Регистрация автоматическая (discovery), без провайдера.

## Роли и доступ

- `admin` — клиенты, задачи, счета, настройки; `client` — только свои задачи, счета и профиль.
- Middleware-алиасы в `bootstrap/app.php`: `is_admin`, `is_active`, `yookassa_webhook`.
- Клиентские маршруты: `/dashboard`, `/tasks`, `/invoices`, `/profile`. Админские: `/admin/*`.

## Домен

- Задача (`one_time` / `maintenance`) проходит `pending_review → queued → in_progress → awaiting_review → completed` с ветками отклонения.
- Счёт: `pending → paid | cancelled | refunded`; суммы `decimal(10,2)` в рублях.
- `payment_method` у счёта:
  - `yookassa` — онлайн-оплата, вебхук обновляет статус, рекуррент разрешён;
  - `transfer` — прямой перевод на реквизиты, оплата вне кассы, рекуррент запрещён, оплату админ подтверждает вручную.
- Ручное подтверждение: `PATCH /admin/invoices/{invoice}/mark-paid` → `MarkInvoiceAsPaidAction` → `InvoicePaid`.
- Реквизиты перевода — таблица `settings` (модель `Setting`, доступ `SettingsService`), редактируются на `/admin/settings`.
- Webhook ЮКассы: `POST /webhooks/yookassa` без auth, защищён `VerifyYooKassaWebhook`; ключи — `YOOKASSA_SHOP_ID`, `YOOKASSA_SECRET_KEY` (`config/services.php`).

## Планировщик

`routes/console.php`:

- `payments:process-recurring` — ежедневно 09:00;
- `invoices:send-reminders` — ежедневно 12:00;
- `invoices:cancel-expired` — понедельник 03:00.

## Куда добавлять фичу

1. Маршрут в `routes/web.php` в нужную группу (`admin`, client, webhook).
2. FormRequest с правилами.
3. Action или Service с бизнес-логикой.
4. Тонкий контроллер + Policy при необходимости.
5. Страница в `resources/js/Pages/{Admin,Client}/…` и ссылка в layout.

Бизнес-требования и описания сущностей — `.github/instructions/terms_of_reference.instructions.md` (читать перед изменением доменной логики; см. оговорку о дрейфе в [gotchas.md](gotchas.md)).
