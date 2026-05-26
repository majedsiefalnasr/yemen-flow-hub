<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE request_documents MODIFY COLUMN type ENUM('REQUEST_DOC','SWIFT','FX_REQUEST','CUSTOMS') NOT NULL"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE request_documents MODIFY COLUMN type ENUM('REQUEST_DOC','SWIFT','CUSTOMS') NOT NULL"
            );
        }
    }
};
