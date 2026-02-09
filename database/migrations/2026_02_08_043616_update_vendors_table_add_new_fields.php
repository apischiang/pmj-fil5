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
        Schema::table('vendors', function (Blueprint $table) {
            $table->renameColumn('tax_number', 'vat_number');
            $table->string('initial')->nullable()->after('company_name');
            $table->boolean('is_pkp')->default(false)->after('vat_number');
            $table->string('npwp_file')->nullable()->after('is_pkp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->renameColumn('vat_number', 'tax_number');
            $table->dropColumn(['initial', 'is_pkp', 'npwp_file']);
        });
    }
};
