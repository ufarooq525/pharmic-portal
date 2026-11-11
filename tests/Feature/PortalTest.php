<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Services\ConfirmImport;
use App\Modules\Ledger\Services\LedgerBuilder;
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
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
            ->assertSee('data-password-toggle', false);
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
        $this->get('/users')->assertOk()->assertSee('Data scope');
        $this->get('/users?tab=permissions')->assertOk()->assertSee('Own territory');
        $this->get('/invoices?period=2026-10&to=d:'.Distributor::where('name', 'Irfan Enterprises')->value('id'))->assertOk()->assertSee('Gynofol Tab')->assertSee('Issue invoice');
        $this->get('/payroll?period=2026-10')->assertOk()->assertSee('Uzma Bibi');
        $this->get('/activity')->assertOk()->assertSee('Who did what and when');
        $this->get('/master-data/reps')->assertOk()->assertSee('Uzma Bibi');
        $this->get('/master-data/name-matching')->assertOk()->assertSee('GYNOF OL TAB (N)');
        $this->get('/ledger?year=2026&distributor='.Distributor::where('name', 'Irfan Enterprises')->value('id'))->assertOk()->assertSee('Invoice for October 2026')->assertSee('Balances for October 2026');
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

    public function test_the_ledger_debits_confirmed_sales_and_credits_payments(): void
    {
        $import = $this->import();
        app(ConfirmImport::class)->handle($import, $this->owner());
        $irfan = Distributor::where('name', 'Irfan Enterprises')->firstOrFail();
        $sales = (float) SaleLine::sum('value_tp') + (float) SaleLine::sum('sales_tax') + (float) SaleLine::sum('withholding_tax');

        $this->actingAs(User::factory()->role('accounts')->create())
            ->post("/ledger/{$irfan->id}/payments", ['paid_on' => '2026-11-05', 'amount' => 1000, 'method' => 'bank', 'reference' => 'TRX-1'])
            ->assertSessionHasNoErrors();

        $statement = app(LedgerBuilder::class)->statement($irfan->fresh(), 2026);
        $this->assertEqualsWithDelta($sales, $statement['debit'], 0.01);
        $this->assertEqualsWithDelta(1000, $statement['credit'], 0.01);
        $this->assertEqualsWithDelta($irfan->opening_balance + $sales - 1000, $statement['closing'], 0.01);

        // Undoing the import takes its sales back out of the ledger.
        app(ConfirmImport::class)->reverse($import->fresh());
        $this->assertEqualsWithDelta(0, app(LedgerBuilder::class)->statement($irfan->fresh(), 2026)['debit'], 0.01);

        $this->actingAs(User::factory()->role('viewer')->create())->post("/ledger/{$irfan->id}/payments", [])->assertForbidden();
    }

    public function test_a_wrong_file_shows_a_warning_and_can_be_discarded(): void
    {
        $import = $this->import();
        $import->update(['status' => Import::BLOCKED, 'checks' => [['key' => 'distributor', 'ok' => false, 'message' => 'The file header reads “HAMDAN ENTERPRISES”, not Irfan Enterprises.']]]);
        $this->actingAs($this->owner());

        $this->get("/imports/{$import->id}")->assertOk()->assertSee('This looks like the wrong file')->assertSee('HAMDAN ENTERPRISES');
        $this->delete("/imports/{$import->id}")->assertRedirect();

        $this->assertModelMissing($import);
    }

    public function test_a_confirmed_import_cannot_be_discarded(): void
    {
        $import = $this->import();
        app(ConfirmImport::class)->handle($import, $this->owner());

        $this->actingAs($this->owner())->delete("/imports/{$import->id}")->assertStatus(422);
    }

    public function test_a_stock_receipt_adds_stock_and_can_be_reverted(): void
    {
        $gyn = Product::where('code', 'GYN')->firstOrFail();
        $accounts = User::factory()->role('accounts')->create();
        $this->actingAs($accounts);

        $this->post('/inventory/receipts', ['received_on' => '2026-10-15', 'reference' => 'GRN-1', 'lines' => [['product_id' => $gyn->id, 'units' => 500, 'unit_cost' => 74]]])
            ->assertSessionHasNoErrors();
        $receipt = \App\Modules\Sales\Models\StockReceipt::firstOrFail();
        $this->assertEqualsWithDelta(3294 + 500, (float) \App\Modules\Sales\Models\StockMovement::where('product_id', $gyn->id)->sum('units'), 0.01);

        $this->post("/inventory/receipts/{$receipt->id}/revert")->assertSessionHasNoErrors();
        $this->assertSame('reverted', $receipt->fresh()->status);
        $this->assertEqualsWithDelta(3294, (float) \App\Modules\Sales\Models\StockMovement::where('product_id', $gyn->id)->sum('units'), 0.01);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Reverted stock receipt']);

        $this->actingAs(User::factory()->role('viewer')->create())->post('/inventory/receipts', [])->assertForbidden();
    }

    public function test_a_stock_csv_with_an_unknown_product_is_rejected_whole(): void
    {
        $csv = \Illuminate\Http\UploadedFile::fake()->createWithContent('stock.csv', "product,units,unit_cost\nGYN,100,74\nNOPE,5,1\n");

        $this->actingAs(User::factory()->role('accounts')->create())
            ->post('/inventory/receipts', ['received_on' => '2026-10-15', 'file' => $csv])
            ->assertSessionHasErrors('receipt');

        $this->assertSame(0, \App\Modules\Sales\Models\StockReceipt::count());
    }

    public function test_an_invoice_is_issued_locked_and_cancelled_with_a_credit_note(): void
    {
        app(ConfirmImport::class)->handle($this->import(), $this->owner());
        $to = 'd:'.Distributor::where('name', 'Irfan Enterprises')->value('id');
        $this->actingAs($this->owner());

        $this->post('/invoices', ['to' => $to, 'period' => '2026-10'])->assertSessionHasNoErrors();
        $invoice = \App\Modules\Billing\Models\Invoice::firstOrFail();
        $this->assertSame('INV-2610-0001', $invoice->number);
        $this->assertEqualsWithDelta(3336 + 16.68, $invoice->total, 0.01);
        $this->post('/invoices', ['to' => $to, 'period' => '2026-10'])->assertSessionHasErrors('invoice');

        $this->get("/invoices/{$invoice->id}/print")->assertOk()->assertSee('INV-2610-0001');

        $this->post("/invoices/{$invoice->id}/cancel", ['reason' => 'Wrong month'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertDatabaseHas('invoices', ['number' => 'CN-2610-0001', 'total' => -1 * round($invoice->total, 2)]);
    }

    public function test_a_medical_rep_sees_only_their_territory(): void
    {
        app(ConfirmImport::class)->handle($this->import(), $this->owner());
        $rep = MedicalRep::orderBy('id')->firstOrFail();
        Customer::firstOrFail()->medicalReps()->sync([$rep->id => ['share_percent' => 100]]);
        $user = User::factory()->create(['role' => 'medical_rep', 'medical_rep_id' => $rep->id]);
        $this->actingAs($user);

        $this->get('/dashboard')->assertRedirect(route('my.index'));
        $this->get('/my-territory?period=2026-10')->assertOk()->assertSee('ZAMAN MEDICAL STORE');
        $this->get("/payroll/{$rep->id}/payslip?period=2026-10")->assertOk()->assertSee('Net pay');
        $this->get('/imports')->assertForbidden();
        $other = MedicalRep::where('id', '!=', $rep->id)->firstOrFail();
        $this->get("/payroll/{$other->id}/payslip")->assertForbidden();
    }

    public function test_a_user_limited_to_one_distributor_cannot_open_anothers_file(): void
    {
        $import = $this->import();
        $hamdan = Distributor::where('name', 'Hamdan Enterprises')->value('id');
        $this->actingAs(User::factory()->role('accounts')->create(['distributor_ids' => [$hamdan]]));

        $this->get("/imports/{$import->id}")->assertForbidden();
        $this->get('/imports')->assertOk()->assertDontSee('Irfan Enterprises');
    }
}
