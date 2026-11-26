<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infrastructure_services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // backup | cluster
            $table->string('name');
            $table->string('status', 20)->default('draft'); // draft | active | suspended
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->string('currency', 3)->default('RUB');
            $table->date('started_at')->nullable();
            $table->date('next_invoice_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('backup_targets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained('infrastructure_services')->cascadeOnDelete();
            $table->string('db_type', 20)->default('mysql');
            $table->string('db_name')->nullable();
            $table->string('ftp_host');
            $table->unsignedInteger('ftp_port')->default(21);
            $table->string('ftp_username');
            $table->text('ftp_password');
            $table->string('remote_path')->default('/');
            $table->unsignedTinyInteger('frequency_per_week')->default(1);
            $table->unsignedTinyInteger('retention')->default(3);
            $table->decimal('quota_gb', 8, 2)->default(3);
            $table->timestamp('last_backup_at')->nullable();
            $table->unsignedBigInteger('last_backup_size')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('target_id')->constrained('backup_targets')->cascadeOnDelete();
            $table->string('filename');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->string('status', 20)->default('received'); // received | verified | failed
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->unique(['target_id', 'filename']);
            $table->index(['target_id', 'uploaded_at']);
        });

        Schema::create('dr_clusters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained('infrastructure_services')->cascadeOnDelete();
            $table->string('tier', 20)->default('warm'); // cold | warm | hot | active_active
            $table->string('size', 10)->default('S'); // S | M | L | XL
            $table->string('region', 40)->default('ru-central');
            $table->unsignedInteger('rto_minutes')->default(60);
            $table->unsignedInteger('rpo_minutes')->default(15);
            $table->string('status', 20)->default('draft'); // draft | ready | degraded | failed
            $table->timestamp('last_drill_at')->nullable();
            $table->text('runbook')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->uuid('task_id')->nullable()->change();
            $table->foreignUuid('infrastructure_service_id')->nullable()->after('task_id')
                ->constrained('infrastructure_services')->nullOnDelete();
            $table->string('service_period', 7)->nullable()->after('infrastructure_service_id');

            $table->unique(['infrastructure_service_id', 'service_period'], 'invoices_service_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_service_period_unique');
            $table->uuid('task_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('infrastructure_service_id');
            $table->dropColumn('service_period');
        });

        Schema::dropIfExists('dr_clusters');
        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('backup_targets');
        Schema::dropIfExists('infrastructure_services');
    }
};
