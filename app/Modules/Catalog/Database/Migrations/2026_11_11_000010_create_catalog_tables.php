<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Prices, taxes and schemes are dated so a change never rewrites past months.
        Schema::create('product_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('exfp_price', 12, 2);
            $table->decimal('trade_price', 12, 2);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('sales_tax_rate', 5, 2)->default(0);
            $table->decimal('withholding_tax_rate', 5, 2)->default(0);
            $table->decimal('incentive_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('bonus_buy')->default(0);
            $table->unsignedSmallInteger('bonus_free')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_rates');
        Schema::dropIfExists('products');
    }
};
