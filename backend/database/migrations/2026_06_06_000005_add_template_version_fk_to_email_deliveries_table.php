<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->foreign('template_version_id', 'ed_template_version_fk')
                ->references('id')
                ->on('notification_template_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropForeign('ed_template_version_fk');
        });
    }
};
