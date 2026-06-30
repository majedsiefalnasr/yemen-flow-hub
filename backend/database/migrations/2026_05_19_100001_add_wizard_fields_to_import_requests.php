<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->string('goods_type', 100)->nullable()->after('goods_description');
            $table->string('payment_terms', 50)->nullable()->after('goods_type');
            $table->date('due_date')->nullable()->after('payment_terms');
            $table->string('invoice_number', 100)->nullable()->after('due_date');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->string('origin_country', 100)->nullable()->after('invoice_date');
            $table->string('arrival_port', 100)->nullable()->after('origin_country');
            $table->string('shipping_port', 255)->nullable()->after('arrival_port');
            $table->string('customs_office', 100)->nullable()->after('shipping_port');
            $table->string('bl_number', 100)->nullable()->after('customs_office');
        });
    }

    public function down(): void
    {
        Schema::table('import_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'goods_type',
                'payment_terms',
                'due_date',
                'invoice_number',
                'invoice_date',
                'origin_country',
                'arrival_port',
                'shipping_port',
                'customs_office',
                'bl_number',
            ]);
        });
    }
};
