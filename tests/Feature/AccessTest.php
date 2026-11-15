<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\UserPermission;
use App\Modules\Distribution\Models\MedicalRep;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Users, roles and permissions: nobody's access changed in the switch, and the safety rules hold. */
class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@example.com')->firstOrFail();
    }

    private function role(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }

    /** A role with exactly these permissions. */
    private function makeRole(string $name, array $permissions): Role
    {
        $role = Role::create(['name' => $name]);
        DB::table('role_permission')->insert(array_map(fn ($p) => ['role_id' => $role->id, 'permission' => $p], $permissions));

        return $role;
    }

    /**
     * tests/Fixtures/route-access-before.json was taken from the old role: middleware before the switch:
     * for every guarded route, which roles could use it (the owner could use all). Every seeded role
     * must open exactly the same routes now.
     */
    public function test_every_role_can_use_exactly_the_routes_it_could_before(): void
    {
        $before = json_decode(file_get_contents(base_path('tests/Fixtures/route-access-before.json')), true);
        // Agreed in the design: one permission for assigning reps, so Data entry may now do it through the API too.
        $intended = ['PUT api/v1/customers/{customer}/reps' => ['data_entry']];

        $routes = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                $routes["$method ".$route->uri()] = $route;
            }
        }

        $people = [
            'owner' => User::factory()->role('owner')->make(),
            'accounts' => User::factory()->role('accounts')->make(),
            'data_entry' => User::factory()->role('data_entry')->make(),
            'viewer' => User::factory()->role('viewer')->make(),
            'medical_rep' => User::factory()->role('medical_rep')->make(),
        ];

        foreach ($before as $key => $allowedBefore) {
            $this->assertArrayHasKey($key, $routes, "Route $key disappeared.");
            $needs = array_map(fn ($m) => substr($m, strlen('permission:')), array_filter($routes[$key]->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'permission:')));
            $this->assertNotEmpty($needs, "Route $key lost its permission check.");

            foreach ($people as $role => $user) {
                $expected = $role === 'owner' || in_array($role, $allowedBefore, true) || in_array($role, $intended[$key] ?? [], true);
                $actual = collect($needs)->every(fn ($p) => $user->hasPermission($p));
                $this->assertSame($expected, $actual, "$role on $key: expected ".($expected ? 'allowed' : 'refused'));
            }
        }
    }

    public function test_permissions_come_from_the_role_extras_or_being_owner(): void
    {
        $viewer = User::factory()->role('viewer')->create();
        $this->assertTrue($viewer->hasPermission(Permission::LedgerView));
        $this->assertFalse($viewer->hasPermission(Permission::LedgerRecord));

        UserPermission::insert(['user_id' => $viewer->id, 'permission' => 'ledger.record']);
        $this->assertTrue($viewer->fresh()->hasPermission(Permission::LedgerRecord), 'an extra adds to the role');
        $this->assertTrue($viewer->fresh()->can('ledger.record'), 'gate abilities read the same permissions');

        $this->assertSame(Permission::values(), $this->owner()->permissionValues());

        $rep = User::factory()->role('medical_rep')->create(['medical_rep_id' => MedicalRep::first()->id]);
        UserPermission::insert(['user_id' => $rep->id, 'permission' => 'ledger.view']);
        $this->assertSame([], $rep->fresh()->permissionValues(), 'a medical rep login never gets staff permissions');
    }

    public function test_a_custom_role_decides_pages_and_the_sidebar(): void
    {
        $this->actingAs($this->owner())->post('/users/roles', ['name' => 'Stock clerk', 'permissions' => ['stock.receive']])
            ->assertRedirect(route('users.index', ['tab' => 'roles']));

        $role = $this->role('Stock clerk');
        $this->assertEqualsCanonicalizing(['stock.view', 'stock.receive'], $role->permissionValues(), 'an action brings its view');

        $clerk = User::factory()->withRole($role)->create();
        $this->actingAs($clerk);
        $this->get('/dashboard')->assertRedirect(route('inventory.index'));
        $this->get('/inventory')->assertOk()->assertSee('Inventory')->assertDontSee('Distributor ledger');
        $this->get('/ledger')->assertForbidden();
        $this->get('/users')->assertForbidden();

        $empty = User::factory()->withRole($this->makeRole('Nothing yet', []))->create();
        $this->actingAs($empty)->get('/dashboard')->assertForbidden();
    }

    public function test_an_extra_permission_is_saved_on_the_person_only(): void
    {
        $viewer = User::factory()->role('viewer')->create();
        $this->actingAs($this->owner())->put("/users/{$viewer->id}", [
            'is_active' => 1, 'login_type' => 'staff', 'role_id' => $this->role('Viewer')->id,
            'permissions' => ['ledger.record', 'ledger.view'], // ledger.view comes from the role, so only the extra is stored
        ])->assertRedirect(route('users.index'));

        $this->assertSame(['ledger.record'], $viewer->fresh()->extraPermissionValues());
        $this->assertNotContains('ledger.record', $this->role('Viewer')->permissionValues());
        $this->assertDatabaseHas('activity_logs', ['action' => 'Changed user']);
    }

    public function test_nobody_can_give_more_than_they_have(): void
    {
        $manager = User::factory()->withRole($this->makeRole('Office manager', ['users.manage', 'ledger.view', 'ledger.record']))->create();
        $this->actingAs($manager);

        $this->post('/users/roles', ['name' => 'Sneaky', 'permissions' => ['invoices.issue']])->assertSessionHasErrors('access');
        $this->post('/users/roles', ['name' => 'Cashier', 'permissions' => ['ledger.record']])->assertSessionHasNoErrors();

        $person = ['name' => 'New', 'email' => 'new@example.com', 'password' => 'long-enough-pass', 'login_type' => 'staff'];
        $this->post('/users', $person + ['role_id' => $this->role('Owner')->id])->assertSessionHasErrors('access');
        $this->post('/users', $person + ['role_id' => $this->role('Accounts')->id])->assertSessionHasErrors('access');
        $this->post('/users', $person + ['role_id' => $this->role('Cashier')->id, 'permissions' => ['payroll.view']])->assertSessionHasErrors('access');
        $this->post('/users', $person + ['role_id' => $this->role('Cashier')->id])->assertSessionHasNoErrors();

        $this->put('/users/'.$this->owner()->id, ['is_active' => 0, 'login_type' => 'staff', 'role_id' => $this->role('Owner')->id])->assertSessionHasErrors('access');
        $this->assertTrue($this->owner()->is_active);
    }

    public function test_there_is_always_an_active_owner(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $ownerRole = $this->role('Owner')->id;

        $this->put("/users/{$owner->id}", ['is_active' => 0, 'login_type' => 'staff', 'role_id' => $ownerRole])->assertSessionHasErrors('access');
        $this->put("/users/{$owner->id}", ['is_active' => 1, 'login_type' => 'staff', 'role_id' => $this->role('Accounts')->id])->assertSessionHasErrors('access');
        $this->assertTrue($owner->fresh()->isOwner());

        $second = User::factory()->role('owner')->create();
        $this->put("/users/{$second->id}", ['is_active' => 0, 'login_type' => 'staff', 'role_id' => $ownerRole])->assertSessionHasNoErrors();
        $this->assertFalse($second->fresh()->is_active, 'another owner can be deactivated while one stays active');
    }

    public function test_the_owner_role_and_roles_in_use_are_protected(): void
    {
        $this->actingAs($this->owner());
        $owner = $this->role('Owner');

        $this->put("/users/roles/{$owner->id}", ['name' => 'Boss', 'permissions' => []])->assertSessionHasErrors('access');
        $this->delete("/users/roles/{$owner->id}")->assertSessionHasErrors('access');
        $this->assertSame('Owner', $owner->fresh()->name);

        User::factory()->role('viewer')->create();
        $this->delete('/users/roles/'.$this->role('Viewer')->id)->assertSessionHasErrors('access');
        $this->assertDatabaseHas('roles', ['name' => 'Viewer']);

        $unused = $this->makeRole('Temporary', ['stock.view']);
        $this->delete("/users/roles/{$unused->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('roles', ['name' => 'Temporary']);
        $this->assertDatabaseMissing('role_permission', ['role_id' => $unused->id]);

        $this->post('/users/roles', ['name' => 'Accounts'])->assertSessionHasErrors('name');
    }

    public function test_changing_a_role_is_logged_with_what_was_added_and_removed(): void
    {
        $this->actingAs($this->owner())->put('/users/roles/'.$this->role('Viewer')->id, [
            'name' => 'Viewer',
            'permissions' => array_values(array_diff([...$this->role('Viewer')->permissionValues(), 'invoices.view'], ['ledger.view'])),
        ])->assertSessionHasNoErrors();

        $detail = DB::table('activity_logs')->where('action', 'Changed role')->value('detail');
        $this->assertStringContainsString('added View and print invoices', $detail);
        $this->assertStringContainsString('removed View and export ledger', $detail);
    }

    public function test_medical_rep_logins_keep_their_territory_only(): void
    {
        $this->actingAs($this->owner())->post('/users', [
            'name' => 'Uzma', 'email' => 'uzma@example.com', 'password' => 'long-enough-pass',
            'login_type' => 'medical_rep', 'medical_rep_id' => MedicalRep::first()->id,
            'role_id' => $this->role('Accounts')->id, 'permissions' => ['ledger.view'], // ignored for a medical rep
        ])->assertSessionHasNoErrors();

        $uzma = User::where('email', 'uzma@example.com')->firstOrFail();
        $this->assertTrue($uzma->isMedicalRep());
        $this->assertNull($uzma->role_id);
        $this->assertSame([], $uzma->extraPermissionValues());

        $this->actingAs($uzma)->get('/dashboard')->assertRedirect(route('my.index'));
        $this->get('/ledger')->assertForbidden();
    }

    public function test_the_migration_keeps_everyones_access_and_can_be_undone(): void
    {
        $accounts = User::factory()->role('accounts')->create();
        $rep = User::factory()->role('medical_rep')->create(['medical_rep_id' => MedicalRep::first()->id]);

        Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertSame('accounts', DB::table('users')->where('id', $accounts->id)->value('role'));
        $this->assertSame('medical_rep', DB::table('users')->where('id', $rep->id)->value('role'));
        $this->assertSame('owner', DB::table('users')->where('email', 'owner@example.com')->value('role'));

        Artisan::call('migrate');
        $this->assertSame('Accounts', User::find($accounts->id)->role->name);
        $this->assertTrue(User::find($rep->id)->isMedicalRep());
        $this->assertTrue($this->owner()->isOwner());
    }
}
