<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('request_votes', function (Blueprint $table) {
            $table->timestamp('voted_at')->nullable()->after('is_director_override');
            $table->index('voted_at');
        });
    }

    public function down(): void
    {
        Schema::table('request_votes', function (Blueprint $table) {
            $table->dropIndex(['voted_at']);
            $table->dropColumn('voted_at');
        });
    }
};
