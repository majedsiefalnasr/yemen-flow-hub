<?php

use App\Enums\OrganizationClassification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('classification', 40)->nullable()->after('name');
            $table->index('classification');
        });

        $codeMap = [
            'commercial_banks' => OrganizationClassification::BANKING_SECTOR->value,
            'national_committee' => OrganizationClassification::NATIONAL_COMMITTEE->value,
        ];

        $rows = DB::table('organizations')->select('id', 'code')->get();
        foreach ($rows as $row) {
            $classification = $codeMap[$row->code] ?? OrganizationClassification::OTHER->value;
            DB::table('organizations')->where('id', $row->id)->update(['classification' => $classification]);
        }

        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('classification', 40)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['classification']);
            $table->dropColumn('classification');
        });
    }
};
