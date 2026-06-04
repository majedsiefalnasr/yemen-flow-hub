<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE request_documents MODIFY COLUMN type
            ENUM('REQUEST_DOC','SWIFT','FX_REQUEST','CUSTOMS','CONFIRMATION_REQUEST') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE request_documents MODIFY COLUMN type
            ENUM('REQUEST_DOC','SWIFT','FX_REQUEST','CUSTOMS') NOT NULL");
    }
};
