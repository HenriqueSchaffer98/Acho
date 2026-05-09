<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates and tears down the tenant_isolation_probes table used to verify
 * RLS isolation in tests. The table is intentionally NOT part of the
 * production schema — it exists only during test execution.
 *
 * Uses the default (pgsql) connection so the DDL runs inside the same
 * transaction as the test itself, avoiding cross-connection DDL visibility
 * issues. No FK to tenants: test-only table, FK is not required.
 */
class ProbeMigration
{
    public static function up(): void
    {
        Schema::create('tenant_isolation_probes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('label', 100);
            $table->timestamps();
            $table->softDeletes();
            $table->index('tenant_id');
        });

        DB::statement('ALTER TABLE tenant_isolation_probes ENABLE ROW LEVEL SECURITY');
        // FORCE RLS applies the policy even to the table owner (acho_app),
        // since the table is created via pgsql and owner is exempt by default.
        DB::statement('ALTER TABLE tenant_isolation_probes FORCE ROW LEVEL SECURITY');

        DB::statement("
            CREATE POLICY tenant_isolation ON tenant_isolation_probes
            USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
            WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
        ");
    }

    public static function down(): void
    {
        Schema::dropIfExists('tenant_isolation_probes');
    }
}
