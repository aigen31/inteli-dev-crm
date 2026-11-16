<?php

declare(strict_types=1);

namespace App\Services\Briefs;

use App\Enums\BriefStatus;
use App\Models\Brief;
use App\Models\BriefTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Жизненный цикл брифа: создание, автосохранение, отправка, возврат, архив.
 *
 * Telegram-ботовский перенос: ссылки-приглашения больше не нужны, доступ даёт
 * аккаунт CRM и проект. После отправки создаётся задача на разбор — это
 * замена уведомлению в Telegram.
 */
class BriefService
{
    public function __construct(
        private readonly CreateBriefTaskAction $tasks,
    ) {}

    /**
     * @param  array{title?: string|null, client_id?: string|null, project_id?: string|null}  $data
     */
    public function create(BriefTemplate $template, array $data, User $admin): Brief
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            $title = $template->title;
        }

        $brief = Brief::create([
            'brief_template_id' => $template->id,
            'template_version' => $template->version,
            'client_id' => $data['client_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'created_by' => $admin->id,
            'title' => $title,
            'status' => BriefStatus::Draft,
            'data' => [],
            'progress' => 0,
        ]);

        return $brief->load(['template', 'client', 'project']);
    }

    /**
     * Сохраняет ответы (автосохранение в мастере). Валидация мягкая:
     * недозаполненный бриф сохраняется, обязательные поля проверяются при отправке.
     *
     * @param  array<string, mixed>  $answers
     */
    public function saveAnswers(Brief $brief, array $answers): Brief
    {
        if (! $brief->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Бриф уже отправлен — изменения недоступны.',
            ]);
        }

        $schema = $brief->schema();
        $clean = $this->normalizeAnswers($schema, $answers);
        $progress = BriefSchema::progress($schema, $clean);

        $brief->fill([
            'data' => $clean,
            'progress' => $progress,
            'status' => $progress > 0 ? BriefStatus::InProgress : BriefStatus::Draft,
        ])->save();

        return $brief;
    }

    /**
     * Отправка брифа: снимок схемы, ревизия, задача на разбор.
     */
    public function submit(Brief $brief, ?User $actor = null): Brief
    {
        if (! $brief->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Бриф уже отправлен.',
            ]);
        }

        $schema = $brief->schema();
        $answers = $this->normalizeAnswers($schema, $brief->answers());
        $errors = BriefSchema::validate($schema, $answers);

        if ($errors !== []) {
            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn (string $message, string $key): array => ["data.{$key}" => $message])->all(),
            );
        }

        DB::transaction(function () use ($brief, $answers, $schema, $actor): void {
            $brief->fill([
                'data' => $answers,
                'schema_snapshot' => $schema,
                'status' => BriefStatus::Submitted,
                'submitted_at' => $brief->submitted_at ?? now(),
                'progress' => 100,
            ])->save();

            $version = (int) $brief->revisions()->max('version') + 1;

            $brief->revisions()->create([
                'version' => $version,
                'data' => $answers,
                'schema' => $schema,
                'created_by' => $actor?->id,
                'reason' => $actor === null ? 'submitted_public' : 'submitted',
            ]);

            $this->tasks->createFor($brief->fresh(['template', 'client', 'project']) ?? $brief, $actor ?? $brief->creator);

            // Ссылка одноразовая: после отправки все активные ссылки гаснут.
            $brief->links()->whereNull('used_at')->whereNull('revoked_at')->update(['used_at' => now()]);
        });

        return $brief->refresh();
    }

    /**
     * Переименование брифа. Заголовок видят клиент, публичная ссылка и экспорт,
     * поэтому пустая строка и перебор по длине отсекаются здесь, а не в UI.
     */
    public function rename(Brief $brief, string $title): Brief
    {
        $title = trim($title);

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Заголовок не может быть пустым.']);
        }

        if (mb_strlen($title) > 200) {
            throw ValidationException::withMessages(['title' => 'Заголовок длиннее 200 символов.']);
        }

        $brief->fill(['title' => $title])->save();

        return $brief->refresh();
    }

    /**
     * Вернуть клиенту на доработку (только админ).
     */
    public function reopen(Brief $brief, User $admin): Brief
    {
        $brief->fill([
            'status' => BriefStatus::InProgress,
            'archived_at' => null,
        ])->save();

        $version = (int) $brief->revisions()->max('version') + 1;

        $brief->revisions()->create([
            'version' => $version,
            'data' => $brief->answers(),
            'schema' => $brief->schema(),
            'created_by' => $admin->id,
            'reason' => 'reopened',
        ]);

        return $brief->refresh();
    }

    public function archive(Brief $brief): Brief
    {
        $brief->fill([
            'status' => BriefStatus::Archived,
            'archived_at' => now(),
        ])->save();

        // Архивный бриф не должен оставаться открытым по внешней ссылке.
        $brief->links()->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return $brief->refresh();
    }

    /**
     * Оставляет только известные схеме поля и приводит типы.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function normalizeAnswers(array $schema, array $answers): array
    {
        $fields = BriefSchema::fieldsByKey($schema);
        $clean = [];

        foreach ($answers as $key => $value) {
            $field = $fields[(string) $key] ?? null;

            if ($field === null) {
                continue;
            }

            $type = (string) $field['type'];

            $clean[(string) $key] = match ($type) {
                'number', 'scale' => is_numeric($value) ? $value + 0 : null,
                'multi_choice' => array_values(array_filter(
                    array_map(static fn (mixed $item): string => (string) $item, is_array($value) ? $value : []),
                    static fn (string $item): bool => $item !== '',
                )),
                'contact_block' => is_array($value)
                    ? array_filter(
                        array_map(static fn (mixed $item): string => is_string($item) ? trim($item) : (string) $item, $value),
                        static fn (string $item): bool => $item !== '',
                    )
                    : [],
                'file' => is_string($value) && $value !== '' ? $value : null,
                default => is_string($value) ? trim($value) : $value,
            };
        }

        return array_filter($clean, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }
}
