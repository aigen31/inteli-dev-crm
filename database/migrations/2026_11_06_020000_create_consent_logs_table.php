<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('form', 80);
            $table->string('document', 40)->default('consent');
            $table->string('version', 20);
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'form']);
            $table->index('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_logs');
    }
};
