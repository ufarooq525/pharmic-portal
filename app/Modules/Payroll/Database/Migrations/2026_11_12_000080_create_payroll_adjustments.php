<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per rep, per month: anything on top of base salary + incentive (advance, fine, allowance).
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_rep_id')->constrained();
            $table->date('period');
            $table->decimal('allowance', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['medical_rep_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
