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
        Schema::create('task_report_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_report_id')->constrained('task_reports')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->text('content');
            $table->string('test_url', 500)->nullable();
            $table->foreignUuid('screenshot_id')->nullable()
                ->constrained('file_attachments')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_report_id', 'position']);
        });

        // Every legacy report becomes one item, so old and new reports render
        // through the same repeatable format.
        DB::table('task_reports')->orderBy('id')->get()->each(function (object $report): void {
            if (trim((string) $report->content) === '') {
                return;
            }

            DB::table('task_report_items')->insert([
                'id' => (string) Str::uuid(),
                'task_report_id' => $report->id,
                'position' => 0,
                'content' => $report->content,
                'test_url' => $report->test_url,
                'screenshot_id' => $report->screenshot_id,
                'created_at' => $report->created_at ?? now(),
                'updated_at' => $report->updated_at ?? now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_report_items');
    }
};
