<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An issued invoice is a frozen copy of a month's confirmed sales; it never changes. A
        // mistake is corrected by a credit note (kind = credit_note) that reverses it in full.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('kind', 12)->default('invoice');       // invoice | credit_note
            $table->foreignId('distributor_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->date('period');
            $table->string('status', 10)->default('issued');      // issued | cancelled
            $table->foreignId('credit_note_for_id')->nullable()->constrained('invoices');
            $table->decimal('units', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('value', 14, 2)->default(0);
            $table->decimal('sales_tax', 14, 2)->default(0);
            $table->decimal('withholding_tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['period', 'distributor_id']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->decimal('units', 12, 2);
            $table->decimal('bonus', 12, 2);
            $table->decimal('rate', 12, 2);
            $table->decimal('value', 14, 2);
            $table->decimal('sales_tax_rate', 5, 2);
            $table->decimal('sales_tax', 14, 2);
            $table->decimal('withholding_tax_rate', 5, 2);
            $table->decimal('withholding_tax', 14, 2);
            $table->decimal('total', 14, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
