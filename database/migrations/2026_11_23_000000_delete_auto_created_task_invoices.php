<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Раньше завершение задачи создавало счёт «прямой перевод» и списывало
     * баланс. Теперь списание идёт без счёта, поэтому автосозданные счета
     * удаляем. Признак автоматического счёта — ссылка из операции баланса.
     */
    public function up(): void
    {
        $invoiceIds = DB::table('balance_transactions')
            ->whereNotNull('invoice_id')
            ->pluck('invoice_id')
            ->all();

        if ($invoiceIds !== []) {
            DB::table('invoices')->whereIn('id', $invoiceIds)->delete();
        }
    }

    public function down(): void
    {
        // Восстанавливать счета не нужно: списание больше их не создаёт.
    }
};
