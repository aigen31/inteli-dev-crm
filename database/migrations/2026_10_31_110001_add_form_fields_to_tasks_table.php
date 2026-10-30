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
            $table->foreignUuid('form_template_id')->nullable()->after('task_template_id')
                ->constrained('form_templates')->nullOnDelete();
            $table->json('form_data')->nullable()->after('checklist');
            $table->index('form_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('form_template_id');
            $table->dropColumn('form_data');
        });
    }
};
