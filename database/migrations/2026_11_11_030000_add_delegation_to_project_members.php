<?php

declare(strict_types=1);

use App\Enums\ProjectDelegation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            $table->string('delegation')->default(ProjectDelegation::Executor->value)->after('role')->index();
        });

        // Existing client accounts invited to a project keep their client role.
        DB::table('project_members')
            ->join('users', 'users.id', '=', 'project_members.user_id')
            ->where('users.role', 'client')
            ->update(['project_members.delegation' => ProjectDelegation::Client->value]);
    }

    public function down(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            $table->dropIndex(['delegation']);
            $table->dropColumn('delegation');
        });
    }
};
