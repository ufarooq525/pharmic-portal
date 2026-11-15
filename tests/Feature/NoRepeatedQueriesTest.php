<?php
namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Imports\Actions\ConfirmImport;
use App\Modules\Imports\Enums\ImportStatus;
use App\Modules\Imports\Models\Import;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every page and API list runs a fixed number of queries, however many rows it shows: no query may
 * repeat per row (an N+1, usually a missing with()). Lazy loading itself already throws in tests
 * (strict mode); this also catches repository calls made inside loops.
 */
class NoRepeatedQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_repeats_a_query_per_row(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@example.com')->first();
        $products = Product::orderBy('id')->take(3)->get();
        foreach (Distributor::orderBy('id')->take(2)->get() as $d) {
            $import = Import::create(['distributor_id' => $d->id, 'period' => '2026-10-01', 'status' => ImportStatus::NeedsReview, 'layout' => 'lineitem',
                'original_name' => 'x.pdf', 'file_path' => 'imports/x.pdf', 'distributor_in_file' => $d->name, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31',
                'file_units' => 0, 'file_value' => 0, 'parsed_units' => 0, 'parsed_value' => 0, 'checks' => [], 'customers' => array_map(fn ($c) => ['code' => "C$c", 'name' => "STORE $c {$d->id}", 'area' => 'AREA'], range(1, 6)), 'uploaded_by' => $owner->id]);
            foreach (range(1, 6) as $c) foreach ($products as $p) {
                $import->rows()->create(['customer_code' => "C$c", 'customer_name' => "STORE $c {$d->id}", 'area' => 'AREA', 'trade_price' => 100, 'product_name' => $p->name, 'product_id' => $p->id, 'units' => 5, 'bonus' => 1, 'value' => 500]);
            }
            app(ConfirmImport::class)->handle($import, $owner);
            foreach (range(1, 3) as $i) $this->actingAs($owner)->post("/ledger/{$d->id}/payments", ['paid_on' => '2026-11-0'.$i, 'amount' => 100, 'method' => 'bank']);
            $this->post('/invoices', ['to' => 'd:'.$d->id, 'period' => '2026-10']);
        }
        foreach (range(1, 3) as $i) $this->post('/inventory/receipts', ['received_on' => '2026-10-1'.$i, 'reference' => "GRN-$i", 'lines' => [['product_id' => $products[0]->id, 'units' => 10, 'unit_cost' => 5]]]);
        User::factory()->count(4)->create();
        User::factory()->role('medical_rep')->create(['medical_rep_id' => MedicalRep::first()->id]);
        $reps = MedicalRep::pluck('id');
        foreach (\App\Modules\Distribution\Models\Customer::all() as $i => $cu) $this->put("/customers/{$cu->id}/reps", ['rep_1' => $reps[$i % count($reps)]]);

        $irfan = Distributor::orderBy('id')->value('id');
        $import = Import::first()->id;
        $pages = ['/dashboard?period=2026-10', '/imports?period=2026-10', "/imports/$import", '/customers?period=2026-10', '/inventory?period=2026-10',
            '/reports?period=2026-10', '/reports/customers?period=2026-10', "/invoices?period=2026-10&to=d:$irfan", '/invoices/'.\App\Modules\Billing\Models\Invoice::first()->id.'/print', "/ledger?year=2026&distributor=$irfan",
            '/payroll?period=2026-10', '/payroll/'.$reps[0].'/payslip?period=2026-10', '/products', '/inventory/'.$products[0]->id.'/card?period=2026-10', "/distributors?distributor=$irfan", '/master-data/reps',
            '/master-data/name-matching', '/users', '/users?tab=roles', '/activity', '/api/v1/customers', '/api/v1/imports?period=2026-10', '/api/v1/dashboard?period=2026-10', '/api/v1/products'];
        foreach ($pages as $url) {
            $q = [];
            DB::flushQueryLog(); DB::enableQueryLog();
            $this->get($url)->assertOk();
            foreach (DB::getQueryLog() as $l) {
                $shape = preg_replace(['/\?(, \?)+/', '/\b\d+\b/'], ['?', 'N'], $l['query']);
                $q[$shape] = ($q[$shape] ?? 0) + 1;
            }
            DB::disableQueryLog();
            arsort($q);
            $dups = array_filter($q, fn ($n) => $n > 2);
            // 2 distributors × 6 customers × 3 products: a per-row query would show up many times.
            $this->assertSame([], $dups, "$url repeats a query per row (N+1)");
        }
    }
}
