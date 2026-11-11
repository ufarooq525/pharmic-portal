<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained();
            $table->date('period');                              // first day of the month
            $table->string('status', 20);                        // needs_review | blocked | confirmed
            $table->string('layout', 20);
            $table->string('original_name');
            $table->string('file_path');
            $table->string('distributor_in_file')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('file_units', 12, 2)->nullable();
            $table->decimal('file_value', 14, 2)->nullable();
            $table->decimal('parsed_units', 12, 2);
            $table->decimal('parsed_value', 14, 2)->nullable();
            $table->json('checks');                              // [{key, ok, message}]
            $table->json('customers');                           // every customer in the file, incl. zero-unit ones
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['distributor_id', 'period']);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->string('customer_code', 30)->nullable();
            $table->string('customer_name');
            $table->string('area')->nullable();
            $table->string('product_name');
            $table->foreignId('product_id')->nullable()->constrained();
            $table->decimal('units', 12, 2);
            $table->decimal('bonus', 12, 2)->nullable();          // null = not printed in the file
            $table->decimal('trade_price', 12, 2)->nullable();
            $table->decimal('value', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('imports');
    }
};
