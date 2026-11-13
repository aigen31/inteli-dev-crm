<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_items', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('resolved_at');
            $table->index(['type', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('feedback_items', function (Blueprint $table) {
            $table->dropIndex(['type', 'archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
