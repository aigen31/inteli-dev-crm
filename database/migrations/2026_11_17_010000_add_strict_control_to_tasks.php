<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Переопределение «строгого контроля» на уровне задачи.
     *
     * null — наследовать настройку CRM; true/false — зафиксировать режим
     * для этой задачи, независимо от глобального переключателя.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->boolean('strict_control')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('strict_control');
        });
    }
};
