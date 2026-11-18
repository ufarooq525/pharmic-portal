<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A distributor whose customer file prints no bonus (the product grid) also sends its Sales & Stock
 * report, which prints the bonus per product. The report is kept with the import it belongs to, and
 * each sale line keeps the product total its bonus share came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->string('stock_report_name')->nullable();
            $table->string('stock_report_path')->nullable();
            $table->json('stock_report')->nullable();          // products read from the report
            $table->json('stock_report_checks')->nullable();   // [{key, ok, message}]: warnings only, never block
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->decimal('bonus_target', 12, 2)->nullable(); // the product's net bonus on the Sales & Stock report
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropColumn('bonus_target');
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn(['stock_report_name', 'stock_report_path', 'stock_report', 'stock_report_checks']);
        });
    }
};
