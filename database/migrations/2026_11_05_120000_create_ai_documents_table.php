<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('file_attachment_id')->nullable()
                ->constrained('file_attachments')->nullOnDelete();
            $table->string('name');
            $table->string('mime_type', 150)->nullable();
            $table->longText('text');
            $table->unsignedInteger('char_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            // Tool-by-tool record of how the answer was produced, mirrored from
            // the kernel so the chat can show the process instead of a spinner.
            $table->json('trace')->nullable()->after('attachments');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn('trace');
        });

        Schema::dropIfExists('ai_documents');
    }
};
