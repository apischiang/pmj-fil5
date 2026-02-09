<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('type')->default('in')->after('id'); // in (Sales), out (Procurement)
            $table->foreignId('vendor_id')->nullable()->after('buyer_id')->constrained('vendors');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('purchase_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');
            $table->dropColumn('type');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('item_name');
        });
    }
};
