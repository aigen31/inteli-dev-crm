<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth;
use App\Http\Controllers\CalendarIntervalController;
use App\Http\Controllers\Client;
use App\Http\Controllers\DashboardLayoutController;
use App\Http\Controllers\Executor;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NavigationHistoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SecretFeatureController;
use App\Http\Controllers\SidebarPreferenceController;
use App\Http\Controllers\SystemProjectController;
use App\Http\Controllers\Webhook\YooKassaWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

// Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [Auth\LoginController::class, 'create'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'store']);
});

// UI language can be changed before login too.
Route::get('/locale/{locale}', LocaleController::class)->name('locale.update');

// Password reset from an admin-created link.
Route::get('/reset-password/{token}', [Auth\PasswordResetController::class, 'show'])->name('password.reset');
Route::post('/reset-password', [Auth\PasswordResetController::class, 'store'])->middleware('throttle:10,1')->name('password.reset.store');

// Authenticated
Route::middleware('auth')->group(function () {
    Route::get('/calendar/intervals', CalendarIntervalController::class)->middleware('is_active')->name('calendar.intervals');
    Route::get('/calendar/tasks', [CalendarIntervalController::class, 'tasks'])->middleware('is_active')->name('calendar.tasks');
    Route::get('/calendar/activity', [CalendarIntervalController::class, 'activity'])->middleware('is_active')->name('calendar.activity');
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/feedback', [FeedbackController::class, 'index'])->middleware('is_active')->name('feedback.index');

    Route::delete('/navigation-history', [NavigationHistoryController::class, 'destroy'])
        ->middleware('is_active')
        ->name('navigation.clear');

    Route::post('/logout', [Auth\LogoutController::class, 'destroy'])->name('logout');
    Route::put('/password', [Auth\PasswordController::class, 'update'])->name('password.update');

    // Kiki assistant (both roles)
    Route::middleware(['is_active', 'throttle:assistant'])->group(function () {
        Route::get('/assistant', [AssistantController::class, 'page'])->name('assistant.index');
        Route::get('/assistant/state', [AssistantController::class, 'state'])->name('assistant.state');

        Route::get('/assistant/conversations', [AssistantController::class, 'sessions'])->name('assistant.conversations.index');
        Route::post('/assistant/conversations', [AssistantController::class, 'createSession'])->name('assistant.conversations.store');
        Route::get('/assistant/conversations/{conversation}', [AssistantController::class, 'showSession'])->name('assistant.conversations.show');
        Route::patch('/assistant/conversations/{conversation}', [AssistantController::class, 'updateSession'])->name('assistant.conversations.update');
        Route::delete('/assistant/conversations/{conversation}', [AssistantController::class, 'destroySession'])->name('assistant.conversations.destroy');
        Route::post('/assistant/conversations/{conversation}/regenerate', [AssistantController::class, 'regenerate'])->name('assistant.conversations.regenerate');

        Route::post('/assistant/documents', [AssistantController::class, 'uploadDocument'])->name('assistant.documents.store');
        Route::post('/assistant/chat', [AssistantController::class, 'chat'])->name('assistant.chat');
        Route::post('/assistant/actions/{action}/confirm', [AssistantController::class, 'confirm'])->name('assistant.confirm');
        Route::post('/assistant/actions/{action}/reject', [AssistantController::class, 'reject'])->name('assistant.reject');
        Route::delete('/assistant', [AssistantController::class, 'clear'])->name('assistant.clear');
        Route::get('/assistant/suggestions', [AssistantController::class, 'suggestions'])->name('assistant.suggestions');
    });

    // Files
    Route::middleware('is_active')->group(function () {
        Route::post('/files/upload', [FileController::class, 'upload'])->name('files.upload');
        Route::get('/files/{file}/preview', [FileController::class, 'preview'])->name('files.preview');
        Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
    });

    // Service projects available to every authenticated user.
    Route::middleware('is_active')->prefix('projects/system')->name('system-projects.')->group(function () {
        Route::get('/{project}', [SystemProjectController::class, 'show'])->whereIn('project', ['feedback', 'bugs'])->name('show');
        Route::post('/{project}', [SystemProjectController::class, 'store'])->whereIn('project', ['feedback', 'bugs'])->name('store');
        Route::post('/{project}/{item}/vote', [SystemProjectController::class, 'vote'])->whereIn('project', ['feedback', 'bugs'])->name('vote');
        Route::patch('/{project}/{item}/status', [SystemProjectController::class, 'updateStatus'])->whereIn('project', ['feedback', 'bugs'])->name('status');
        Route::patch('/{project}/{item}', [SystemProjectController::class, 'update'])->whereIn('project', ['feedback', 'bugs'])->name('update');
        Route::patch('/{project}/{item}/archive', [SystemProjectController::class, 'archive'])->whereIn('project', ['feedback', 'bugs'])->name('archive');
        Route::patch('/{project}/{item}/restore', [SystemProjectController::class, 'restore'])->whereIn('project', ['feedback', 'bugs'])->name('restore');
        Route::patch('/{project}/{item}/reject', [SystemProjectController::class, 'reject'])->whereIn('project', ['feedback', 'bugs'])->name('reject');
    });

    // Secret, opt-in interface features.
    Route::middleware('is_active')->group(function () {
        Route::post('/settings/unlock', [SecretFeatureController::class, 'unlock'])->middleware('throttle:5,1')->name('settings.unlock');
        Route::patch('/settings/theme', [SecretFeatureController::class, 'theme'])->name('settings.theme');
    });

    // Executor routes
    Route::middleware(['is_active', 'is_executor'])->prefix('executor')->name('executor.')->group(function () {
        Route::get('/dashboard', [Executor\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/tasks', [Executor\TaskController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/{task}', [Executor\TaskController::class, 'show'])->name('tasks.show');
        Route::patch('/tasks/{task}/status', [Executor\TaskController::class, 'updateStatus'])->name('tasks.status');
        Route::patch('/tasks/{task}/checklist', [Executor\TaskController::class, 'updateChecklist'])->name('tasks.checklist');
        Route::post('/tasks/{task}/comments', [Executor\TaskCommentController::class, 'store'])->name('tasks.comments.store');
        Route::post('/tasks/{task}/reports', [Executor\TaskReportController::class, 'store'])->name('tasks.reports.store');
        Route::get('/reports', [Executor\ReportController::class, 'index'])->name('reports.index');
    });

    // Admin routes
    Route::middleware(['is_active', 'is_admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/widget-zone/data', [Admin\WidgetZoneController::class, 'data'])->name('widget-zone.data');
        Route::patch('/widget-zone/layout', [Admin\WidgetZoneController::class, 'updateLayout'])->name('widget-zone.layout');

        // Team
        Route::get('/team', [Admin\TeamController::class, 'index'])->name('team.index');
        Route::get('/team/create', [Admin\TeamController::class, 'create'])->name('team.create');
        Route::post('/team', [Admin\TeamController::class, 'store'])->name('team.store');
        Route::patch('/team/{executor}/toggle', [Admin\TeamController::class, 'toggle'])->name('team.toggle');

        // Password reset links
        Route::post('/users/{user}/password-reset', [Admin\UserPasswordResetController::class, 'store'])->name('users.password-reset');

        // Clients
        Route::get('/clients', [Admin\ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/create', [Admin\ClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [Admin\ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [Admin\ClientController::class, 'show'])->name('clients.show');
        Route::get('/clients/{client}/edit', [Admin\ClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [Admin\ClientController::class, 'update'])->name('clients.update');
        Route::put('/clients/{client}/custom-fields', [Admin\ClientController::class, 'updateCustomFields'])->name('clients.custom-fields');
        Route::patch('/clients/{client}/deactivate', [Admin\ClientController::class, 'deactivate'])->name('clients.deactivate');

        // Forms and scenarios
        Route::get('/forms', [Admin\FormTemplateController::class, 'index'])->name('forms.index');
        Route::patch('/forms/{formTemplate}/toggle', [Admin\FormTemplateController::class, 'toggle'])->name('forms.toggle');

        // Briefs
        Route::get('/briefs', [Admin\BriefController::class, 'index'])->name('briefs.index');
        Route::get('/briefs/templates', [Admin\BriefTemplateController::class, 'index'])->name('briefs.templates');
        Route::delete('/briefs/templates/{briefTemplate}', [Admin\BriefTemplateController::class, 'destroy'])->whereUuid('briefTemplate')->name('briefs.templates.destroy');
        Route::get('/briefs/create', [Admin\BriefController::class, 'create'])->name('briefs.create');
        Route::post('/briefs', [Admin\BriefController::class, 'store'])->name('briefs.store');
        Route::get('/briefs/{brief}', [Admin\BriefController::class, 'show'])->whereUuid('brief')->name('briefs.show');
        Route::patch('/briefs/{brief}', [Admin\BriefController::class, 'update'])->whereUuid('brief')->name('briefs.update');
        Route::post('/briefs/{brief}/reopen', [Admin\BriefController::class, 'reopen'])->whereUuid('brief')->name('briefs.reopen');
        Route::post('/briefs/{brief}/archive', [Admin\BriefController::class, 'archive'])->whereUuid('brief')->name('briefs.archive');
        Route::get('/briefs/{brief}/export/{format}', [Admin\BriefController::class, 'export'])
            ->whereUuid('brief')
            ->whereIn('format', ['json', 'md', 'pdf'])
            ->name('briefs.export');

        // Task templates

        // Tasks
        Route::get('/tasks', [Admin\TaskController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/create', [Admin\TaskController::class, 'create'])->name('tasks.create');
        Route::get('/tasks/forms/create', [Admin\TaskController::class, 'createFromForm'])->name('tasks.forms.create');
        Route::post('/tasks/forms/{formTemplate}', [Admin\TaskController::class, 'storeForm'])->name('tasks.forms.store');
        Route::post('/tasks', [Admin\TaskController::class, 'store'])->name('tasks.store');
        Route::patch('/tasks/bulk-update', [Admin\TaskController::class, 'bulkUpdate'])->name('tasks.bulk-update');
        Route::get('/tasks/{task}', [Admin\TaskController::class, 'show'])->name('tasks.show');
        Route::get('/tasks/{task}/edit', [Admin\TaskController::class, 'edit'])->name('tasks.edit');
        Route::put('/tasks/{task}', [Admin\TaskController::class, 'update'])->name('tasks.update');
        Route::patch('/tasks/{task}/status', [Admin\TaskController::class, 'updateStatus'])->name('tasks.status');
        Route::patch('/tasks/{task}/checklist', [Admin\TaskController::class, 'updateChecklist'])->name('tasks.checklist');
        Route::post('/tasks/{task}/comments', [Admin\TaskCommentController::class, 'store'])->name('tasks.comments.store');
        Route::post('/tasks/{task}/reports', [Admin\TaskReportController::class, 'store'])->name('tasks.reports.store');

        // Reports
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/create', [ReportController::class, 'create'])->name('reports.create');
        Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
        Route::get('/reports/{report}/edit', [ReportController::class, 'edit'])->whereUuid('report')->name('reports.edit');
        Route::patch('/reports/{report}', [ReportController::class, 'update'])->whereUuid('report')->name('reports.update');
        Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->whereUuid('report')->name('reports.destroy');

        // Projects
        Route::get('/projects', [Admin\ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/create', [Admin\ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [Admin\ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [Admin\ProjectController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/edit', [Admin\ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [Admin\ProjectController::class, 'update'])->name('projects.update');
        Route::post('/projects/{project}/archive', [Admin\ProjectController::class, 'archive'])->name('projects.archive');
        Route::post('/projects/{project}/members', [Admin\ProjectController::class, 'storeMember'])->name('projects.members.store');
        Route::patch('/projects/{project}/members/{member}', [Admin\ProjectController::class, 'updateMember'])->name('projects.members.update');
        Route::delete('/projects/{project}/members/{member}', [Admin\ProjectController::class, 'destroyMember'])->name('projects.members.destroy');
        Route::post('/projects/{project}/invites', [Admin\ProjectInviteController::class, 'store'])->name('projects.invites.store');
        Route::delete('/projects/{project}/invites/{invite}', [Admin\ProjectInviteController::class, 'destroy'])->name('projects.invites.destroy');
        Route::post('/projects/{project}/study', [Admin\ProjectController::class, 'study'])->name('projects.study');
        Route::get('/projects/{project}/study/{study}', [Admin\ProjectController::class, 'studyStatus'])->name('projects.study.status');
        Route::post('/projects/{project}/study/{study}/cancel', [Admin\ProjectController::class, 'cancelStudy'])->name('projects.study.cancel');

        // Invoices
        Route::get('/invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/create', [Admin\InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [Admin\InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
        Route::patch('/invoices/{invoice}/cancel', [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::patch('/invoices/{invoice}/mark-paid', [Admin\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
        Route::post('/invoices/{invoice}/refund', [Admin\InvoiceController::class, 'refund'])->name('invoices.refund');

        // Custom fields
        Route::post('/custom-fields', [Admin\CustomFieldController::class, 'store'])->name('custom-fields.store');
        Route::post('/custom-fields/reorder', [Admin\CustomFieldController::class, 'reorder'])->name('custom-fields.reorder');
        Route::put('/custom-fields/{customField}', [Admin\CustomFieldController::class, 'update'])->name('custom-fields.update');
        Route::delete('/custom-fields/{customField}', [Admin\CustomFieldController::class, 'destroy'])->name('custom-fields.destroy');

        // API tokens
        Route::post('/api-tokens', [Admin\ApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::delete('/api-tokens/{apiToken}', [Admin\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

        // Settings
        Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

    });

    // Client routes
    Route::middleware('is_active')->group(function () {
        Route::get('/dashboard', [Client\DashboardController::class, 'index'])->name('client.dashboard');

        Route::get('/reports', [ReportController::class, 'index'])->name('client.reports.index');

        Route::get('/projects', [Client\ProjectController::class, 'index'])->name('client.projects.index');
        Route::get('/projects/{project}', [Client\ProjectController::class, 'show'])->name('client.projects.show');

        Route::get('/briefs', [Client\BriefController::class, 'index'])->name('client.briefs.index');
        Route::get('/briefs/{brief}', [Client\BriefController::class, 'show'])->whereUuid('brief')->name('client.briefs.show');
        Route::patch('/briefs/{brief}', [Client\BriefController::class, 'save'])->whereUuid('brief')->name('client.briefs.save');
        Route::post('/briefs/{brief}/submit', [Client\BriefController::class, 'submit'])->whereUuid('brief')->name('client.briefs.submit');

        Route::get('/tasks', [Client\TaskController::class, 'index'])->name('client.tasks.index');
        Route::get('/tasks/create', [Client\TaskController::class, 'create'])->name('client.tasks.create');
        Route::post('/tasks/forms/{formTemplate}', [Client\TaskController::class, 'storeForm'])->name('client.tasks.forms.store');
        Route::post('/tasks', [Client\TaskController::class, 'store'])->name('client.tasks.store');
        Route::get('/tasks/{task}/edit', [Client\TaskController::class, 'edit'])->whereUuid('task')->name('client.tasks.edit');
        Route::put('/tasks/{task}', [Client\TaskController::class, 'update'])->whereUuid('task')->name('client.tasks.update');
        Route::get('/tasks/{task}', [Client\TaskController::class, 'show'])->name('client.tasks.show');
        Route::patch('/tasks/{task}/review', [Client\TaskController::class, 'review'])->name('client.tasks.review');
        Route::post('/tasks/{task}/comments', [Client\TaskCommentController::class, 'store'])->name('client.tasks.comments.store');

        Route::get('/invoices', [Client\InvoiceController::class, 'index'])->name('client.invoices.index');
        Route::get('/invoices/{invoice}', [Client\InvoiceController::class, 'show'])->name('client.invoices.show');
        Route::get('/invoices/{invoice}/pay', [Client\InvoiceController::class, 'pay'])->name('client.invoices.pay');

        Route::get('/profile', [Client\ProfileController::class, 'edit'])->name('client.profile.edit');
        Route::put('/profile', [Client\ProfileController::class, 'update'])->name('client.profile.update');
    });
});

// Reports are shared by both roles; the policy checks the recipient.
Route::middleware(['auth', 'is_active'])->group(function () {
    Route::patch('/sidebar-preferences', [SidebarPreferenceController::class, 'update'])->name('sidebar.update');
    Route::patch('/dashboard-layout', [DashboardLayoutController::class, 'update'])->name('dashboard.layout');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->whereUuid('report')->name('reports.show');
    Route::get('/reports/{report}/screenshot', [ReportController::class, 'screenshot'])->whereUuid('report')->name('reports.screenshot');
    Route::get('/reports/{report}/export/xlsx', [ReportController::class, 'exportExcel'])->whereUuid('report')->name('reports.export.xlsx');
    Route::get('/reports/{report}/export/pdf', [ReportController::class, 'exportPdf'])->whereUuid('report')->name('reports.export.pdf');
});

// Legal pages are public: they are linked from the login screen and the site.
Route::get('/invite/{token}', [InviteController::class, 'show'])->name('invite.show');
Route::post('/invite/{token}', [InviteController::class, 'store'])->middleware('throttle:10,1')->name('invite.store');

Route::get('/legal/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/consent', [LegalController::class, 'consent'])->name('legal.consent');
Route::get('/legal/requisites', [LegalController::class, 'requisites'])->name('legal.requisites');

// Webhook (no auth, verify middleware)
Route::post('/webhooks/yookassa', [YooKassaWebhookController::class, 'handle'])
    ->middleware('yookassa_webhook')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.yookassa');

// Redirect root
Route::get('/', fn () => redirect()->route('login'));
