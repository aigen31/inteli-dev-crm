<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BriefTemplate;
use App\Services\Briefs\BriefSchema;
use Illuminate\Console\Command;

/**
 * Синхронизирует шаблоны брифов из database/data/brief-templates/*.json.
 *
 * Каталог — источник правды: новый бриф = новый JSON-файл. При изменении схемы
 * версия растёт, уже созданные брифы продолжают жить на своей версии и снимке.
 */
class SeedBriefTemplates extends Command
{
    protected $signature = 'briefs:seed';

    protected $description = 'Загрузить и обновить шаблоны брифов из database/data/brief-templates';

    public function handle(): int
    {
        $directory = database_path('data/brief-templates');

        if (! is_dir($directory)) {
            $this->error("Каталог шаблонов не найден: {$directory}");

            return self::FAILURE;
        }

        $files = glob($directory.'/*.json') ?: [];
        $seen = [];
        $created = 0;
        $updated = 0;

        foreach ($files as $file) {
            $raw = file_get_contents($file);

            if ($raw === false) {
                $this->error('Не прочитан файл '.basename($file));

                continue;
            }

            $decoded = json_decode($raw, true);

            if (! is_array($decoded) || ! isset($decoded['slug'], $decoded['title'])) {
                $this->error('Невалидный шаблон '.basename($file));

                return self::FAILURE;
            }

            $schema = BriefSchema::normalize($decoded);
            $hash = BriefSchema::hash($schema);
            $slug = (string) $decoded['slug'];
            $seen[] = $slug;

            $template = BriefTemplate::query()->where('slug', $slug)->first();

            if ($template === null) {
                BriefTemplate::query()->create([
                    'slug' => $slug,
                    'title' => (string) $decoded['title'],
                    'summary' => (string) ($decoded['description'] ?? ''),
                    'icon' => (string) ($decoded['icon'] ?? ''),
                    'estimated_minutes' => (int) ($decoded['estimated_minutes'] ?? 0),
                    'schema' => $schema,
                    'schema_hash' => $hash,
                    'version' => 1,
                    'is_active' => ! ($decoded['deprecated'] ?? false),
                    'sort_order' => (int) ($decoded['sort_order'] ?? 100),
                ]);
                $created++;

                continue;
            }

            $isDeprecated = (bool) ($decoded['deprecated'] ?? false);

            if ($template->schema_hash === $hash) {
                if ($template->is_active === $isDeprecated) {
                    $template->update(['is_active' => ! $isDeprecated]);
                }

                continue;
            }

            $template->update([
                'title' => (string) $decoded['title'],
                'summary' => (string) ($decoded['description'] ?? ''),
                'icon' => (string) ($decoded['icon'] ?? ''),
                'estimated_minutes' => (int) ($decoded['estimated_minutes'] ?? 0),
                'schema' => $schema,
                'schema_hash' => $hash,
                'version' => $template->version + 1,
                'is_active' => ! $isDeprecated,
                'sort_order' => (int) ($decoded['sort_order'] ?? $template->sort_order),
            ]);
            $updated++;
        }

        $disabled = BriefTemplate::query()
            ->whereNotIn('slug', $seen)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->info("Шаблоны брифов: создано {$created}, обновлено {$updated}, выключено {$disabled}.");

        return self::SUCCESS;
    }
}
