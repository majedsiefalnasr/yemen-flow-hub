<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('license_number')->nullable();
            $table->string('swift_code')->nullable()->unique();
            $table->enum('status', ['ACTIVE', 'SUSPENDED'])->default('ACTIVE');
            $table->unsignedBigInteger('version')->default(1);
        });

        $organizationId = DB::table('organizations')->where('code', 'commercial_banks')->value('id');
        DB::table('banks')->update([
            'organization_id' => $organizationId,
            'status' => DB::raw("CASE WHEN is_active = 1 THEN 'ACTIVE' ELSE 'SUSPENDED' END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropUnique(['swift_code']);
            $table->dropColumn(['organization_id', 'license_number', 'swift_code', 'status', 'version']);
        });
    }
};
