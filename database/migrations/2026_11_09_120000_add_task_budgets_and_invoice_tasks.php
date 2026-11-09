<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('budget', 12, 2)->nullable()->after('estimated_minutes');
        });

        Schema::create('invoice_task', function (Blueprint $table) {
            $table->uuid('invoice_id');
            $table->uuid('task_id');
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->primary(['invoice_id', 'task_id']);
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
        });

        // Preserve one-task invoices created before the pivot existed.
        DB::table('invoices')
            ->select(['id', 'task_id', 'amount', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(200, function ($invoices): void {
                $rows = [];

                foreach ($invoices as $invoice) {
                    $rows[] = [
                        'invoice_id' => $invoice->id,
                        'task_id' => $invoice->task_id,
                        'amount' => $invoice->amount,
                        'created_at' => $invoice->created_at,
                        'updated_at' => $invoice->updated_at,
                    ];
                }

                if ($rows !== []) {
                    DB::table('invoice_task')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_task');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('budget');
        });
    }
};
