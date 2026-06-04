<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->decimal('yer_equivalent', 18, 2)->nullable()->after('amount');
            $table->string('quantity')->nullable()->after('yer_equivalent');
        });
    }

    public function down(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->dropColumn(['yer_equivalent', 'quantity']);
        });
    }
};
