<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Публичные ссылки на заполнение брифа без аккаунта.
     *
     * В базе лежит только хэш токена: утечка дампа не даёт рабочий URL.
     */
    public function up(): void
    {
        Schema::create('brief_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('brief_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['brief_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brief_links');
    }
};
