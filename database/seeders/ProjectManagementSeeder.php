<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\CustomField;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectManagementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'admin')->first();

        if ($admin === null) {
            return;
        }

        $this->seedCustomFields($admin);
        $this->seedTemplates($admin);
    }

    private function seedCustomFields(User $admin): void
    {
        $fields = [
            [
                'entity_type' => CustomFieldEntity::Client,
                'key' => 'intake_status',
                'label' => 'Статус приёма заявок',
                'type' => CustomFieldType::Select,
                'options' => [
                    ['value' => 'open', 'label' => 'Принимаю заявки', 'color' => 'success'],
                    ['value' => 'limited', 'label' => 'Ограниченно', 'color' => 'warning'],
                    ['value' => 'paused', 'label' => 'Пауза', 'color' => 'muted'],
                    ['value' => 'closed', 'label' => 'Закрыт', 'color' => 'danger'],
                ],
                'default_value' => 'open',
                'is_required' => true,
                'show_in_list' => true,
                'help_text' => 'Видно в карточке клиента и в REST API. Удобно для честного статуса загрузки.',
                'sort_order' => 1,
            ],
            [
                'entity_type' => CustomFieldEntity::Client,
                'key' => 'preferred_channel',
                'label' => 'Удобный канал связи',
                'type' => CustomFieldType::Select,
                'options' => [
                    ['value' => 'telegram', 'label' => 'Telegram'],
                    ['value' => 'email', 'label' => 'Email'],
                    ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                    ['value' => 'call', 'label' => 'Звонок'],
                ],
                'is_required' => false,
                'show_in_list' => true,
                'sort_order' => 2,
            ],
            [
                'entity_type' => CustomFieldEntity::Task,
                'key' => 'acceptance_criteria',
                'label' => 'Критерии приёмки',
                'type' => CustomFieldType::Textarea,
                'is_required' => false,
                'show_in_list' => false,
                'help_text' => 'Что должно быть верно, чтобы работа считалась готовой.',
                'sort_order' => 1,
            ],
            [
                'entity_type' => CustomFieldEntity::Invoice,
                'key' => 'payment_purpose',
                'label' => 'Назначение платежа',
                'type' => CustomFieldType::Text,
                'is_required' => false,
                'show_in_list' => false,
                'sort_order' => 1,
            ],
        ];

        foreach ($fields as $field) {
            CustomField::query()->firstOrCreate(
                ['entity_type' => $field['entity_type']->value, 'key' => $field['key']],
                [
                    ...$field,
                    'is_active' => true,
                    'client_editable' => false,
                    'created_by' => $admin->id,
                ],
            );
        }
    }

    private function seedTemplates(User $admin): void
    {
        $templates = [
            [
                'name' => 'Лендинг под ключ',
                'summary' => 'Одностраничный сайт: структура, дизайн, вёрстка, запуск.',
                'description' => '<p>Собираем лендинг под одну услугу или запуск. На старте фиксируем цель, аудиторию и целевое действие, чтобы не спорить о вкусах на финише.</p><h3>Что входит</h3><ul><li>структура и текст по блокам;</li><li>дизайн desktop + mobile;</li><li>вёрстка, формы, аналитика;</li><li>проверка на реальных устройствах и запуск.</li></ul><h3>Что нужно от клиента</h3><ul><li>описание услуги и преимуществ;</li><li>логотип и материалы, если есть;</li><li>доступ к домену и хостингу;</li><li>один ответственный за согласование.</li></ul><p><strong>Готово, когда:</strong> страница открывается на телефоне и десктопе, форма отправляет заявку, аналитика фиксирует визит и целевое действие.</p>',
                'checklist' => ['Бриф: цель, аудитория, целевое действие', 'Структура и тексты', 'Дизайн desktop + mobile', 'Вёрстка и адаптив', 'Формы и аналитика', 'Тест на устройствах и запуск'],
                'default_priority' => TaskPriority::High,
                'default_due_days' => 14,
                'estimated_minutes' => 2400,
            ],
            [
                'name' => 'Правка на сайте',
                'summary' => 'Точечное изменение контента или вёрстки без большого релиза.',
                'description' => '<p>Небольшая правка: текст, изображение, ссылка, отступ или блок. Фиксируем, что именно меняем и где, чтобы не задеть соседние элементы.</p><p>Если правка затрагивает несколько страниц или логику — это уже отдельная задача, обсудим объём.</p>',
                'checklist' => ['Уточнить страницу и элемент', 'Сделать правку на тестовой копии', 'Проверить desktop и mobile', 'Опубликовать и показать результат'],
                'default_priority' => TaskPriority::Medium,
                'default_due_days' => 2,
                'estimated_minutes' => 60,
            ],
            [
                'name' => 'Интеграция по API',
                'summary' => 'Связать сервисы, настроить обмен данными и обработку ошибок.',
                'description' => '<p>Проектируем интеграцию: какие данные, в какую сторону, как часто и что делать при сбое. Сразу договариваемся о тестовом контуре и логах.</p><h3>Результат</h3><ul><li>схема обмена и список endpoints;</li><li>рабочая интеграция с retry и логами;</li><li>инструкция по эксплуатации и токенам.</li></ul>',
                'checklist' => ['Согласовать схему данных и endpoints', 'Получить тестовые доступы', 'Реализовать обмен и обработку ошибок', 'Прогнать сценарии и логи', 'Передать инструкцию'],
                'default_priority' => TaskPriority::High,
                'default_due_days' => 7,
                'estimated_minutes' => 900,
            ],
            [
                'name' => 'Консультация 60 минут',
                'summary' => 'Разбор вопроса, план действий и короткое резюме после встречи.',
                'description' => '<p>Созвон на 60 минут по одному вопросу: архитектура, код, запуск, автоматизация или ИИ. До встречи пришлите контекст и ссылки — так разбор будет предметным.</p><p>После встречи вы получаете короткое резюме с решениями и следующими шагами.</p>',
                'checklist' => ['Получить контекст и вопросы', 'Провести встречу', 'Отправить резюме и следующие шаги'],
                'default_priority' => TaskPriority::Medium,
                'default_due_days' => 1,
                'estimated_minutes' => 60,
            ],
            [
                'name' => 'Аудит проекта',
                'summary' => 'Проверка кода, инфраструктуры и процессов с приоритетами.',
                'description' => '<p>Смотрим проект целиком: код, зависимости, деплой, безопасность, тесты и слабые места. На выходе — отчёт с приоритетами и оценкой рисков, без «переписать всё».</p>',
                'checklist' => ['Собрать доступы и контекст', 'Проверить код и зависимости', 'Проверить деплой и инфраструктуру', 'Собрать отчёт с приоритетами', 'Обсудить план работ'],
                'default_priority' => TaskPriority::Medium,
                'default_due_days' => 7,
                'estimated_minutes' => 600,
            ],
        ];

        foreach ($templates as $template) {
            TaskTemplate::query()->firstOrCreate(
                ['name' => $template['name']],
                [
                    ...$template,
                    'checklist' => array_map(fn (string $text) => ['text' => $text, 'done' => false], $template['checklist']),
                    'default_type' => TaskType::OneTime,
                    'default_status' => TaskStatus::PendingReview,
                    'is_active' => true,
                    'is_shared' => true,
                    'created_by' => $admin->id,
                ],
            );
        }
    }
}
