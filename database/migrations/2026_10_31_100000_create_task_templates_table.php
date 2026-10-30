<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('summary')->nullable();
            $table->longText('description')->nullable();
            $table->json('checklist')->nullable();
            $table->string('default_type')->default('one_time');
            $table->string('default_priority')->default('medium');
            $table->string('default_status')->default('pending_review');
            $table->foreignUuid('default_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('default_due_days')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->foreignUuid('client_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_shared')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_shared']);
            $table->index('client_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};
