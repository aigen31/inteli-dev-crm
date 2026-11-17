<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('type', 20)->default('client')->after('key');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('balance', 12, 2)->default(0)->after('is_active');
        });

        Schema::create('balance_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reason', 40);
            $table->string('description')->nullable();
            $table->foreignUuid('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_transactions');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('balance');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
