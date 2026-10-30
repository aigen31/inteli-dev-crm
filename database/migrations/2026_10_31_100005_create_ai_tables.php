<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->string('tool_call_id')->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('ai_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tool_name');
            $table->json('arguments');
            $table->json('result')->nullable();
            $table->string('status', 16)->default('proposed');
            $table->boolean('requires_confirmation')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_actions');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
