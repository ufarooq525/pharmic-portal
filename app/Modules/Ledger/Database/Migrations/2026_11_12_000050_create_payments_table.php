<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money received from a distributor. Credits in its ledger; confirmed monthly sales are the debits.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained();
            $table->date('paid_on');
            $table->decimal('amount', 14, 2);
            $table->string('method', 20);                      // cash | bank | cheque | online
            $table->string('reference', 100)->nullable();     // cheque or transaction number
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['distributor_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
