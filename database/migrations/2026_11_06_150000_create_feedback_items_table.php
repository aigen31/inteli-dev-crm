<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('title', 160);
            $table->text('description');
            $table->string('category', 32)->nullable();
            $table->string('severity', 16)->nullable();
            $table->text('steps')->nullable();
            $table->text('expected')->nullable();
            $table->text('actual')->nullable();
            $table->string('environment', 160)->nullable();
            $table->string('status', 24)->default('open');
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('feedback_votes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('feedback_item_id')->constrained('feedback_items')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['feedback_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_votes');
        Schema::dropIfExists('feedback_items');
    }
};
