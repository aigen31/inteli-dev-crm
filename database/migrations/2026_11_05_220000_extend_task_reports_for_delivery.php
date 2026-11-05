<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_reports', function (Blueprint $table) {
            $table->uuid('task_id')->nullable()->change();
            $table->foreignUuid('recipient_id')->nullable()->after('author_id')
                ->constrained('users')->nullOnDelete();
            $table->string('test_url', 500)->nullable()->after('content');
            $table->foreignUuid('screenshot_id')->nullable()->after('test_url')
                ->constrained('file_attachments')->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->after('attachments');
            $table->timestamp('read_at')->nullable()->after('sent_at');

            $table->index('recipient_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipient_id');
            $table->dropConstrainedForeignId('screenshot_id');
            $table->dropColumn(['test_url', 'sent_at', 'read_at']);
        });
    }
};
