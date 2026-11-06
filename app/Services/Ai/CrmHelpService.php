<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\User;

/**
 * Role-aware map of the CRM interface.
 *
 * This is deliberately a small, versioned in-process catalogue rather than a
 * separate search service: the product has one UI, the catalogue changes with
 * the code, and the assistant needs stable links more than fuzzy retrieval.
 * `search()` is the tool contract; `systemMap()` is the always-on fallback.
 */
class CrmHelpService
{
    public const VERSION = '2026-11-06';

    /**
     * @return list<array{
     *     id: string, roles: list<string>, section: string, title: string,
     *     path: string, summary: string, steps: list<string>,
     *     keywords: list<string>
     * }>
     */
    public function catalog(): array
    {
        return [
            [
                'id' => 'admin.dashboard',
                'roles' => ['admin'],
                'section' => 'Обзор',
                'title' => 'Обзор (дашборд)',
                'path' => '/admin/dashboard',
                'summary' => 'Сводка по задачам, счетам, клиентам и доходу; отсюда начинается рабочий день.',
                'steps' => [
                    'Откройте «Обзор» в левом меню.',
                    'Проверьте карточки: активные задачи, просрочки, счета, доход.',
                    'Нажмите на задачу или счёт, чтобы перейти к деталям.',
                ],
                'keywords' => ['обзор', 'дашборд', 'сводка', 'доход', 'просрочки', 'главная', 'dashboard'],
            ],
            [
                'id' => 'admin.tasks.index',
                'roles' => ['admin'],
                'section' => 'Задачи',
                'title' => 'Все задачи',
                'path' => '/admin/tasks',
                'summary' => 'Список задач с фильтрами по статусу, проекту, клиенту и исполнителю.',
                'steps' => [
                    'Откройте «Задачи».',
                    'Выберите фильтр или введите запрос в поиск.',
                    'Нажмите на задачу, чтобы открыть карточку и комментарии.',
                ],
                'keywords' => ['задачи', 'список задач', 'фильтр', 'поиск', 'статус', 'tasks'],
            ],
            [
                'id' => 'admin.tasks.create',
                'roles' => ['admin'],
                'section' => 'Задачи',
                'title' => 'Создать задачу',
                'path' => '/admin/tasks/create',
                'summary' => 'Новая задача для клиента: название, описание, проект, приоритет и срок.',
                'steps' => [
                    'Нажмите «Новая задача» на странице задач.',
                    'Выберите клиента и проект, заполните название и описание.',
                    'Поставьте приоритет и срок, затем сохраните.',
                ],
                'keywords' => ['создать задачу', 'новая задача', 'заявка', 'добавить задачу', 'task create'],
            ],
            [
                'id' => 'admin.tasks.show',
                'roles' => ['admin'],
                'section' => 'Задачи',
                'title' => 'Карточка задачи: статус, комментарии, отчёты',
                'path' => '/admin/tasks',
                'summary' => 'В задаче меняются статус и исполнитель, ведутся комментарии, чек-лист и отчёты для клиента.',
                'steps' => [
                    'Откройте задачу из списка.',
                    'Справа или в шапке смените статус; вкладка комментариев — для переписки.',
                    'Чек-лист и отчёт — в блоке выполнения задачи.',
                ],
                'keywords' => ['статус задачи', 'комментарий', 'чек-лист', 'отчёт по задаче', 'исполнитель', 'закрыть задачу'],
            ],
            [
                'id' => 'admin.projects.index',
                'roles' => ['admin'],
                'section' => 'Проекты',
                'title' => 'Проекты',
                'path' => '/admin/projects',
                'summary' => 'Все проекты с клиентом, прогрессом, статусом и ссылкой на сайт.',
                'steps' => [
                    'Откройте «Проекты».',
                    'Используйте фильтры по статусу и клиенту.',
                    'Нажмите на проект, чтобы открыть карточку и доступы.',
                ],
                'keywords' => ['проекты', 'проект', 'портфель', 'фильтр проектов', 'projects'],
            ],
            [
                'id' => 'admin.projects.show',
                'roles' => ['admin'],
                'section' => 'Проекты',
                'title' => 'Карточка проекта: доступ, участники и приглашения',
                'path' => '/admin/projects',
                'summary' => 'В проекте настраиваются участники, роли, приглашения по ссылке, контекст и изучение сайта.',
                'steps' => [
                    'Откройте проект из списка.',
                    'Блок «Доступ к проекту» — добавить участника и сменить роль.',
                    'Блок «Приглашения по ссылке» — создать одноразовую ссылку с ролью и сроком.',
                    'Ниже — контекст проекта, AGENTS.md и изучение сайта.',
                ],
                'keywords' => ['участники проекта', 'доступ к проекту', 'приглашение', 'инвайт', 'роль', 'наблюдатель', 'менеджер', 'project access'],
            ],
            [
                'id' => 'admin.projects.create',
                'roles' => ['admin'],
                'section' => 'Проекты',
                'title' => 'Создать или изменить проект',
                'path' => '/admin/projects/create',
                'summary' => 'Новый проект: ключ, название, клиент, сайт, статус, сроки и инструкции.',
                'steps' => [
                    'Нажмите «Новый проект».',
                    'Заполните ключ (например, KOR), название и клиента.',
                    'Добавьте сайт и инструкции; сохраните.',
                ],
                'keywords' => ['создать проект', 'новый проект', 'ключ проекта', 'проект создать', 'project create'],
            ],
            [
                'id' => 'admin.clients.index',
                'roles' => ['admin'],
                'section' => 'Клиенты',
                'title' => 'Клиенты',
                'path' => '/admin/clients',
                'summary' => 'Список клиентских аккаунтов с контактами, статусом и количеством задач.',
                'steps' => [
                    'Откройте «Клиенты».',
                    'Найдите клиента поиском или фильтром.',
                    'Нажмите на карточку, чтобы увидеть задачи, счета и поля.',
                ],
                'keywords' => ['клиенты', 'клиент', 'контакты', 'аккаунт клиента', 'clients'],
            ],
            [
                'id' => 'admin.clients.create',
                'roles' => ['admin'],
                'section' => 'Клиенты',
                'title' => 'Создать клиента',
                'path' => '/admin/clients/create',
                'summary' => 'Новый клиентский аккаунт: имя, e-mail, телефон и пароль для первого входа.',
                'steps' => [
                    'Нажмите «Новый клиент».',
                    'Заполните имя, e-mail и пароль; подтвердите ознакомление клиента с политикой.',
                    'Сохраните и передайте клиенту доступ или приглашение в проект.',
                ],
                'keywords' => ['создать клиента', 'новый клиент', 'аккаунт', 'пароль клиента', 'client create'],
            ],
            [
                'id' => 'admin.clients.show',
                'roles' => ['admin'],
                'section' => 'Клиенты',
                'title' => 'Карточка клиента: задачи, счета, поля',
                'path' => '/admin/clients',
                'summary' => 'История клиента, его задачи и счета, пользовательские поля и деактивация.',
                'steps' => [
                    'Откройте клиента из списка.',
                    'Проверьте блоки задач и счетов.',
                    'Пользовательские поля и статус — в карточке клиента.',
                ],
                'keywords' => ['карточка клиента', 'поля клиента', 'деактивировать', 'история клиента', 'client show'],
            ],
            [
                'id' => 'admin.invoices.index',
                'roles' => ['admin'],
                'section' => 'Счета',
                'title' => 'Счета',
                'path' => '/admin/invoices',
                'summary' => 'Все счета: статус оплаты, суммы, клиент и задача-основание.',
                'steps' => [
                    'Откройте «Счета».',
                    'Отфильтруйте по статусу: неоплаченные, оплаченные, отменённые.',
                    'Нажмите на счёт, чтобы открыть детали и действия.',
                ],
                'keywords' => ['счета', 'счёт', 'оплата', 'неоплаченные', 'статус счёта', 'invoices'],
            ],
            [
                'id' => 'admin.invoices.create',
                'roles' => ['admin'],
                'section' => 'Счета',
                'title' => 'Создать счёт',
                'path' => '/admin/invoices/create',
                'summary' => 'Счёт по задаче: сумма, назначение, способ оплаты и рекуррентность.',
                'steps' => [
                    'Нажмите «Новый счёт».',
                    'Выберите задачу и заполните сумму и назначение.',
                    'Выберите ЮKassa или прямой перевод; при необходимости включите рекуррентность.',
                ],
                'keywords' => ['создать счёт', 'выставить счёт', 'новая оплата', 'юкасса', 'перевод', 'invoice create'],
            ],
            [
                'id' => 'admin.invoices.show',
                'roles' => ['admin'],
                'section' => 'Счета',
                'title' => 'Карточка счёта: оплата, ссылка, возврат',
                'path' => '/admin/invoices',
                'summary' => 'Детали счёта, ссылка на оплату, отметка оплаты и возврат.',
                'steps' => [
                    'Откройте счёт из списка.',
                    'Скопируйте ссылку на оплату или отправьте клиенту.',
                    'После поступления отметьте оплату; возврат — в действиях счёта.',
                ],
                'keywords' => ['ссылка на оплату', 'отметить оплату', 'возврат', 'счёт оплачен', 'payment link'],
            ],
            [
                'id' => 'admin.reports.index',
                'roles' => ['admin'],
                'section' => 'Отчёты',
                'title' => 'Отчёты',
                'path' => '/admin/reports',
                'summary' => 'Отчёты клиентам: заголовок, группы работ, тестовые ссылки и скриншоты.',
                'steps' => [
                    'Откройте «Отчёты».',
                    'Нажмите на карточку, чтобы открыть полный отчёт.',
                    'Новый отчёт — кнопка «Новый отчёт».',
                ],
                'keywords' => ['отчёты', 'отчёт', 'клиенту', 'скриншот', 'тестовая ссылка', 'reports'],
            ],
            [
                'id' => 'admin.reports.create',
                'roles' => ['admin'],
                'section' => 'Отчёты',
                'title' => 'Создать отчёт',
                'path' => '/admin/reports/create',
                'summary' => 'Отчёт получателю: необязательный заголовок и повторяемые группы «объяснение → ссылка → скриншот».',
                'steps' => [
                    'Нажмите «Новый отчёт».',
                    'Укажите получателя и заголовок (можно оставить пустым).',
                    'Добавьте одну или несколько групп и отправьте.',
                ],
                'keywords' => ['создать отчёт', 'новый отчёт', 'группа отчёта', 'скриншот', 'report create'],
            ],
            [
                'id' => 'admin.library',
                'roles' => ['admin'],
                'section' => 'Библиотека',
                'title' => 'Библиотека: формы заявок и шаблоны задач',
                'path' => '/admin/forms',
                'summary' => 'Формы заявок для клиентов и шаблоны типовых задач; можно включать и редактировать.',
                'steps' => [
                    'Откройте «Библиотеку».',
                    'Вкладка «Формы» — структурированные заявки; «Шаблоны» — типовые задачи.',
                    'Включите нужное и отредактируйте поля или чек-лист.',
                ],
                'keywords' => ['библиотека', 'формы', 'шаблоны', 'шаблон задачи', 'форма заявки', 'library'],
            ],
            [
                'id' => 'admin.settings',
                'roles' => ['admin'],
                'section' => 'Настройки',
                'title' => 'Настройки',
                'path' => '/admin/settings',
                'summary' => 'Оплата, пользовательские поля, API и MCP, реквизиты и параметры ассистента.',
                'steps' => [
                    'Откройте «Настройки» внизу меню.',
                    'Выберите вкладку: «Оплата», «Поля» или «API и MCP».',
                    'Сохраните изменения; секреты маскируются.',
                ],
                'keywords' => ['настройки', 'оплата', 'реквизиты', 'поля', 'api', 'mcp', 'settings'],
            ],
            [
                'id' => 'admin.assistant',
                'roles' => ['admin', 'client'],
                'section' => 'Кики',
                'title' => 'Кики — ассистент CRM',
                'path' => '/assistant',
                'summary' => 'Чат по данным CRM и интерфейсу: находит разделы, объясняет шаги, готовит задачи и счета.',
                'steps' => [
                    'Откройте «Кики» в меню или нажмите на аватар.',
                    'Спросите словами: «где счета», «как пригласить в проект», «что с моими задачами».',
                    'Кики даст ссылку на раздел и шаги; действия с деньгами подтверждаются отдельно.',
                ],
                'keywords' => ['кики', 'ассистент', 'помощь', 'чат', 'подсказка', 'assistant'],
            ],
            [
                'id' => 'admin.help',
                'roles' => ['admin', 'client'],
                'section' => 'Помощь',
                'title' => 'Помощь по CRM',
                'path' => '/help',
                'summary' => 'Каталог всех разделов CRM с инструкциями и ссылками; доступен и Кики.',
                'steps' => [
                    'Откройте «Помощь» в меню.',
                    'Найдите раздел поиском или по группе.',
                    'Нажмите «Открыть раздел» или спросите Кики.',
                ],
                'keywords' => ['помощь', 'инструкция', 'как пользоваться', 'разделы', 'help', 'guide'],
            ],
            [
                'id' => 'client.dashboard',
                'roles' => ['client'],
                'section' => 'Обзор',
                'title' => 'Личный кабинет',
                'path' => '/dashboard',
                'summary' => 'Ваши задачи, счета и ближайшие сроки; отсюда создаётся новая заявка.',
                'steps' => [
                    'Откройте «Обзор».',
                    'Проверьте активные задачи и счета к оплате.',
                    'Нажмите «Новая заявка» или спросите Кики.',
                ],
                'keywords' => ['обзор', 'кабинет', 'дашборд', 'мои задачи', 'счета', 'dashboard'],
            ],
            [
                'id' => 'client.tasks.index',
                'roles' => ['client'],
                'section' => 'Задачи',
                'title' => 'Мои задачи',
                'path' => '/tasks',
                'summary' => 'Все ваши заявки и задачи со статусами и сроками.',
                'steps' => [
                    'Откройте «Задачи».',
                    'Найдите нужную по названию или статусу.',
                    'Нажмите на задачу, чтобы открыть переписку и детали.',
                ],
                'keywords' => ['мои задачи', 'задачи', 'заявки', 'статус', 'tasks'],
            ],
            [
                'id' => 'client.tasks.create',
                'roles' => ['client'],
                'section' => 'Задачи',
                'title' => 'Создать заявку',
                'path' => '/tasks/create',
                'summary' => 'Новая заявка: форма под ситуацию, свободное описание или готовый сценарий.',
                'steps' => [
                    'Нажмите «Новая заявка».',
                    'Выберите форму (баг, сайт, контент) или «Свободная заявка».',
                    'Заполните поля, подтвердите согласие и отправьте.',
                ],
                'keywords' => ['создать заявку', 'новая заявка', 'форма', 'баг', 'сценарий', 'task create'],
            ],
            [
                'id' => 'client.tasks.show',
                'roles' => ['client'],
                'section' => 'Задачи',
                'title' => 'Карточка задачи: переписка и приёмка',
                'path' => '/tasks',
                'summary' => 'Детали задачи, комментарии, вложения и кнопки принять работу или вернуть на доработку.',
                'steps' => [
                    'Откройте задачу из списка.',
                    'Прочитайте описание и комментарии; при необходимости приложите файл.',
                    'Когда работа готова — примите её или верните с комментарием.',
                ],
                'keywords' => ['принять работу', 'вернуть на доработку', 'комментарий', 'вложение', 'задача детали'],
            ],
            [
                'id' => 'client.projects.index',
                'roles' => ['client'],
                'section' => 'Проекты',
                'title' => 'Мои проекты',
                'path' => '/projects',
                'summary' => 'Проекты, в которых вы участвуете, с прогрессом и задачами.',
                'steps' => [
                    'Откройте «Проекты».',
                    'Выберите проект.',
                    'Внутри — задачи, контекст и материалы проекта.',
                ],
                'keywords' => ['проекты', 'мой проект', 'участие', 'progress', 'projects'],
            ],
            [
                'id' => 'client.projects.show',
                'roles' => ['client'],
                'section' => 'Проекты',
                'title' => 'Карточка проекта',
                'path' => '/projects',
                'summary' => 'Контекст проекта, его задачи и материалы; доступ зависит от роли в проекте.',
                'steps' => [
                    'Откройте проект из списка.',
                    'Посмотрите описание и задачи.',
                    'Если чего-то не хватает — создайте заявку или спросите Кики.',
                ],
                'keywords' => ['карточка проекта', 'контекст проекта', 'проект детали', 'project show'],
            ],
            [
                'id' => 'client.invoices.index',
                'roles' => ['client'],
                'section' => 'Счета',
                'title' => 'Мои счета',
                'path' => '/invoices',
                'summary' => 'Счета к оплате и история платежей.',
                'steps' => [
                    'Откройте «Счета».',
                    'Выберите неоплаченный счёт.',
                    'Перейдите к оплате или скачайте счёт.',
                ],
                'keywords' => ['счета', 'оплатить', 'неоплаченный', 'платёж', 'invoices'],
            ],
            [
                'id' => 'client.invoices.show',
                'roles' => ['client'],
                'section' => 'Счета',
                'title' => 'Счёт и оплата',
                'path' => '/invoices',
                'summary' => 'Детали счёта, назначение платежа и кнопка оплаты.',
                'steps' => [
                    'Откройте счёт из списка.',
                    'Проверьте сумму и назначение.',
                    'Нажмите «Оплатить» — откроется ЮKassa; по переводу покажут реквизиты.',
                ],
                'keywords' => ['оплатить счёт', 'юкасса', 'реквизиты', 'счёт детали', 'payment'],
            ],
            [
                'id' => 'client.reports.index',
                'roles' => ['client'],
                'section' => 'Отчёты',
                'title' => 'Отчёты',
                'path' => '/reports',
                'summary' => 'Отчёты по выполненным работам с тестовыми ссылками и скриншотами.',
                'steps' => [
                    'Откройте «Отчёты».',
                    'Выберите отчёт.',
                    'На странице — все группы, ссылки и скриншоты.',
                ],
                'keywords' => ['отчёты', 'отчёт', 'результат', 'скриншот', 'reports'],
            ],
            [
                'id' => 'client.profile',
                'roles' => ['client'],
                'section' => 'Аккаунт',
                'title' => 'Профиль',
                'path' => '/profile',
                'summary' => 'Имя, телефон, мессенджеры и смена пароля.',
                'steps' => [
                    'Откройте «Профиль» внизу меню.',
                    'Обновите контакты.',
                    'Пароль меняется в отдельном блоке.',
                ],
                'keywords' => ['профиль', 'пароль', 'телефон', 'контакты', 'profile'],
            ],
            [
                'id' => 'legal.privacy',
                'roles' => ['admin', 'client'],
                'section' => 'Помощь',
                'title' => 'Политика обработки персональных данных',
                'path' => '/legal/privacy',
                'summary' => 'Какие данные CRM обрабатывает, зачем, кому передаёт и сколько хранит.',
                'steps' => [
                    'Откройте политику по ссылке.',
                    'Раздел 2 — данные и основания; раздел 5 — ваши права.',
                ],
                'keywords' => ['политика', 'персональные данные', 'конфиденциальность', 'согласие', 'privacy'],
            ],
            [
                'id' => 'legal.consent',
                'roles' => ['admin', 'client'],
                'section' => 'Помощь',
                'title' => 'Согласие на обработку данных',
                'path' => '/legal/consent',
                'summary' => 'Текст согласия и порядок отзыва.',
                'steps' => [
                    'Откройте согласие по ссылке.',
                    'Отзыв — письмом на contact@inteli-dev.ru.',
                ],
                'keywords' => ['согласие', 'отзыв согласия', 'персональные данные', 'consent'],
            ],
        ];
    }

    /**
     * @return list<array{id: string, section: string, title: string, path: string, summary: string, steps: list<string>, keywords: list<string>}>
     */
    public function forUser(User $user): array
    {
        return array_values(array_map(
            fn (array $entry): array => [
                'id' => $entry['id'],
                'section' => $entry['section'],
                'title' => $entry['title'],
                'path' => $entry['path'],
                'summary' => $entry['summary'],
                'steps' => $entry['steps'],
                'keywords' => $entry['keywords'],
            ],
            array_filter(
                $this->catalog(),
                fn (array $entry): bool => in_array($user->role->value, $entry['roles'], true),
            ),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(User $user, string $query, ?string $page = null, int $limit = 5): array
    {
        $tokens = $this->tokens($query);
        $pageId = $this->pageId($page);
        $scored = [];

        foreach ($this->catalog() as $entry) {
            if (! in_array($user->role->value, $entry['roles'], true)) {
                continue;
            }

            $score = 0;

            if ($pageId !== null && $entry['id'] === $pageId) {
                $score += 20;
            }

            foreach ($tokens as $token) {
                if (str_contains($this->normalize($entry['title']), $token)) {
                    $score += 9;
                }
                foreach ($entry['keywords'] as $keyword) {
                    if (str_contains($this->normalize($keyword), $token) || str_contains($token, $this->normalize($keyword))) {
                        $score += 6;
                        break;
                    }
                }
                if (str_contains($this->normalize($entry['summary']), $token)) {
                    $score += 3;
                }
                if (str_contains($this->normalize(implode(' ', $entry['steps'])), $token)) {
                    $score += 1;
                }
            }

            if ($score === 0 && $tokens !== []) {
                continue;
            }

            $scored[] = [
                'score' => $score,
                'entry' => $entry,
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(
            fn (array $item): array => [
                'id' => $item['entry']['id'],
                'section' => $item['entry']['section'],
                'title' => $item['entry']['title'],
                'url' => $item['entry']['path'],
                'summary' => $item['entry']['summary'],
                'steps' => $item['entry']['steps'],
            ],
            array_slice($scored, 0, max(1, min($limit, 8))),
        );
    }

    public function current(User $user, ?array $context): ?array
    {
        $pageId = $this->pageId($context['page'] ?? null) ?? $this->pageIdFromUrl($context['url'] ?? null);

        if ($pageId === null) {
            return null;
        }

        foreach ($this->forUser($user) as $entry) {
            if ($entry['id'] === $pageId) {
                return $entry;
            }
        }

        return null;
    }

    public function systemMap(User $user, ?array $context = null): string
    {
        $lines = ['Карта интерфейса CRM (версия '.self::VERSION.'):'];

        foreach ($this->forUser($user) as $entry) {
            $lines[] = '- '.$entry['section'].' → '.$entry['title'].' (`'.$entry['path'].'`): '.$entry['summary'];
        }

        if (($current = $this->current($user, $context)) !== null) {
            $lines[] = '';
            $lines[] = 'Пользователь сейчас на странице «'.$current['title'].'» ('.$current['path'].').';
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    public function promptSuggestions(User $user): array
    {
        return $user->isAdmin()
            ? [
                'Где создать задачу?',
                'Как пригласить клиента в проект?',
                'Где выставить счёт?',
                'Как отправить отчёт клиенту?',
            ]
            : [
                'Как создать заявку?',
                'Где посмотреть мои счета?',
                'Как принять работу?',
                'Где мои отчёты?',
            ];
    }

    /**
     * @return list<string>
     */
    private function tokens(string $query): array
    {
        $normalized = $this->normalize($query);
        $tokens = preg_split('/[^a-zа-я0-9]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 2)));
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(str_replace('ё', 'е', $value));
    }

    private function pageId(?string $page): ?string
    {
        if ($page === null || $page === '') {
            return null;
        }

        return match ($page) {
            'Admin/Dashboard' => 'admin.dashboard',
            'Admin/Tasks/Index' => 'admin.tasks.index',
            'Admin/Tasks/Create' => 'admin.tasks.create',
            'Admin/Tasks/Show', 'Admin/Tasks/Edit' => 'admin.tasks.show',
            'Admin/Projects/Index' => 'admin.projects.index',
            'Admin/Projects/Show', 'Admin/Projects/Edit' => 'admin.projects.show',
            'Admin/Projects/Create' => 'admin.projects.create',
            'Admin/Clients/Index' => 'admin.clients.index',
            'Admin/Clients/Create' => 'admin.clients.create',
            'Admin/Clients/Show', 'Admin/Clients/Edit' => 'admin.clients.show',
            'Admin/Invoices/Index' => 'admin.invoices.index',
            'Admin/Invoices/Create' => 'admin.invoices.create',
            'Admin/Invoices/Show' => 'admin.invoices.show',
            'Admin/Reports/Index', 'Admin/Reports/Show' => 'admin.reports.index',
            'Admin/Reports/Create' => 'admin.reports.create',
            'Admin/Forms/Index', 'Admin/TaskTemplates/Index', 'Admin/TaskTemplates/Create', 'Admin/TaskTemplates/Edit' => 'admin.library',
            'Admin/Settings/Index' => 'admin.settings',
            'Assistant/Chat' => 'admin.assistant',
            'Help/Index' => 'admin.help',
            'Client/Dashboard' => 'client.dashboard',
            'Client/Tasks/Index' => 'client.tasks.index',
            'Client/Tasks/Create' => 'client.tasks.create',
            'Client/Tasks/Show' => 'client.tasks.show',
            'Client/Projects/Index' => 'client.projects.index',
            'Client/Projects/Show' => 'client.projects.show',
            'Client/Invoices/Index' => 'client.invoices.index',
            'Client/Invoices/Show' => 'client.invoices.show',
            'Client/Reports/Index', 'Client/Reports/Show' => 'client.reports.index',
            'Client/Profile/Edit' => 'client.profile',
            default => null,
        };
    }

    private function pageIdFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return match (true) {
            str_starts_with($path, '/admin/dashboard') => 'admin.dashboard',
            str_starts_with($path, '/admin/tasks/create') => 'admin.tasks.create',
            str_starts_with($path, '/admin/tasks') => 'admin.tasks.index',
            str_starts_with($path, '/admin/projects/create') => 'admin.projects.create',
            str_starts_with($path, '/admin/projects') => 'admin.projects.show',
            str_starts_with($path, '/admin/clients/create') => 'admin.clients.create',
            str_starts_with($path, '/admin/clients') => 'admin.clients.show',
            str_starts_with($path, '/admin/invoices/create') => 'admin.invoices.create',
            str_starts_with($path, '/admin/invoices') => 'admin.invoices.show',
            str_starts_with($path, '/admin/reports') => 'admin.reports.index',
            str_starts_with($path, '/admin/forms'), str_starts_with($path, '/admin/task-templates') => 'admin.library',
            str_starts_with($path, '/admin/settings') => 'admin.settings',
            str_starts_with($path, '/assistant') => 'admin.assistant',
            str_starts_with($path, '/help') => 'admin.help',
            str_starts_with($path, '/dashboard') => 'client.dashboard',
            str_starts_with($path, '/tasks/create') => 'client.tasks.create',
            str_starts_with($path, '/tasks') => 'client.tasks.index',
            str_starts_with($path, '/projects') => 'client.projects.index',
            str_starts_with($path, '/invoices') => 'client.invoices.index',
            str_starts_with($path, '/reports') => 'client.reports.index',
            str_starts_with($path, '/profile') => 'client.profile',
            default => null,
        };
    }
}
