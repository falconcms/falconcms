<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds multiple-roles-per-user support (hybrid). The existing users.role_id is kept as the
 * "primary" role for backward compatibility ($user->role still works); the new role_user
 * pivot holds the full set of roles. Effective permissions are the union across all roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->primary(['user_id', 'role_id']);
            });
        }

        // Backfill: every user's current primary role becomes their first pivot row.
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role_id')) {
            DB::table('users')->whereNotNull('role_id')
                ->select('id', 'role_id')->orderBy('id')
                ->chunk(500, function ($users) {
                    $rows = [];
                    foreach ($users as $u) {
                        $rows[] = ['user_id' => $u->id, 'role_id' => $u->role_id];
                    }
                    if ($rows) {
                        DB::table('role_user')->insertOrIgnore($rows);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
