<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed roles (a column on users) become roles managed in the portal, with permissions ticked per
 * role and optional extras per person. Everyone keeps exactly the access they had: the four staff
 * roles are created with the permissions their old route checks allowed, and medical reps become
 * the "medical rep" login type. The permission lists are frozen here on purpose.
 */
return new class extends Migration
{
    private const ROLES = [
        'owner' => ['Owner', 'Can do everything. Cannot be changed or deleted.', true, []],
        'accounts' => ['Accounts', 'Confirms imports, records payments, issues invoices and runs payroll.', false, [
            'dashboard.view', 'imports.view', 'imports.upload', 'imports.confirm', 'customers.view', 'customers.assign',
            'distributors.view', 'reps.view', 'reps.edit', 'products.view', 'stock.view', 'stock.receive', 'stock.revert',
            'ledger.view', 'ledger.record', 'invoices.view', 'invoices.issue', 'payroll.view', 'payroll.adjust', 'activity.view',
        ]],
        'data_entry' => ['Data entry', 'Uploads and checks distributor files and assigns reps to customers.', false, [
            'dashboard.view', 'imports.view', 'imports.upload', 'customers.view', 'customers.assign',
            'distributors.view', 'reps.view', 'products.view', 'stock.view',
        ]],
        'viewer' => ['Viewer', 'Can look at everything except invoices and payroll; cannot change anything.', false, [
            'dashboard.view', 'imports.view', 'customers.view', 'distributors.view', 'reps.view', 'products.view',
            'stock.view', 'ledger.view',
        ]],
    ];

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 150)->nullable();
            $table->boolean('is_owner')->default(false);
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 40);
            $table->primary(['role_id', 'permission']);
        });

        Schema::create('user_permission', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 40);
            $table->primary(['user_id', 'permission']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('login_type', 20)->default('staff')->after('email');
            $table->foreignId('role_id')->nullable()->after('login_type')->constrained()->restrictOnDelete();
        });

        $now = now();
        $ids = [];
        foreach (self::ROLES as $key => [$name, $description, $isOwner, $permissions]) {
            $ids[$key] = DB::table('roles')->insertGetId(['name' => $name, 'description' => $description, 'is_owner' => $isOwner, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('role_permission')->insert(array_map(fn ($p) => ['role_id' => $ids[$key], 'permission' => $p], $permissions));
        }

        foreach ($ids as $key => $id) {
            DB::table('users')->where('role', $key)->update(['login_type' => 'staff', 'role_id' => $id]);
        }
        DB::table('users')->where('role', 'medical_rep')->update(['login_type' => 'medical_rep', 'role_id' => null]);
        // Anything unexpected falls back to the least access, never more.
        DB::table('users')->where('login_type', 'staff')->whereNull('role_id')->update(['role_id' => $ids['viewer']]);

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('role', 20)->default('viewer')->after('email'));

        $byName = array_flip(array_map(fn ($r) => $r[0], self::ROLES));
        foreach (DB::table('roles')->get() as $role) {
            $old = $role->is_owner ? 'owner' : ($byName[$role->name] ?? 'viewer');
            DB::table('users')->where('role_id', $role->id)->update(['role' => $old]);
        }
        DB::table('users')->where('login_type', 'medical_rep')->update(['role' => 'medical_rep']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('login_type');
        });
        Schema::dropIfExists('user_permission');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
    }
};
