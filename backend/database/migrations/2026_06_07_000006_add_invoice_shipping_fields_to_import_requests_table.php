<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('trader_id')->nullable()->after('merchant_id');
            $table->string('request_type')->nullable()->after('trader_id');
            $table->string('coverage_type')->nullable()->after('request_type');
            $table->string('currency_source')->nullable()->after('coverage_type');
            $table->string('payment_terms_mode')->nullable()->after('currency_source');
            $table->decimal('request_percentage', 5, 2)->nullable()->after('payment_terms_mode');
            $table->string('request_currency', 10)->nullable()->after('request_percentage');
            $table->decimal('requested_amount', 15, 2)->nullable()->after('request_currency');
            $table->string('invoice_type')->nullable()->after('requested_amount');
            $table->string('invoice_currency', 10)->nullable()->after('invoice_type');
            $table->string('unit_of_measure', 100)->nullable()->after('invoice_currency');
            $table->decimal('total_invoice_amount', 15, 2)->nullable()->after('unit_of_measure');
            $table->text('commodity')->nullable()->after('total_invoice_amount');
            $table->string('exporting_company_name')->nullable()->after('commodity');
            $table->string('exporting_company_location')->nullable()->after('exporting_company_name');
            $table->string('country_of_origin', 100)->nullable()->after('exporting_company_location');
            $table->string('port_of_loading')->nullable()->after('country_of_origin');
            $table->string('port_of_arrival')->nullable()->after('port_of_loading');
            $table->string('incoterm')->nullable()->after('port_of_arrival');
            $table->string('final_destination')->nullable()->after('incoterm');
            $table->date('shipping_date')->nullable()->after('final_destination');
            $table->date('arrival_date')->nullable()->after('shipping_date');
            $table->string('trader_snapshot_name')->nullable()->after('arrival_date');
            $table->string('trader_snapshot_tax_number')->nullable()->after('trader_snapshot_name');
            $table->date('trader_snapshot_tax_card_expiry')->nullable()->after('trader_snapshot_tax_number');
            $table->string('trader_snapshot_commercial_registration_number')->nullable()->after('trader_snapshot_tax_card_expiry');
            $table->date('trader_snapshot_commercial_registration_expiry')->nullable()->after('trader_snapshot_commercial_registration_number');
            $table->tinyInteger('voting_rule_version')->unsigned()->default(1)->after('trader_snapshot_commercial_registration_expiry');

            $table->foreign('trader_id')->references('id')->on('traders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->dropForeign(['trader_id']);
            $table->dropColumn([
                'trader_id',
                'request_type',
                'coverage_type',
                'currency_source',
                'payment_terms_mode',
                'request_percentage',
                'request_currency',
                'requested_amount',
                'invoice_type',
                'invoice_currency',
                'unit_of_measure',
                'total_invoice_amount',
                'commodity',
                'exporting_company_name',
                'exporting_company_location',
                'country_of_origin',
                'port_of_loading',
                'port_of_arrival',
                'incoterm',
                'final_destination',
                'shipping_date',
                'arrival_date',
                'trader_snapshot_name',
                'trader_snapshot_tax_number',
                'trader_snapshot_tax_card_expiry',
                'trader_snapshot_commercial_registration_number',
                'trader_snapshot_commercial_registration_expiry',
                'voting_rule_version',
            ]);
        });
    }
};
