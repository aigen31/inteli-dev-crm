<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brief_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('summary', 500)->nullable();
            $table->string('icon')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->json('schema');
            $table->string('schema_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('briefs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('brief_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('template_version')->default(1);
            $table->foreignUuid('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status', 20)->default('draft');
            $table->json('data');
            $table->json('schema_snapshot')->nullable();
            $table->text('admin_note')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['status', 'updated_at']);
            $table->index('project_id');
        });

        Schema::create('brief_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('brief_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('data');
            $table->json('schema');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 40)->default('submitted');
            $table->timestamp('created_at')->nullable();

            $table->unique(['brief_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_revisions');
        Schema::dropIfExists('briefs');
        Schema::dropIfExists('brief_templates');
    }
};
