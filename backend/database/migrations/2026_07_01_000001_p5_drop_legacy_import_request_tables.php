<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK, unique index, then column from customs_declarations.
        Schema::table('customs_declarations', function (Blueprint $table): void {
            $table->dropForeign(['request_id']);
            $table->dropUnique('customs_declarations_request_id_unique');
            $table->dropColumn('request_id');
        });

        // Drop child tables first (FK order).
        Schema::dropIfExists('request_votes');
        Schema::dropIfExists('request_documents');
        Schema::dropIfExists('request_stage_history');
        Schema::dropIfExists('import_request_reference_sequences');
        Schema::dropIfExists('import_requests');
    }

    public function down(): void
    {
        // Intentionally irreversible — legacy data is gone after P5.
        // To restore, replay migrations from the original legacy migration files.
    }
};
