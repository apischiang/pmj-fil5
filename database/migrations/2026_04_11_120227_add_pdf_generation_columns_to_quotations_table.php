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
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('pdf_status')->default('not_generated')->after('grand_total');
            $table->string('pdf_path')->nullable()->after('pdf_status');
            $table->timestamp('pdf_requested_at')->nullable()->after('pdf_path');
            $table->timestamp('pdf_generated_at')->nullable()->after('pdf_requested_at');
            $table->timestamp('pdf_failed_at')->nullable()->after('pdf_generated_at');
            $table->text('pdf_error')->nullable()->after('pdf_failed_at');

            $table->index(['pdf_status', 'pdf_generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['pdf_status', 'pdf_generated_at']);
            $table->dropColumn([
                'pdf_status',
                'pdf_path',
                'pdf_requested_at',
                'pdf_generated_at',
                'pdf_failed_at',
                'pdf_error',
            ]);
        });
    }
};
