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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('buyer_id')->nullable()->constrained('customers');
            $table->string('po_number')->unique()->nullable();
            $table->date('po_date')->nullable();
            $table->string('purchaser_name')->nullable();
            $table->string('payment_term')->nullable();
            $table->string('currency')->default('IDR');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->decimal('grand_total', 15, 2)->nullable();
            $table->string('file_attachment')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->integer('item_sequence')->nullable();
            $table->string('material_number')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('uom')->nullable();
            $table->date('delivery_date')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('net_value', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
