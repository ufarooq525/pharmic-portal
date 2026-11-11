<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Services\ConfirmImport;
use App\Modules\Sales\Models\SaleLine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** An Irfan Enterprises file for October 2026: one sale with a bonus, one return, one unknown spelling. */
    private function import(bool $withUnknownProduct = false): Import
    {
        $irfan = Distributor::where('name', 'Irfan Enterprises')->firstOrFail();
        $gyn = Product::where('code', 'GYN')->value('id');

        $import = Import::create([
            'distributor_id' => $irfan->id, 'period' => '2026-10-01', 'status' => Import::NEEDS_REVIEW, 'layout' => 'lineitem',
            'original_name' => 'LH COSTUMER.PDF', 'file_path' => 'imports/2026-10/test.pdf', 'distributor_in_file' => 'IRFAN ENTERPRISES Timergarah',
            'period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'file_units' => 8, 'file_value' => 3336, 'parsed_units' => 8, 'parsed_value' => 3336,
            'checks' => [['key' => 'totals', 'ok' => true, 'message' => 'Units and value match the grand total printed in the file.']],
            'customers' => [['code' => '101', 'name' => 'ZAMAN MEDICAL STORE', 'area' => 'TIMERGARA']],
            'uploaded_by' => User::where('role', 'owner')->value('id'),
        ]);

        $row = ['customer_code' => '101', 'customer_name' => 'ZAMAN MEDICAL STORE', 'area' => 'TIMERGARA', 'trade_price' => 417];
        $import->rows()->create($row + ['product_name' => 'GYNOFOL TAB', 'product_id' => $gyn, 'units' => 10, 'bonus' => 2, 'value' => 4170]);
        $import->rows()->create($row + ['product_name' => 'GYNOFOL TAB', 'product_id' => $gyn, 'units' => -2, 'bonus' => 0, 'value' => -834]);

        if ($withUnknownProduct) {
            $import->rows()->create($row + ['product_name' => 'EQUAT FLORA', 'product_id' => null, 'units' => 0, 'bonus' => 0, 'value' => 0]);
        }

        return $import;
    }

    private function owner(): User
    {
        return User::where('role', 'owner')->firstOrFail();
    }

    public function test_guests_are_sent_to_login_and_nothing_is_indexable(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertRedirect('/login');

        $this->get('/login')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noai, noimageai')
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false);
    }

    public function test_active_users_sign_in_and_deactivated_users_cannot(): void
    {
        User::factory()->role('accounts')->create(['email' => 'acc@example.com']);
        User::factory()->role('viewer')->create(['email' => 'off@example.com', 'is_active' => false]);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'acc@example.com', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_confirming_posts_sales_taxes_stock_and_customers(): void
    {
        $import = $this->import();
        app(ConfirmImport::class)->handle($import, $this->owner());

        $this->assertSame(Import::CONFIRMED, $import->fresh()->status);
        $this->assertSame(1, Customer::count());
        $this->assertSame(2, SaleLine::count());

        // 10 sold + 2 bonus out, 2 returned in: 3294 opening - 12 + 2 = 3284.
        $this->actingAs($this->owner())->get('/inventory?period=2026-10')->assertOk()->assertSee('3,284');

        // Gynofol: 0% sales tax, 0.5% withholding tax on the trade-price value (4170 - 834 = 3336).
        $this->assertEqualsWithDelta(16.68, (float) SaleLine::sum('withholding_tax'), 0.01);
        $this->assertEqualsWithDelta(0.0, (float) SaleLine::sum('sales_tax'), 0.01);
    }

    public function test_every_page_renders_for_the_owner(): void
    {
        app(ConfirmImport::class)->handle($this->import(), $this->owner());
        $this->actingAs($this->owner());

        $this->get('/dashboard?period=2026-10')->assertOk()->assertSee('October 2026')->assertSee('Needs your attention');
        $this->get('/imports?period=2026-10')->assertOk()->assertSee('Irfan Enterprises');
        $this->get('/imports/'.Import::first()->id)->assertOk()->assertSee('ZAMAN MEDICAL STORE');
        $this->get('/customers?period=2026-10')->assertOk()->assertSee('ZAMAN MEDICAL STORE');
        $this->get('/inventory?period=2026-10')->assertOk()->assertSee('Gynofol Tab');
        $this->get('/products?period=2026-10')->assertOk()->assertSee('withholding tax rate');
        $this->get('/users')->assertOk()->assertSee('What each role can do');
        $this->get('/distributors?distributor='.Distributor::where('name', 'Irfan Enterprises')->value('id'))->assertOk()
            ->assertSee('IRFAN ENTERPRISES')->assertSee('GYNOFOL TAB')->assertSee('955');

        foreach (['sales', 'customers', 'bonus', 'incentive', 'taxes'] as $type) {
            $this->get("/reports/{$type}?period=2026-10")->assertOk();
        }

        $this->get('/reports/sales/export?period=2026-10')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_an_unknown_product_blocks_confirm_until_it_is_linked_and_the_link_is_remembered(): void
    {
        $import = $this->import(withUnknownProduct: true);
        $this->actingAs($this->owner());

        $this->get("/imports/{$import->id}")->assertOk()->assertSee('EQUAT FLORA')->assertDontSee('Confirm and post');

        $this->post("/imports/{$import->id}/map", ['product_name' => 'EQUAT FLORA', 'product_id' => Product::where('code', 'EQU')->value('id')])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_aliases', ['name_in_file' => 'EQUAT FLORA']);
        $this->get("/imports/{$import->id}")->assertSee('Confirm and post');
    }

    public function test_roles_limit_what_each_person_can_do(): void
    {
        $import = $this->import();

        $this->actingAs(User::factory()->role('data_entry')->create())->post("/imports/{$import->id}/confirm")->assertForbidden();
        $this->actingAs(User::factory()->role('viewer')->create())->post('/imports', [])->assertForbidden();
        $this->actingAs(User::factory()->role('accounts')->create())->get('/users')->assertForbidden();

        $this->actingAs(User::factory()->role('accounts')->create())->post("/imports/{$import->id}/confirm")->assertRedirect();
        $this->assertSame(Import::CONFIRMED, $import->fresh()->status);
    }

    public function test_two_reps_share_a_customer_fifty_fifty(): void
    {
        app(ConfirmImport::class)->handle($this->import(), $this->owner());
        [$a, $b] = MedicalRep::orderBy('id')->take(2)->pluck('id')->all();
        $customer = Customer::firstOrFail();

        $this->actingAs($this->owner())->put("/customers/{$customer->id}/reps", ['rep_1' => $a, 'rep_2' => $b])->assertSessionHasNoErrors();

        $this->assertSame([50, 50], $customer->medicalReps()->pluck('share_percent')->map(fn ($v) => (int) $v)->all());
    }

    public function test_bonus_follows_each_distributors_setting(): void
    {
        $irfan = Distributor::where('name', 'Irfan Enterprises')->firstOrFail();
        $irfan->update(['bonus_source' => Distributor::BONUS_SCHEME]);
        $scheme = Product::where('code', 'GYN')->firstOrFail()->rateOn(now()->setDate(2026, 10, 31))->schemeBonus(10);

        app(ConfirmImport::class)->handle($this->import(), $this->owner());

        $sale = SaleLine::where('units', 10)->firstOrFail();
        $this->assertEqualsWithDelta($scheme, (float) $sale->bonus_units, 0.01);
        $this->assertFalse((bool) $sale->bonus_from_file);

        $this->assertSame(2.0, (new Distributor(['bonus_source' => Distributor::BONUS_FILE]))->bonusFor(2.0, 5));
        $this->assertSame(5.0, (new Distributor(['bonus_source' => Distributor::BONUS_FILE]))->bonusFor(null, 5));
        $this->assertSame(0.0, (new Distributor(['bonus_source' => Distributor::BONUS_NONE]))->bonusFor(null, 5));
    }

    public function test_the_header_check_uses_the_saved_name_and_the_first_file_saves_the_format(): void
    {
        $irfan = Distributor::where('name', 'Irfan Enterprises')->firstOrFail();
        $this->assertTrue($irfan->headerMatches('IRFAN  ENTERPRISES Timergarah'));
        $this->assertFalse($irfan->headerMatches('HAMDAN ENTERPRISES'));

        $irfan->update(['layout' => null, 'header_name' => null]);
        app(ConfirmImport::class)->handle($this->import(), $this->owner());

        $irfan->refresh();
        $this->assertSame('lineitem', $irfan->layout);
        $this->assertSame('IRFAN ENTERPRISES Timergarah', $irfan->header_name);
    }

    public function test_a_link_keeps_the_distributors_item_code(): void
    {
        $import = $this->import(withUnknownProduct: true);
        $import->rows()->where('product_name', 'EQUAT FLORA')->update(['product_code' => '999']);

        $this->actingAs($this->owner())
            ->post("/imports/{$import->id}/map", ['product_name' => 'EQUAT FLORA', 'product_id' => Product::where('code', 'EQU')->value('id')])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_aliases', ['name_in_file' => 'EQUAT FLORA', 'code_in_file' => '999']);
    }

    public function test_only_the_owner_edits_a_distributor_profile(): void
    {
        $irfan = Distributor::where('name', 'Irfan Enterprises')->firstOrFail();
        $profile = ['name' => 'Irfan Enterprises', 'city' => 'Timergara', 'header_name' => 'IRFAN ENTERPRISES', 'layout' => 'lineitem', 'bonus_source' => 'none'];

        $this->actingAs(User::factory()->role('accounts')->create())->put("/distributors/{$irfan->id}", $profile)->assertForbidden();
        $this->actingAs($this->owner())->put("/distributors/{$irfan->id}", $profile)->assertSessionHasNoErrors();

        $this->assertSame('none', $irfan->fresh()->bonus_source);
    }
}
