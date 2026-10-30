<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FormTemplate;
use Illuminate\Database\Seeder;

class FormTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            FormTemplate::query()->updateOrCreate(
                ['key' => $template['key']],
                [...$template, 'is_active' => true],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'key' => 'bug-report',
                'name' => 'Баг-репорт',
                'summary' => 'Что сломалось, как повторить и насколько это критично',
                'description' => 'Техническая заявка с обязательными шагами воспроизведения и скриншотами.',
                'icon' => 'BugAntIcon',
                'tone' => 'peach',
                'category' => 'Разработка',
                'sort_order' => 10,
                'schema' => [
                    'fields' => [
                        ['key' => 'severity', 'label' => 'Критичность', 'type' => 'select', 'required' => true, 'options' => [
                            ['value' => 'low', 'label' => 'Низкая'],
                            ['value' => 'medium', 'label' => 'Средняя'],
                            ['value' => 'high', 'label' => 'Высокая'],
                            ['value' => 'blocker', 'label' => 'Блокер'],
                        ]],
                        ['key' => 'what_happened', 'label' => 'Что случилось', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Коротко опишите проблему…', 'help' => 'Без оценки и догадок — только факты.'],
                        ['key' => 'expected', 'label' => 'Ожидалось', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Как должно было работать?'],
                        ['key' => 'actual', 'label' => 'Получилось', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Что произошло на самом деле?'],
                        ['key' => 'environment', 'label' => 'Где воспроизводится', 'type' => 'text', 'required' => false, 'placeholder' => 'Браузер, устройство, URL'],
                    ],
                    'groups' => [[
                        'key' => 'steps', 'label' => 'Как воспроизвести', 'repeatable' => true, 'min' => 1, 'max' => 10,
                        'add_label' => 'Добавить шаг', 'help' => 'Можно добавить сколько угодно шагов.',
                        'fields' => [
                            ['key' => 'step', 'label' => 'Шаг', 'type' => 'text', 'required' => true, 'placeholder' => 'Например: открыть страницу авторизации'],
                            ['key' => 'screenshot', 'label' => 'Скриншот', 'type' => 'image', 'required' => false],
                        ],
                    ]],
                ],
            ],
            [
                'key' => 'website-brief',
                'name' => 'Бриф на сайт',
                'summary' => 'Цель, аудитория, страницы и референсы для нового сайта',
                'description' => 'Структурированный бриф для лендинга, корпоративного сайта или отдельной страницы.',
                'icon' => 'GlobeAltIcon',
                'tone' => 'violet',
                'category' => 'Разработка',
                'sort_order' => 20,
                'schema' => [
                    'fields' => [
                        ['key' => 'goal', 'label' => 'Цель сайта', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Что должен сделать посетитель?'],
                        ['key' => 'audience', 'label' => 'Аудитория', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Кто эти люди и что для них важно?'],
                        ['key' => 'budget', 'label' => 'Бюджет', 'type' => 'select', 'required' => false, 'options' => [
                            ['value' => 'small', 'label' => 'До 50 000 ₽'],
                            ['value' => 'medium', 'label' => '50–150 000 ₽'],
                            ['value' => 'large', 'label' => '150 000 ₽+'],
                            ['value' => 'unknown', 'label' => 'Пока не знаю'],
                        ]],
                        ['key' => 'must_have', 'label' => 'Обязательно на сайте', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Формы, каталог, оплата, интеграции…'],
                    ],
                    'groups' => [
                        [
                            'key' => 'pages', 'label' => 'Страницы', 'repeatable' => true, 'min' => 1, 'max' => 20,
                            'add_label' => 'Добавить страницу', 'fields' => [
                                ['key' => 'title', 'label' => 'Название страницы', 'type' => 'text', 'required' => true],
                                ['key' => 'purpose', 'label' => 'Задача страницы', 'type' => 'textarea', 'required' => true],
                                ['key' => 'reference', 'label' => 'Референс', 'type' => 'url', 'required' => false],
                            ],
                        ],
                        [
                            'key' => 'references', 'label' => 'Референсы', 'repeatable' => true, 'min' => 0, 'max' => 10,
                            'add_label' => 'Добавить референс', 'fields' => [
                                ['key' => 'url', 'label' => 'Ссылка', 'type' => 'url', 'required' => true],
                                ['key' => 'comment', 'label' => 'Что нравится', 'type' => 'textarea', 'required' => false],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'content-brief',
                'name' => 'Контент-бриф',
                'summary' => 'Тема, тезисы, структура и источники для статьи или рассылки',
                'description' => 'Помогает собрать материал до начала текста и не переписывать его трижды.',
                'icon' => 'PencilSquareIcon',
                'tone' => 'sage',
                'category' => 'Контент',
                'sort_order' => 30,
                'schema' => [
                    'fields' => [
                        ['key' => 'topic', 'label' => 'Тема', 'type' => 'text', 'required' => true, 'placeholder' => 'О чём текст?'],
                        ['key' => 'audience', 'label' => 'Для кого', 'type' => 'textarea', 'required' => true],
                        ['key' => 'key_message', 'label' => 'Главный тезис', 'type' => 'textarea', 'required' => true, 'help' => 'Одна мысль, которую читатель должен запомнить.'],
                        ['key' => 'tone', 'label' => 'Тон', 'type' => 'select', 'required' => true, 'options' => [
                            ['value' => 'expert', 'label' => 'Экспертный'],
                            ['value' => 'friendly', 'label' => 'Дружелюбный'],
                            ['value' => 'bold', 'label' => 'Смелый'],
                            ['value' => 'neutral', 'label' => 'Нейтральный'],
                        ]],
                        ['key' => 'cta', 'label' => 'Призыв к действию', 'type' => 'text', 'required' => false],
                    ],
                    'groups' => [
                        [
                            'key' => 'outline', 'label' => 'Структура', 'repeatable' => true, 'min' => 2, 'max' => 20,
                            'add_label' => 'Добавить раздел', 'fields' => [
                                ['key' => 'section', 'label' => 'Раздел', 'type' => 'text', 'required' => true],
                                ['key' => 'description', 'label' => 'О чём раздел', 'type' => 'textarea', 'required' => true],
                            ],
                        ],
                        [
                            'key' => 'sources', 'label' => 'Источники', 'repeatable' => true, 'min' => 0, 'max' => 15,
                            'add_label' => 'Добавить источник', 'fields' => [
                                ['key' => 'url', 'label' => 'Ссылка', 'type' => 'url', 'required' => true],
                                ['key' => 'comment', 'label' => 'Комментарий', 'type' => 'text', 'required' => false],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'design-review',
                'name' => 'Дизайн-ревью',
                'summary' => 'Проверка макета по экранам, элементам и приоритету',
                'description' => 'Запрос обратной связи по дизайну с привязкой к экранам и скриншотам.',
                'icon' => 'EyeIcon',
                'tone' => 'sky',
                'category' => 'Дизайн',
                'sort_order' => 40,
                'schema' => [
                    'fields' => [
                        ['key' => 'url', 'label' => 'Ссылка на макет', 'type' => 'url', 'required' => true, 'placeholder' => 'https://figma.com/…'],
                        ['key' => 'goal', 'label' => 'Что проверить', 'type' => 'textarea', 'required' => true],
                        ['key' => 'priority', 'label' => 'Приоритет', 'type' => 'select', 'required' => true, 'options' => [
                            ['value' => 'low', 'label' => 'Когда будет время'],
                            ['value' => 'medium', 'label' => 'На этой неделе'],
                            ['value' => 'high', 'label' => 'Срочно'],
                        ]],
                    ],
                    'groups' => [[
                        'key' => 'areas', 'label' => 'Экраны и элементы', 'repeatable' => true, 'min' => 1, 'max' => 20,
                        'add_label' => 'Добавить экран', 'fields' => [
                            ['key' => 'screen', 'label' => 'Экран / элемент', 'type' => 'text', 'required' => true],
                            ['key' => 'comment', 'label' => 'Что не так', 'type' => 'textarea', 'required' => true],
                            ['key' => 'screenshot', 'label' => 'Скриншот', 'type' => 'image', 'required' => false],
                        ],
                    ]],
                ],
            ],
            [
                'key' => 'access-request',
                'name' => 'Доступы',
                'summary' => 'Сервис, аккаунт, роль и срок доступа',
                'description' => 'Заявка на выдачу доступов без переписки «дайте доступ, пожалуйста».',
                'icon' => 'KeyIcon',
                'tone' => 'peach',
                'category' => 'Доступы',
                'sort_order' => 50,
                'schema' => [
                    'fields' => [
                        ['key' => 'service', 'label' => 'Сервис', 'type' => 'text', 'required' => true, 'placeholder' => 'Например: Google Analytics'],
                        ['key' => 'account', 'label' => 'Аккаунт / email', 'type' => 'email', 'required' => true],
                        ['key' => 'role', 'label' => 'Роль', 'type' => 'select', 'required' => true, 'options' => [
                            ['value' => 'viewer', 'label' => 'Только просмотр'],
                            ['value' => 'editor', 'label' => 'Редактирование'],
                            ['value' => 'admin', 'label' => 'Администратор'],
                        ]],
                        ['key' => 'reason', 'label' => 'Зачем нужен доступ', 'type' => 'textarea', 'required' => true],
                        ['key' => 'duration', 'label' => 'На какой срок', 'type' => 'select', 'required' => true, 'options' => [
                            ['value' => 'once', 'label' => 'Разово'],
                            ['value' => 'month', 'label' => 'Месяц'],
                            ['value' => 'permanent', 'label' => 'Постоянно'],
                        ]],
                        ['key' => 'confirmation', 'label' => 'Подтверждаю, что доступ нужен для работы', 'type' => 'checkbox', 'required' => true],
                    ],
                    'groups' => [],
                ],
            ],
            [
                'key' => 'integration-request',
                'name' => 'Интеграция',
                'summary' => 'Системы, данные и правила синхронизации',
                'description' => 'Техническое задание на интеграцию: что с чем соединяем и какие данные передаём.',
                'icon' => 'ArrowsRightLeftIcon',
                'tone' => 'violet',
                'category' => 'Разработка',
                'sort_order' => 60,
                'schema' => [
                    'fields' => [
                        ['key' => 'goal', 'label' => 'Что должно получиться', 'type' => 'textarea', 'required' => true],
                        ['key' => 'frequency', 'label' => 'Частота обмена', 'type' => 'select', 'required' => true, 'options' => [
                            ['value' => 'realtime', 'label' => 'В реальном времени'],
                            ['value' => 'hourly', 'label' => 'Каждый час'],
                            ['value' => 'daily', 'label' => 'Раз в день'],
                            ['value' => 'manual', 'label' => 'Вручную'],
                        ]],
                        ['key' => 'constraints', 'label' => 'Ограничения', 'type' => 'textarea', 'required' => false],
                    ],
                    'groups' => [
                        [
                            'key' => 'systems', 'label' => 'Системы', 'repeatable' => true, 'min' => 2, 'max' => 10,
                            'add_label' => 'Добавить систему', 'fields' => [
                                ['key' => 'name', 'label' => 'Система', 'type' => 'text', 'required' => true],
                                ['key' => 'contact', 'label' => 'Ответственный', 'type' => 'text', 'required' => false],
                            ],
                        ],
                        [
                            'key' => 'fields', 'label' => 'Поля для синхронизации', 'repeatable' => true, 'min' => 1, 'max' => 30,
                            'add_label' => 'Добавить поле', 'fields' => [
                                ['key' => 'source', 'label' => 'Откуда', 'type' => 'text', 'required' => true],
                                ['key' => 'target', 'label' => 'Куда', 'type' => 'text', 'required' => true],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'task-description-images',
                'name' => 'Описание задачи + изображения',
                'summary' => 'Повторяемые блоки «описание + изображение»',
                'description' => 'Для заявок, где важно собрать несколько связанных описаний и скриншотов.',
                'icon' => 'QueueListIcon',
                'tone' => 'sage',
                'category' => 'Универсальные',
                'sort_order' => 70,
                'schema' => [
                    'fields' => [
                        ['key' => 'task_title', 'label' => 'Короткое название', 'type' => 'text', 'required' => true],
                        ['key' => 'context', 'label' => 'Общий контекст', 'type' => 'textarea', 'required' => false],
                    ],
                    'groups' => [[
                        'key' => 'items', 'label' => 'Описание и изображение', 'repeatable' => true, 'min' => 1, 'max' => 15,
                        'add_label' => 'Добавить описание', 'fields' => [
                            ['key' => 'description', 'label' => 'Описание задачи', 'type' => 'textarea', 'required' => true],
                            ['key' => 'image', 'label' => 'Изображение', 'type' => 'image', 'required' => false],
                        ],
                    ]],
                ],
            ],
            [
                'key' => 'feature-request',
                'name' => 'Фича-реквест',
                'summary' => 'Проблема, пользователи, решение и метрика успеха',
                'description' => 'Помогает не начинать с готового решения, а сначала описать проблему.',
                'icon' => 'LightBulbIcon',
                'tone' => 'sky',
                'category' => 'Продукт',
                'sort_order' => 80,
                'schema' => [
                    'fields' => [
                        ['key' => 'problem', 'label' => 'Проблема', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Что не получается и у кого?'],
                        ['key' => 'audience', 'label' => 'Кто сталкивается', 'type' => 'textarea', 'required' => true],
                        ['key' => 'solution', 'label' => 'Предлагаемое решение', 'type' => 'textarea', 'required' => false],
                        ['key' => 'alternatives', 'label' => 'Что пробовали', 'type' => 'textarea', 'required' => false],
                        ['key' => 'success_metric', 'label' => 'Как поймём, что получилось', 'type' => 'text', 'required' => true],
                        ['key' => 'priority', 'label' => 'Приоритет', 'type' => 'select', 'required' => true, 'options' => [
                            ['value' => 'low', 'label' => 'Низкий'],
                            ['value' => 'medium', 'label' => 'Средний'],
                            ['value' => 'high', 'label' => 'Высокий'],
                        ]],
                    ],
                    'groups' => [],
                ],
            ],
        ];
    }
}
