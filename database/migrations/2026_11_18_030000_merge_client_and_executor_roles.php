<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Клиент и исполнитель объединены в обычного пользователя.
     * Кем он выступает — решает назначение в проекте/задаче, а не роль.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['client', 'executor'])
            ->update(['role' => 'user']);
    }

    public function down(): void
    {
        // Разделение не восстанавливается: какие были роли, история не хранит.
    }
};
