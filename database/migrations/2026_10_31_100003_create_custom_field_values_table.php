<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('custom_field_id')->constrained('custom_fields')->cascadeOnDelete();
            $table->string('entity_type', 32);
            $table->uuid('entity_id');
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 20, 4)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_id', 'entity_id']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('value_text');
            $table->index('value_number');
            $table->index('value_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
