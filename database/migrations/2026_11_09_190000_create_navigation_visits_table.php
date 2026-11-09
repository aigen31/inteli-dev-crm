<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('visitable_type', 120);
            $table->uuid('visitable_id');
            // Denormalised for the project colour chip without an extra join.
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('path', 500);
            $table->timestamp('visited_at');
            $table->timestamps();

            // One row per place: a revisit bumps visited_at instead of piling up.
            $table->unique(['user_id', 'visitable_type', 'visitable_id']);
            $table->index(['user_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_visits');
    }
};
