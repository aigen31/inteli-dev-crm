<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 32);
            $table->string('key', 64);
            $table->string('label');
            $table->string('type', 32);
            $table->json('options')->nullable();
            $table->json('default_value')->nullable();
            $table->json('validation')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_list')->default(false);
            $table->boolean('client_editable')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('help_text')->nullable();
            $table->string('placeholder')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entity_type', 'key']);
            $table->index(['entity_type', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
