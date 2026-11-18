<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything posted from a file can be traced back to it:
 * - each row read keeps the PDF page and the exact text line it came from;
 * - each sale line keeps its source row, the bonus printed in the file, the scheme bonus and which rule decided the bonus;
 * - each stock movement from an import keeps its source row.
 * Also: "use the file's bonus" no longer falls back to the scheme when the file prints none, so the separate
 * "none" setting (which already behaved that way) becomes "file".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->unsignedSmallInteger('source_page')->nullable();
            $table->string('source_text', 500)->nullable();
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->foreignId('import_row_id')->nullable()->constrained('import_rows')->nullOnDelete();
            $table->decimal('bonus_in_file', 12, 2)->nullable();
            $table->decimal('scheme_bonus', 12, 2)->default(0);
            $table->string('bonus_rule', 10)->nullable();   // file | scheme | none (nothing printed)
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('import_row_id')->nullable()->constrained('import_rows')->nullOnDelete();
        });

        DB::table('distributors')->where('bonus_source', 'none')->update(['bonus_source' => 'file']);
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_row_id');
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_row_id');
            $table->dropColumn(['bonus_in_file', 'scheme_bonus', 'bonus_rule']);
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropColumn(['source_page', 'source_text']);
        });
        // Distributors moved from "none" to "file" keep "file": it now means the same thing.
    }
};
