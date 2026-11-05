<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FileAttachment;
use App\Models\TaskReport;
use App\Models\User;
use App\Notifications\ReportDelivered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(
        private readonly FileUploadService $files,
    ) {}

    /**
     * A report is a repeatable group: short explanation, optional test URL,
     * optional screenshot. It may be attached to a task or stand alone.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $author, array $data): TaskReport
    {
        $validated = Validator::make($data, [
            'task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
            'title' => ['nullable', 'string', 'max:160'],
            'recipient_id' => ['nullable', 'uuid', 'exists:users,id'],
            'recipient_email' => ['nullable', 'email', 'exists:users,email'],
            'items' => ['nullable', 'array'],
            'items.*.summary' => ['required', 'string', 'min:3', 'max:5000'],
            'items.*.test_url' => ['nullable', 'url', 'max:500'],
            'items.*.screenshot' => ['nullable', 'image', 'max:10240'],
            'items.*.screenshot_base64' => ['nullable', 'string'],
            'items.*.screenshot_mime' => ['nullable', 'string', 'in:image/png,image/jpeg,image/webp,image/gif'],
            'items.*.screenshot_name' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'min:3', 'max:5000'],
            'test_url' => ['nullable', 'url', 'max:500'],
            'screenshot' => ['nullable', 'image', 'max:10240'],
        ])->validate();

        $items = $validated['items'] ?? [];

        if ($items === [] && filled($validated['summary'] ?? null)) {
            $items = [[
                'summary' => $validated['summary'],
                'test_url' => $validated['test_url'] ?? null,
                'screenshot' => $validated['screenshot'] ?? null,
            ]];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Добавьте хотя бы одну группу отчёта.',
            ]);
        }

        $recipient = $this->resolveRecipient($validated);

        if ($recipient === null) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Укажите получателя отчёта.',
            ]);
        }

        $first = $items[0];

        $report = TaskReport::create([
            'task_id' => $validated['task_id'] ?? null,
            'author_id' => $author->id,
            'recipient_id' => $recipient->id,
            'title' => filled($validated['title'] ?? null) ? trim((string) $validated['title']) : null,
            'content' => trim((string) $first['summary']),
            'test_url' => $first['test_url'] ?? null,
            'screenshot_id' => null,
            'attachments' => [],
            'sent_at' => now(),
        ]);

        $firstScreenshot = null;

        foreach (array_values($items) as $position => $item) {
            $screenshotId = $this->screenshotFor($item, $author);

            $report->items()->create([
                'position' => $position,
                'content' => trim((string) $item['summary']),
                'test_url' => $item['test_url'] ?? null,
                'screenshot_id' => $screenshotId,
            ]);

            $firstScreenshot ??= $screenshotId;
        }

        $report->update(['screenshot_id' => $firstScreenshot]);

        if ($recipient->id !== $author->id) {
            $recipient->notify(new ReportDelivered($report->refresh()));
        }

        return $report->load([
            'author:id,name',
            'recipient:id,name,email',
            'task:id,title,project_id',
            'items.screenshot',
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function screenshotFor(array $item, User $author): ?string
    {
        if (($item['screenshot'] ?? null) instanceof UploadedFile) {
            return $this->files->upload($item['screenshot'], $author)->id;
        }

        if (filled($item['screenshot_base64'] ?? null)) {
            return $this->storeBase64($item, $author)->id;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function storeBase64(array $validated, User $author): FileAttachment
    {
        $mime = (string) ($validated['screenshot_mime'] ?? 'image/png');
        $raw = (string) $validated['screenshot_base64'];

        if (str_starts_with($raw, 'data:')) {
            $raw = substr($raw, (int) strpos($raw, ',') + 1);
        }

        $contents = base64_decode($raw, true);

        if ($contents === false) {
            throw ValidationException::withMessages(['screenshot_base64' => 'Скриншот не является корректным base64.']);
        }

        if (strlen($contents) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['screenshot_base64' => 'Скриншот больше 10 МБ.']);
        }

        $name = (string) ($validated['screenshot_name'] ?? 'report-screenshot.'.match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'gif',
        });

        return $this->files->storeContents($contents, $name, $mime, $author);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveRecipient(array $data): ?User
    {
        if (filled($data['recipient_id'] ?? null)) {
            return User::query()->where('is_active', true)->find((string) $data['recipient_id']);
        }

        if (filled($data['recipient_email'] ?? null)) {
            return User::query()->where('is_active', true)->where('email', (string) $data['recipient_email'])->first();
        }

        return null;
    }
}
