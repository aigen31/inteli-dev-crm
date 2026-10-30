<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignUuid('task_template_id')->nullable()->after('client_id')
                ->constrained('task_templates')->nullOnDelete();
            $table->foreignUuid('assignee_id')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->json('checklist')->nullable()->after('description');
            $table->string('source')->default('manual')->after('type');
            $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('priority');

            $table->index('task_template_id');
            $table->index('assignee_id');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_template_id');
            $table->dropConstrainedForeignId('assignee_id');
            $table->dropColumn(['checklist', 'source', 'estimated_minutes']);
        });
    }
};
