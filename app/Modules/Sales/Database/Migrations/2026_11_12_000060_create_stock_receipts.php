<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One delivery of stock into the warehouse, entered by hand or uploaded as a file. Reverting
        // removes its stock movements but keeps the receipt, marked reverted, for the record.
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->date('received_on');
            $table->string('reference', 100)->nullable();       // supplier invoice / GRN number
            $table->string('supplier', 150)->nullable();
            $table->string('note')->nullable();
            $table->string('original_name')->nullable();        // uploaded file, if any
            $table->string('file_path')->nullable();
            $table->unsignedSmallInteger('line_count')->default(0);
            $table->decimal('total_units', 12, 2)->default(0); // kept after a revert, for the record
            $table->string('status', 10)->default('posted');    // posted | reverted
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('receipt_id')->nullable()->after('import_id')->constrained('stock_receipts')->cascadeOnDelete();
            $table->decimal('unit_cost', 12, 2)->nullable()->after('units');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_id');
            $table->dropColumn('unit_cost');
        });
        Schema::dropIfExists('stock_receipts');
    }
};
