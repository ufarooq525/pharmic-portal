<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A medical rep's login is tied to their rep record and sees only their own customers.
            $table->foreignId('medical_rep_id')->nullable()->after('is_active')->constrained()->nullOnDelete();
            // Other roles can be limited to some distributors; null means all.
            $table->json('distributor_ids')->nullable()->after('medical_rep_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('medical_rep_id');
            $table->dropColumn('distributor_ids');
        });
    }
};
