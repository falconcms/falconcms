<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mark every existing user as email-verified. Registration e-mail verification
     * only applies to NEW sign-ups going forward; this backfill ensures current
     * admins/users are never locked out when the feature is enabled.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'email_verified_at')) {
            DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
        }
    }

    public function down(): void
    {
        // Non-reversible by design (we don't want to un-verify real users).
    }
};
