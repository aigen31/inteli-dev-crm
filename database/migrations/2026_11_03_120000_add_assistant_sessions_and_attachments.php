<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            // Image references ({id, name, mime}) carried by a user turn. Bytes
            // stay in file_attachments; the kernel pulls them by id.
            $table->json('attachments')->nullable()->after('tool_call_id');
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            // Local mirror of kernel session metadata. The kernel is the source
            // of truth; these columns exist for the degraded path and for the
            // action FK when the kernel is briefly unavailable.
            $table->timestamp('pinned_at')->nullable()->after('last_message_at');
            $table->timestamp('archived_at')->nullable()->after('pinned_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn(['pinned_at', 'archived_at']);
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
