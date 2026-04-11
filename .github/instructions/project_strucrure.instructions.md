---
description: Архитектура и структура каталогов проекта CRM. Загружать при создании файлов, навигации по проекту, рефакторинге.
applyTo: '**/*.{php,js,ts,vue,blade.php,json,yaml,yml}'
---

# CRM для самозанятого — Архитектура и структура проекта

## 1. Стек технологий

| Слой | Технология | Версия |
|------|------------|--------|
| Backend | PHP + Laravel | PHP 8.2+, Laravel 11+ |
| Frontend | Vue.js 3 + Inertia.js | Vue 3.4+, Inertia 1.x |
| Сборка | Vite | 5.x |
| CSS | Tailwind CSS | 3.x |
| БД | PostgreSQL | 15+ |
| Кэш / Очереди | Redis | 7+ |
| Платежи | yoomoney/yookassa-sdk-php | ^3.0 |
| Тесты | Pest | 2.x |
| Линтинг PHP | Laravel Pint | latest |
| Линтинг JS | ESLint + Prettier | latest |

---

## 2. Архитектурные принципы

- **Service Layer** — бизнес-логика вынесена в сервисные классы (`app/Services/`), контроллеры тонкие
- **Action-классы** — сложные операции (смена статуса, создание платежа) оформлены как отдельные Action-классы (`app/Actions/`)
- **Form Requests** — вся валидация входных данных в отдельных Request-классах
- **Policies** — авторизация через Laravel Policies, привязанные к моделям
- **Enums** — все статусы и типы — нативные PHP enum-ы (`app/Enums/`)
- **Events + Listeners** — побочные эффекты (уведомления, логирование) через систему событий
- **DTO (Data Transfer Objects)** — при необходимости передачи структурированных данных между слоями
- **Repository не используется** — Eloquent используется напрямую в сервисах (достаточно для масштаба проекта)

---

## 3. Структура каталогов

```
self-employed-crm/
├── app/
│   ├── Actions/                        # Одноразовые бизнес-операции
│   │   ├── Task/
│   │   │   ├── ChangeTaskStatusAction.php      # Смена статуса + побочные эффекты
│   │   │   ├── CreateTaskAction.php            # Создание задачи (клиент/админ)
│   │   │   └── CloneMaintenanceTaskAction.php  # Клонирование maintenance-задачи
│   │   ├── Invoice/
│   │   │   ├── CreateInvoiceAction.php         # Создание счёта
│   │   │   ├── CancelInvoiceAction.php         # Отмена счёта
│   │   │   └── ProcessRefundAction.php         # Инициация возврата
│   │   ├── Payment/
│   │   │   ├── CreatePaymentAction.php         # Создание платежа в ЮКасса
│   │   │   ├── ProcessRecurringPaymentAction.php # Автоплатёж
│   │   │   └── HandleWebhookAction.php         # Обработка webhook от ЮКасса
│   │   └── Client/
│   │       ├── CreateClientAction.php          # Создание клиента
│   │       └── DeactivateClientAction.php      # Деактивация клиента
│   │
│   ├── Console/
│   │   └── Commands/
│   │       ├── ProcessRecurringPaymentsCommand.php  # Ежедневный автоплатёж
│   │       ├── RetryFailedPaymentsCommand.php       # Повтор неудачных автоплатежей
│   │       ├── SendPaymentRemindersCommand.php      # Напоминания об оплате
│   │       └── CancelExpiredInvoicesCommand.php     # Отмена просроченных счетов
│   │
│   ├── Enums/
│   │   ├── UserRole.php              # admin, client
│   │   ├── TaskStatus.php            # pending_review, queued, in_progress, awaiting_review, rejected_by_client, completed, rejected_by_admin
│   │   ├── TaskType.php              # one_time, maintenance
│   │   ├── TaskPriority.php          # low, medium, high
│   │   ├── InvoiceStatus.php         # pending, paid, cancelled, refunded
│   │   └── RecurringInterval.php     # monthly, quarterly, yearly
│   │
│   ├── Events/
│   │   ├── Task/
│   │   │   ├── TaskCreated.php
│   │   │   ├── TaskStatusChanged.php
│   │   │   └── TaskCompleted.php
│   │   ├── Invoice/
│   │   │   ├── InvoiceCreated.php
│   │   │   ├── InvoicePaid.php
│   │   │   ├── InvoiceCancelled.php
│   │   │   └── RecurringPaymentFailed.php
│   │   └── Client/
│   │       └── ClientCreated.php
│   │
│   ├── Listeners/
│   │   ├── SendTaskStatusNotification.php
│   │   ├── SendNewTaskNotificationToAdmin.php
│   │   ├── SendInvoiceCreatedNotification.php
│   │   ├── SendPaymentReceivedNotification.php
│   │   ├── SendClientCreatedNotification.php
│   │   ├── LogTaskStatusChange.php               # Запись в TaskStatusHistory
│   │   ├── CancelPendingInvoicesOnReject.php     # Отмена счетов при rejected_by_admin
│   │   └── CloneMaintenanceTaskOnComplete.php    # Клонирование задачи обслуживания
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── LogoutController.php
│   │   │   │   └── PasswordController.php
│   │   │   ├── Admin/
│   │   │   │   ├── DashboardController.php       # Дашборд администратора
│   │   │   │   ├── ClientController.php          # CRUD клиентов
│   │   │   │   ├── TaskController.php            # Управление задачами
│   │   │   │   ├── InvoiceController.php         # Управление счетами
│   │   │   │   ├── TaskReportController.php      # Отчёты по задачам
│   │   │   │   ├── TaskCommentController.php     # Комментарии (админская часть)
│   │   │   │   └── SettingsController.php        # Настройки (ЮКасса, профиль)
│   │   │   ├── Client/
│   │   │   │   ├── DashboardController.php       # Дашборд клиента
│   │   │   │   ├── TaskController.php            # Задачи клиента
│   │   │   │   ├── TaskCommentController.php     # Комментарии клиента
│   │   │   │   ├── InvoiceController.php         # Счета клиента
│   │   │   │   └── ProfileController.php         # Профиль клиента
│   │   │   ├── Webhook/
│   │   │   │   └── YooKassaWebhookController.php # Обработка webhook ЮКасса
│   │   │   └── FileController.php                # Загрузка / скачивание файлов
│   │   │
│   │   ├── Middleware/
│   │   │   ├── EnsureUserIsActive.php            # Проверка is_active
│   │   │   ├── EnsureUserIsAdmin.php             # Только для admin
│   │   │   └── VerifyYooKassaWebhook.php         # Проверка IP + валидация webhook
│   │   │
│   │   └── Requests/
│   │       ├── Auth/
│   │       │   ├── LoginRequest.php
│   │       │   └── UpdatePasswordRequest.php
│   │       ├── Admin/
│   │       │   ├── StoreClientRequest.php
│   │       │   ├── UpdateClientRequest.php
│   │       │   ├── StoreTaskRequest.php
│   │       │   ├── UpdateTaskRequest.php
│   │       │   ├── ChangeTaskStatusRequest.php
│   │       │   ├── StoreInvoiceRequest.php
│   │       │   ├── StoreTaskReportRequest.php
│   │       │   └── StoreTaskCommentRequest.php
│   │       └── Client/
│   │           ├── StoreTaskRequest.php
│   │           ├── StoreTaskCommentRequest.php
│   │           ├── ReviewTaskRequest.php         # Принятие/отклонение работы
│   │           └── UpdateProfileRequest.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Task.php
│   │   ├── TaskComment.php
│   │   ├── TaskReport.php
│   │   ├── TaskStatusHistory.php
│   │   ├── Invoice.php
│   │   └── FileAttachment.php
│   │
│   ├── Notifications/
│   │   ├── ClientAccountCreated.php              # Клиенту: аккаунт создан
│   │   ├── NewTaskFromClient.php                 # Админу: новая заявка
│   │   ├── TaskStatusUpdated.php                 # Клиенту: статус изменён
│   │   ├── TaskReviewRequired.php                # Клиенту: ожидается оценка
│   │   ├── TaskAcceptedByClient.php              # Админу: работа принята
│   │   ├── TaskRejectedByClient.php              # Админу: работа отклонена
│   │   ├── InvoiceCreated.php                    # Клиенту: новый счёт
│   │   ├── PaymentReceived.php                   # Админу: платёж получен
│   │   ├── RecurringPaymentSuccess.php           # Клиенту: автоплатёж прошёл
│   │   ├── RecurringPaymentFailed.php            # Клиенту: автоплатёж не прошёл
│   │   └── RecurringPaymentSuspended.php         # Админу: автоплатёж приостановлен
│   │
│   ├── Policies/
│   │   ├── TaskPolicy.php                        # Кто что может делать с задачей
│   │   ├── TaskCommentPolicy.php
│   │   ├── TaskReportPolicy.php
│   │   ├── InvoicePolicy.php
│   │   ├── FileAttachmentPolicy.php
│   │   └── UserPolicy.php                        # Управление клиентами
│   │
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php               # Регистрация policies
│   │   └── EventServiceProvider.php              # Связь events → listeners
│   │
│   └── Services/
│       ├── YooKassaService.php                   # Обёртка над SDK: createPayment, createRefund, и т.д.
│       ├── InvoiceService.php                    # Бизнес-логика счетов
│       ├── TaskService.php                       # Бизнес-логика задач
│       ├── FileUploadService.php                 # Загрузка и валидация файлов
│       └── NotificationService.php               # Централизованная отправка уведомлений
│
├── bootstrap/
│   ├── app.php
│   └── providers.php
│
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── database.php
│   ├── filesystems.php                           # Диск для загрузок (local / s3)
│   ├── mail.php
│   ├── queue.php
│   └── services.php                              # yookassa.shop_id, yookassa.secret_key
│
├── database/
│   ├── factories/
│   │   ├── UserFactory.php
│   │   ├── TaskFactory.php
│   │   ├── TaskCommentFactory.php
│   │   ├── TaskReportFactory.php
│   │   ├── InvoiceFactory.php
│   │   └── FileAttachmentFactory.php
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── xxxx_xx_xx_create_tasks_table.php
│   │   ├── xxxx_xx_xx_create_task_comments_table.php
│   │   ├── xxxx_xx_xx_create_task_reports_table.php
│   │   ├── xxxx_xx_xx_create_task_status_histories_table.php
│   │   ├── xxxx_xx_xx_create_invoices_table.php
│   │   └── xxxx_xx_xx_create_file_attachments_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── AdminSeeder.php                       # Создание аккаунта администратора
│
├── resources/
│   ├── css/
│   │   └── app.css                               # Tailwind директивы
│   ├── js/
│   │   ├── app.js                                # Точка входа Inertia + Vue
│   │   ├── bootstrap.js                          # Axios, Echo (если нужен)
│   │   ├── Components/                           # Переиспользуемые Vue-компоненты
│   │   │   ├── UI/
│   │   │   │   ├── Button.vue
│   │   │   │   ├── Modal.vue
│   │   │   │   ├── Badge.vue                     # Бейджи статусов
│   │   │   │   ├── Card.vue
│   │   │   │   ├── Table.vue
│   │   │   │   ├── Pagination.vue
│   │   │   │   ├── FileUploader.vue
│   │   │   │   ├── DropdownMenu.vue
│   │   │   │   └── NotificationBell.vue          # Иконка уведомлений
│   │   │   ├── Form/
│   │   │   │   ├── TextInput.vue
│   │   │   │   ├── TextArea.vue
│   │   │   │   ├── SelectInput.vue
│   │   │   │   ├── DatePicker.vue
│   │   │   │   └── InputError.vue
│   │   │   └── Layout/
│   │   │       ├── AdminLayout.vue               # Шаблон страниц администратора
│   │   │       ├── ClientLayout.vue              # Шаблон страниц клиента
│   │   │       ├── GuestLayout.vue               # Шаблон для login
│   │   │       ├── Sidebar.vue
│   │   │       └── Header.vue
│   │   │
│   │   ├── Pages/                                # Inertia-страницы (по роутам)
│   │   │   ├── Auth/
│   │   │   │   └── Login.vue
│   │   │   ├── Admin/
│   │   │   │   ├── Dashboard.vue
│   │   │   │   ├── Clients/
│   │   │   │   │   ├── Index.vue                 # Список клиентов
│   │   │   │   │   ├── Create.vue                # Создание клиента
│   │   │   │   │   ├── Edit.vue                  # Редактирование клиента
│   │   │   │   │   └── Show.vue                  # Детали клиента + его задачи
│   │   │   │   ├── Tasks/
│   │   │   │   │   ├── Index.vue                 # Список задач с фильтрами
│   │   │   │   │   ├── Create.vue                # Создание задачи
│   │   │   │   │   ├── Show.vue                  # Детали: комментарии, отчёты, счета, статус
│   │   │   │   │   └── Edit.vue                  # Редактирование задачи
│   │   │   │   ├── Invoices/
│   │   │   │   │   ├── Index.vue                 # Список счетов
│   │   │   │   │   ├── Create.vue                # Создание счёта
│   │   │   │   │   └── Show.vue                  # Детали счёта + история платежей
│   │   │   │   └── Settings/
│   │   │   │       └── Index.vue                 # Настройки ЮКасса, профиль
│   │   │   └── Client/
│   │   │       ├── Dashboard.vue
│   │   │       ├── Tasks/
│   │   │       │   ├── Index.vue                 # Мои задачи
│   │   │       │   ├── Create.vue                # Создание заявки
│   │   │       │   └── Show.vue                  # Детали + комментарии + принять/отклонить
│   │   │       ├── Invoices/
│   │   │       │   ├── Index.vue                 # Мои счета
│   │   │       │   └── Show.vue                  # Детали счёта + кнопка оплаты
│   │   │       └── Profile/
│   │   │           └── Edit.vue                  # Профиль + смена пароля
│   │   │
│   │   ├── Composables/                          # Vue composables (хуки)
│   │   │   ├── useTaskStatus.js                  # Логика статусов: цвета, переводы, доступные переходы
│   │   │   ├── useNotifications.js               # Работа с уведомлениями
│   │   │   └── useFileUpload.js                  # Загрузка файлов
│   │   │
│   │   └── Utils/
│   │       ├── formatters.js                     # Форматирование дат, сумм, статусов
│   │       └── constants.js                      # Константы (статусы, приоритеты, интервалы)
│   │
│   └── views/
│       └── app.blade.php                         # Корневой Blade-шаблон для Inertia
│
├── routes/
│   ├── web.php                                   # Все веб-роуты (auth, admin/*, client/*)
│   ├── console.php                               # Расписание Artisan-команд
│   └── channels.php                              # Broadcast-каналы (если используется Echo)
│
├── storage/
│   └── app/
│       └── attachments/                          # Загруженные файлы
│
├── tests/
│   ├── Feature/
│   │   ├── Auth/
│   │   │   ├── LoginTest.php
│   │   │   └── PasswordChangeTest.php
│   │   ├── Admin/
│   │   │   ├── ClientManagementTest.php
│   │   │   ├── TaskManagementTest.php
│   │   │   ├── InvoiceManagementTest.php
│   │   │   ├── TaskReportTest.php
│   │   │   └── DashboardTest.php
│   │   ├── Client/
│   │   │   ├── TaskViewTest.php
│   │   │   ├── TaskCreationTest.php
│   │   │   ├── TaskReviewTest.php
│   │   │   ├── TaskCommentTest.php
│   │   │   ├── InvoiceViewTest.php
│   │   │   └── ProfileTest.php
│   │   ├── Payment/
│   │   │   ├── YooKassaPaymentTest.php
│   │   │   ├── WebhookHandlingTest.php
│   │   │   └── RecurringPaymentTest.php
│   │   └── Webhook/
│   │       └── YooKassaWebhookTest.php
│   ├── Unit/
│   │   ├── Actions/
│   │   │   ├── ChangeTaskStatusActionTest.php
│   │   │   ├── CreateInvoiceActionTest.php
│   │   │   └── CloneMaintenanceTaskActionTest.php
│   │   ├── Enums/
│   │   │   └── TaskStatusTest.php                # Тест допустимых переходов
│   │   ├── Models/
│   │   │   ├── TaskTest.php
│   │   │   ├── InvoiceTest.php
│   │   │   └── UserTest.php
│   │   └── Services/
│   │       ├── YooKassaServiceTest.php
│   │       └── InvoiceServiceTest.php
│   └── Pest.php
│
├── .env.example
├── artisan
├── composer.json
├── package.json
├── tailwind.config.js
├── vite.config.js
├── phpunit.xml
└── README.md
```

---

## 4. Карта классов по слоям

### 4.1. Models → отношения (Eloquent)

```
User
  ├── hasMany(Task::class, 'client_id')       → задачи как клиент
  ├── hasMany(Task::class, 'created_by')       → созданные задачи
  ├── hasMany(TaskComment::class, 'author_id')
  ├── hasMany(Invoice::class, 'client_id')
  └── hasMany(FileAttachment::class, 'uploaded_by')

Task
  ├── belongsTo(User::class, 'client_id')     → клиент
  ├── belongsTo(User::class, 'created_by')     → создатель
  ├── hasMany(TaskComment::class)
  ├── hasMany(TaskReport::class)
  ├── hasMany(Invoice::class)
  └── hasMany(TaskStatusHistory::class)

TaskComment
  ├── belongsTo(Task::class)
  └── belongsTo(User::class, 'author_id')

TaskReport
  ├── belongsTo(Task::class)
  └── belongsTo(User::class, 'author_id')

Invoice
  ├── belongsTo(Task::class)
  └── belongsTo(User::class, 'client_id')

TaskStatusHistory
  ├── belongsTo(Task::class)
  └── belongsTo(User::class, 'changed_by')

FileAttachment
  └── belongsTo(User::class, 'uploaded_by')
```

### 4.2. Enums → допустимые значения

```php
// app/Enums/TaskStatus.php
enum TaskStatus: string {
    case PendingReview    = 'pending_review';
    case Queued           = 'queued';
    case InProgress       = 'in_progress';
    case AwaitingReview   = 'awaiting_review';
    case RejectedByClient = 'rejected_by_client';
    case Completed        = 'completed';
    case RejectedByAdmin  = 'rejected_by_admin';

    // Метод: allowedTransitions() → возвращает массив допустимых следующих статусов
    // Метод: canBeChangedBy(UserRole $role) → проверка прав на смену
    // Метод: label() → человекочитаемое название на русском
}
```

### 4.3. Actions → ответственность

| Action | Что делает |
|--------|------------|
| `ChangeTaskStatusAction` | Валидирует переход, меняет статус, диспатчит `TaskStatusChanged`, записывает историю |
| `CreateTaskAction` | Создаёт задачу, устанавливает начальный статус, диспатчит `TaskCreated` |
| `CloneMaintenanceTaskAction` | Создаёт клон задачи для нового цикла обслуживания |
| `CreateInvoiceAction` | Создаёт счёт, привязывает к задаче, инициирует платёж в ЮКасса |
| `CancelInvoiceAction` | Отменяет счёт, отменяет платёж в ЮКасса (если pending) |
| `ProcessRefundAction` | Инициирует возврат через ЮКасса |
| `CreatePaymentAction` | Создаёт платёж через SDK, возвращает URL для оплаты |
| `ProcessRecurringPaymentAction` | Безакцептное списание по сохранённому способу оплаты |
| `HandleWebhookAction` | Маршрутизирует webhook-события к обработчикам |
| `CreateClientAction` | Создаёт клиента, хеширует пароль, диспатчит `ClientCreated` |
| `DeactivateClientAction` | Деактивирует клиента (`is_active = false`) |

### 4.4. Services → ответственность

| Service | Что делает |
|---------|------------|
| `YooKassaService` | Обёртка над SDK: `createPayment()`, `capturePayment()`, `createRefund()`, `getPaymentInfo()`. Инкапсулирует конфигурацию и обработку ошибок SDK |
| `InvoiceService` | Расчёт `recurring_next_date`, логика повторных попыток автоплатежа, проверка лимитов |
| `TaskService` | Вспомогательная логика задач: фильтрация, статистика для дашборда |
| `FileUploadService` | Валидация MIME-типа, сохранение файлов, генерация безопасного пути |
| `NotificationService` | Диспатч уведомлений по каналам (mail + database) с учётом настроек |

### 4.5. Events → Listeners (карта связей)

```
TaskCreated
  → SendNewTaskNotificationToAdmin       (если создана клиентом)

TaskStatusChanged
  → LogTaskStatusChange                  (всегда — запись в TaskStatusHistory)
  → SendTaskStatusNotification           (уведомление заинтересованной стороне)
  → CancelPendingInvoicesOnReject        (если → rejected_by_admin)
  → CloneMaintenanceTaskOnComplete       (если → completed и type=maintenance)

InvoiceCreated
  → SendInvoiceCreatedNotification       (клиенту)

InvoicePaid
  → SendPaymentReceivedNotification      (админу)

RecurringPaymentFailed
  → SendRecurringPaymentFailedNotification  (админу после 3 неудач)

ClientCreated
  → SendClientCreatedNotification        (клиенту: данные для входа)
```

---

## 5. Маршрутизация (routes/web.php)

```
// Auth (без регистрации)
GET    /login                          → Auth\LoginController@create
POST   /login                          → Auth\LoginController@store
POST   /logout                         → Auth\LogoutController@destroy
PUT    /password                        → Auth\PasswordController@update

// Admin (middleware: auth, is_active, is_admin)
GET    /admin/dashboard                 → Admin\DashboardController@index
GET    /admin/clients                   → Admin\ClientController@index
POST   /admin/clients                   → Admin\ClientController@store
GET    /admin/clients/create            → Admin\ClientController@create
GET    /admin/clients/{client}          → Admin\ClientController@show
GET    /admin/clients/{client}/edit     → Admin\ClientController@edit
PUT    /admin/clients/{client}          → Admin\ClientController@update
PATCH  /admin/clients/{client}/deactivate → Admin\ClientController@deactivate
GET    /admin/tasks                     → Admin\TaskController@index
POST   /admin/tasks                     → Admin\TaskController@store
GET    /admin/tasks/create              → Admin\TaskController@create
GET    /admin/tasks/{task}              → Admin\TaskController@show
GET    /admin/tasks/{task}/edit         → Admin\TaskController@edit
PUT    /admin/tasks/{task}              → Admin\TaskController@update
PATCH  /admin/tasks/{task}/status       → Admin\TaskController@updateStatus
POST   /admin/tasks/{task}/comments     → Admin\TaskCommentController@store
POST   /admin/tasks/{task}/reports      → Admin\TaskReportController@store
GET    /admin/invoices                  → Admin\InvoiceController@index
POST   /admin/invoices                  → Admin\InvoiceController@store
GET    /admin/invoices/create           → Admin\InvoiceController@create
GET    /admin/invoices/{invoice}        → Admin\InvoiceController@show
PATCH  /admin/invoices/{invoice}/cancel → Admin\InvoiceController@cancel
POST   /admin/invoices/{invoice}/refund → Admin\InvoiceController@refund
GET    /admin/settings                  → Admin\SettingsController@edit
PUT    /admin/settings                  → Admin\SettingsController@update

// Client (middleware: auth, is_active)
GET    /dashboard                       → Client\DashboardController@index
GET    /tasks                           → Client\TaskController@index
POST   /tasks                           → Client\TaskController@store
GET    /tasks/create                    → Client\TaskController@create
GET    /tasks/{task}                    → Client\TaskController@show
PATCH  /tasks/{task}/review             → Client\TaskController@review
POST   /tasks/{task}/comments           → Client\TaskCommentController@store
GET    /invoices                        → Client\InvoiceController@index
GET    /invoices/{invoice}              → Client\InvoiceController@show
GET    /invoices/{invoice}/pay          → Client\InvoiceController@pay
GET    /profile                         → Client\ProfileController@edit
PUT    /profile                         → Client\ProfileController@update

// Файлы (middleware: auth, is_active)
POST   /files/upload                    → FileController@upload
GET    /files/{file}/download           → FileController@download

// Webhook (без auth, с middleware проверки IP)
POST   /webhooks/yookassa              → Webhook\YooKassaWebhookController@handle
```

---

## 6. Middleware-цепочки

| Группа | Middleware |
|--------|-----------|
| `admin` | `auth`, `EnsureUserIsActive`, `EnsureUserIsAdmin` |
| `client` | `auth`, `EnsureUserIsActive` |
| `webhook` | `VerifyYooKassaWebhook` (проверка IP + структуры) |
| guest | `guest` (только login-страница) |

---

## 7. Конфигурация сервисов (config/services.php)

```php
'yookassa' => [
    'shop_id'    => env('YOOKASSA_SHOP_ID'),
    'secret_key' => env('YOOKASSA_SECRET_KEY'),
],
```

**`.env` переменные:**
```
YOOKASSA_SHOP_ID=
YOOKASSA_SECRET_KEY=

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=self_employed_crm
DB_USERNAME=
DB_PASSWORD=

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
```

---

## 8. Расписание команд (routes/console.php)

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('payments:process-recurring')->dailyAt('09:00');
Schedule::command('payments:retry-failed')->dailyAt('10:00');
Schedule::command('invoices:send-reminders')->dailyAt('12:00');
Schedule::command('invoices:cancel-expired')->weeklyOn(1, '03:00');
```

---

## 9. Соглашения по коду

### 9.1. PHP (Backend)
- **Именование:** PascalCase для классов, camelCase для методов, snake_case для переменных БД
- **Типизация:** строгая типизация (`declare(strict_types=1)`) во всех файлах
- **Форматирование:** Laravel Pint (preset: laravel)
- **Enum-ы:** использовать нативные PHP 8.1+ enum с `BackedEnum` (string)
- **UUID:** использовать `HasUuids` trait в моделях
- **Mass assignment:** все модели имеют `$fillable` или `$guarded`
- **Даты:** использовать `$casts` для приведения к `datetime`, `date`

### 9.2. JavaScript / Vue (Frontend)
- **Composition API:** использовать `<script setup>` синтаксис
- **Именование компонентов:** PascalCase (`TaskCard.vue`)
- **Именование страниц:** PascalCase, по структуре роутов (`Admin/Tasks/Index.vue`)
- **Props:** типизировать через `defineProps`
- **Inertia:** использовать `useForm()` для форм, `router.visit()` для навигации
- **Composables:** выносить переиспользуемую логику в `Composables/`

### 9.3. Тесты
- **Фреймворк:** Pest PHP
- **Feature-тесты:** по одному файлу на контроллер / сценарий
- **Unit-тесты:** для Actions, Enums, Services
- **Фабрики:** для каждой модели
- **Naming:** `it('creates a task for client')`, `it('prevents unauthorized status change')`