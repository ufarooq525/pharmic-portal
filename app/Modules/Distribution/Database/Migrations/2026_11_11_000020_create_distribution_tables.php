<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->string('phone', 30)->nullable();
            $table->string('layout', 20)->nullable();           // matrix | lineitem | monthly
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('territories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('medical_reps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('territory_id')->constrained();
            $table->string('phone', 30)->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Built from the monthly files. match_key = the distributor's customer code, or
        // normalised name + area when the layout prints no codes.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained();
            $table->string('code', 30)->nullable();
            $table->string('name');
            $table->string('area')->nullable();
            $table->string('match_key');
            $table->date('first_seen');
            $table->date('last_seen');
            $table->timestamps();
            $table->unique(['distributor_id', 'match_key']);
        });

        Schema::create('customer_medical_rep', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medical_rep_id')->constrained();
            $table->unsignedTinyInteger('share_percent')->default(100);
            $table->timestamps();
            $table->unique(['customer_id', 'medical_rep_id']);
        });

        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained();
            $table->string('name_in_file');
            $table->foreignId('product_id')->constrained();
            $table->timestamps();
            $table->unique(['distributor_id', 'name_in_file']);
        });
    }

    public function down(): void
    {
        foreach (['product_aliases', 'customer_medical_rep', 'customers', 'medical_reps', 'territories', 'distributors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
