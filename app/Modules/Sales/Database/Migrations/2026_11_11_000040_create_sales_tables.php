<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prices and tax rates are copied in at confirm time, so later rate changes never alter posted months.
        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('distributor_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->date('period');
            $table->decimal('units', 12, 2);
            $table->decimal('bonus_units', 12, 2)->default(0);
            $table->boolean('bonus_from_file')->default(false);
            $table->decimal('trade_price', 12, 2);
            $table->decimal('exfp_price', 12, 2);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('value_tp', 14, 2);
            $table->decimal('value_exfp', 14, 2);
            $table->decimal('sales_tax_rate', 5, 2);
            $table->decimal('sales_tax', 14, 2);
            $table->decimal('withholding_tax_rate', 5, 2);
            $table->decimal('withholding_tax', 14, 2);
            $table->decimal('incentive_rate', 5, 2);
            $table->decimal('incentive', 14, 2);
            $table->timestamps();
            $table->index(['period', 'distributor_id']);
            $table->index(['period', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('import_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);                          // opening | purchase | sale | return | adjustment
            $table->decimal('units', 12, 2);                     // signed: in is positive, out is negative
            $table->date('moved_on');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'moved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('sale_lines');
    }
};
