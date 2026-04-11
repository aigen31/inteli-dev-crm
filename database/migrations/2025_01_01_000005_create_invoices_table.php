<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('RUB');
            $table->string('description');
            $table->string('status')->default('pending');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_interval')->nullable();
            $table->date('recurring_next_date')->nullable();
            $table->string('yookassa_payment_id')->nullable();
            $table->string('yookassa_payment_method_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('task_id');
            $table->index('status');
            $table->index('recurring_next_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
