<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 30)->unique();
            $table->string('name', 200);
            $table->string('custom_domain', 255)->nullable()->unique();
            $table->timestamp('domain_verified_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("
            ALTER TABLE tenants
            ADD CONSTRAINT check_tenant_status
            CHECK (status IN ('active', 'suspended'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
