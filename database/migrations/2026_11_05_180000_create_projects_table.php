<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Human-readable prefix for task references (KOR-12), like Linear.
            $table->string('key', 12)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('priority', 20)->default('medium');
            $table->string('color', 20)->default('violet');
            $table->string('site_url', 500)->nullable();
            $table->longText('agents_md')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamp('studied_at')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'archived_at']);
            $table->index('client_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignUuid('project_id')->nullable()->after('client_id')
                ->constrained('projects')->nullOnDelete();
            $table->unsignedInteger('number')->nullable()->after('project_id');

            $table->index('project_id');
            $table->unique(['project_id', 'number']);
        });

        Schema::table('ai_documents', function (Blueprint $table) {
            $table->foreignUuid('project_id')->nullable()->after('user_id')
                ->constrained('projects')->nullOnDelete();
            $table->index('project_id');
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->foreignUuid('project_id')->nullable()->after('user_id')
                ->constrained('projects')->nullOnDelete();
            $table->index('project_id');
        });

        Schema::create('project_studies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kernel_study_id', 64)->nullable();
            $table->string('status', 20)->default('running');
            $table->json('events')->nullable();
            $table->longText('agents_md')->nullable();
            $table->text('notes')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });

        // Carry the previously global project context into a real project so the
        // existing AGENTS.md and site study are not lost.
        $settings = DB::table('settings')
            ->whereIn('key', ['project_site_url', 'project_agents_md', 'project_instructions', 'project_studied_at'])
            ->pluck('value', 'key');

        $siteUrl = trim((string) ($settings['project_site_url'] ?? ''));
        $agents = trim((string) ($settings['project_agents_md'] ?? ''));
        $instructions = trim((string) ($settings['project_instructions'] ?? ''));
        $studiedAt = trim((string) ($settings['project_studied_at'] ?? ''));

        if ($siteUrl !== '' || $agents !== '') {
            $host = parse_url($siteUrl, PHP_URL_HOST) ?: 'Основной проект';
            $key = strtoupper(Str::slug((string) preg_replace('/^www\./', '', (string) $host)) ?: 'MAIN');
            $key = Str::limit(preg_replace('/[^A-Z0-9]/', '', $key) ?: 'MAIN', 8, '');

            DB::table('projects')->insert([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'name' => $host,
                'description' => 'Перенесено из общих настроек проекта.',
                'status' => 'active',
                'priority' => 'medium',
                'color' => 'violet',
                'site_url' => $siteUrl !== '' ? $siteUrl : null,
                'agents_md' => $agents !== '' ? $agents : null,
                'instructions' => $instructions !== '' ? $instructions : null,
                'studied_at' => $studiedAt !== '' ? $studiedAt : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_studies');

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('ai_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'number']);
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('number');
        });

        Schema::dropIfExists('projects');
    }
};
