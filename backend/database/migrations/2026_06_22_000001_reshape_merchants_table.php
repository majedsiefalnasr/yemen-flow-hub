<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
        });

        Schema::table('merchants', function (Blueprint $table): void {
            $table->dropUnique(['commercial_register']);
        });

        Schema::table('merchants', function (Blueprint $table): void {
            $table->dropUnique(['tax_number']);
        });

        Schema::table('merchants', function (Blueprint $table): void {
            $table->dropColumn(['commercial_register', 'national_id', 'owner_name', 'email', 'is_active']);
        });

        if (Schema::hasColumn('merchants', 'business_type')) {
            Schema::table('merchants', function (Blueprint $table): void {
                $table->dropColumn('business_type');
            });
        }

        Schema::table('merchants', function (Blueprint $table): void {
            $table->date('tax_card_expiry')->nullable()->after('tax_number');
            $table->string('status', 20)->default('ACTIVE')->after('address');
            $table->unsignedInteger('version')->default(1)->after('status');
        });

        Schema::table('merchants', function (Blueprint $table): void {
            $table->string('tax_number')->nullable(false)->change();
            $table->unique('tax_number');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table): void {
            $table->dropUnique(['tax_number']);
            $table->dropColumn(['tax_card_expiry', 'status', 'version']);
            $table->string('commercial_register')->nullable()->unique();
            $table->string('national_id')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('email')->nullable();
            $table->string('business_type', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->index('is_active');
            $table->string('tax_number')->nullable()->change();
            $table->unique('tax_number');
        });
    }
};
