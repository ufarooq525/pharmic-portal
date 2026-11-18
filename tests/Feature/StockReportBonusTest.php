<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Enums\BonusSource;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Actions\ConfirmImport;
use App\Modules\Imports\Enums\ImportStatus;
use App\Modules\Imports\Models\Import;
use App\Modules\Sales\Models\SaleLine;
use App\Modules\Sales\Models\StockMovement;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A product-grid distributor: no bonus in the customer file, so the Sales & Stock report's bonus is shared out. */
class StockReportBonusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function owner(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('is_owner', true))->firstOrFail();
    }

    /** Hamdan, September 2026: three customers buy GYNOFOL (60, 20, 2 units, one returns 2), no bonus printed. */
    private function hamdanImport(): Import
    {
        $hamdan = Distributor::where('name', 'Hamdan Enterprises')->firstOrFail();
        $hamdan->update(['bonus_source' => BonusSource::Allocated]);
        $gyn = Product::where('code', 'GYN')->value('id');

        $import = Import::create([
            'distributor_id' => $hamdan->id, 'period' => '2026-09-01', 'status' => ImportStatus::NeedsReview, 'layout' => 'matrix',
            'original_name' => 'hamdan sep.PDF', 'file_path' => 'imports/2026-09/hamdan.pdf', 'distributor_in_file' => 'HAMDAN ENTERPRISES',
            'period_from' => '2026-09-01', 'period_to' => '2026-09-30', 'file_units' => 80, 'file_value' => null, 'parsed_units' => 80, 'parsed_value' => null,
            'checks' => [['key' => 'totals', 'ok' => true, 'message' => 'Units match the grand total printed in the file.']],
            'customers' => [['name' => 'AKBAR M/C JIAL ROAD', 'area' => 'MMC'], ['name' => 'DR FAZAL NAWE KALE', 'area' => 'SHAWA ADDA'], ['name' => 'MALIKA M/S SHERGARH', 'area' => 'SHERGHAR']],
            'uploaded_by' => $this->owner()->id,
        ]);

        foreach ([['AKBAR M/C JIAL ROAD', 'MMC', 60], ['DR FAZAL NAWE KALE', 'SHAWA ADDA', 20], ['MALIKA M/S SHERGARH', 'SHERGHAR', 2], ['MALIKA M/S SHERGARH', 'SHERGHAR', -2]] as [$name, $area, $units]) {
            $import->rows()->create(['customer_name' => $name, 'area' => $area, 'product_name' => 'GYNOF OL TAB', 'product_id' => $gyn, 'units' => $units, 'bonus' => null]);
        }

        return $import;
    }

    /** What AttachStockReport keeps: one product row of the report. */
    private function report(float $netUnits, float $netBonus, array $extra = []): array
    {
        return ['items' => [['code' => '46', 'name' => 'GYNOFOL TAB', 'trade_price' => 408, 'sale_units' => $netUnits + 2, 'sale_bonus' => $netBonus, 'return_units' => 2,
            'return_bonus' => 0, 'net_units' => $netUnits, 'net_bonus' => $netBonus, 'closing_units' => 17], ...$extra],
            'printed' => null, 'distributor_in_file' => 'HAMDAN ENTERPRISES', 'period_from' => '2026-09-01', 'period_to' => '2026-09-30'];
    }

    public function test_the_reports_bonus_is_shared_out_and_posted_as_an_estimate(): void
    {
        $import = $this->hamdanImport();
        // The matrix spells it "GYNOF OL TAB", the report "GYNOFOL TAB": matched with spaces ignored.
        $import->update(['stock_report' => $this->report(80, 24), 'stock_report_checks' => []]);

        $this->actingAs($this->owner())->get("/imports/{$import->id}")->assertOk()
            ->assertSee('Sales &amp; Stock report · bonus', false)->assertSee('Units match the Sales &amp; Stock report for every product.', false)
            ->assertSee('Matches')->assertSee('est.');

        app(ConfirmImport::class)->handle($import->fresh(), $this->owner());

        $lines = SaleLine::orderBy('id')->get();
        $this->assertSame(24.0, (float) $lines->sum('bonus_units'), 'The month’s bonus equals the report.');
        $this->assertSame(['allocated'], $lines->pluck('bonus_rule')->unique()->values()->all());
        $this->assertSame(24.0, (float) $lines->first()->bonus_target);
        $this->assertSame(0.0, (float) $lines->firstWhere('units', -2)->bonus_units, 'A return gets no share.');
        $this->assertGreaterThan($lines->firstWhere('units', 20)->bonus_units, $lines->firstWhere('units', 60)->bonus_units);
        $this->assertFalse((bool) $lines->first()->bonus_from_file);

        // Units and bonus both leave stock.
        $this->assertEqualsWithDelta(-(80 + 24), (float) StockMovement::where('import_id', $import->id)->sum('units'), 0.01);
    }

    public function test_a_report_that_disagrees_only_warns(): void
    {
        $import = $this->hamdanImport();
        $import->update(['stock_report' => $this->report(85, 20), 'stock_report_checks' => []]);

        $this->actingAs($this->owner())->get("/imports/{$import->id}")->assertOk()
            ->assertSee('Units differ from the Sales &amp; Stock report', false)->assertSee('Confirm and post');

        app(ConfirmImport::class)->handle($import->fresh(), $this->owner());
        $this->assertSame(ImportStatus::Confirmed, $import->fresh()->status);
        $this->assertSame(20.0, (float) SaleLine::sum('bonus_units'));
    }

    public function test_without_a_report_the_scheme_is_used_and_a_warning_shows(): void
    {
        $import = $this->hamdanImport();

        $this->actingAs($this->owner())->get("/imports/{$import->id}")->assertOk()
            ->assertSee('No Sales &amp; Stock report yet', false)->assertSee('Upload the Sales &amp; Stock report for September 2026', false);

        app(ConfirmImport::class)->handle($import->fresh(), $this->owner());
        $this->assertSame(['scheme'], SaleLine::pluck('bonus_rule')->unique()->values()->all());
    }

    public function test_a_report_product_that_matches_nothing_must_be_linked_first(): void
    {
        $import = $this->hamdanImport();
        $extra = ['code' => '697', 'name' => 'PROVIEM MILK', 'trade_price' => 637.5, 'sale_units' => 10, 'sale_bonus' => 2, 'return_units' => 0,
            'return_bonus' => 0, 'net_units' => 10, 'net_bonus' => 2, 'closing_units' => 0];
        $import->update(['stock_report' => $this->report(80, 24, [$extra]), 'stock_report_checks' => []]);
        $this->actingAs($this->owner());

        $this->get("/imports/{$import->id}")->assertOk()->assertSee('“PROVIEM MILK”', false)->assertDontSee('Confirm and post');
        $this->expectExceptionMessage('Link the Sales & Stock report’s “PROVIEM MILK”');

        try {
            app(ConfirmImport::class)->handle($import->fresh(), $this->owner());
        } finally {
            // Linking it keeps the report's item code, and the warning names the bonus nobody bought.
            $this->post("/imports/{$import->id}/map", ['product_name' => 'PROVIEM MILK', 'product_id' => Product::where('code', '!=', 'GYN')->value('id')])->assertRedirect();
            $this->assertDatabaseHas('product_aliases', ['name_in_file' => 'PROVIEM MILK', 'code_in_file' => '697']);
            $this->get("/imports/{$import->id}")->assertSee('Bonus with no customer to give it to: 2 of PROVIEM MILK');
        }
    }

    public function test_attaching_a_file_that_is_not_a_stock_report_is_refused_and_nothing_is_kept(): void
    {
        Storage::fake('local');
        $import = $this->hamdanImport();
        if (! is_file($sample = rtrim((string) getenv('PHARMIC_SAMPLES'), '/\\').'/Hamdan ent.Mardan/hamdan sep.PDF')) {
            $this->markTestSkipped('Set PHARMIC_SAMPLES to the folder with the distributor PDFs.');
        }

        $this->actingAs($this->owner())->post("/imports/{$import->id}/stock-report", ['stock_report' => new UploadedFile($sample, 'hamdan sep.PDF', 'application/pdf', null, true)])
            ->assertSessionHasErrors('stock_report');

        $this->assertNull($import->fresh()->stock_report);
        $this->assertSame([], Storage::allFiles());
    }

    public function test_attaching_the_real_report_reads_it_and_checks_it(): void
    {
        Storage::fake('local');
        $import = $this->hamdanImport();
        if (! is_file($sample = rtrim((string) getenv('PHARMIC_SAMPLES'), '/\\').'/Hamdan ent.Mardan/CLOSING SSR (1).PDF')) {
            $this->markTestSkipped('Set PHARMIC_SAMPLES to the folder with the distributor PDFs.');
        }

        $this->actingAs($this->owner())->post("/imports/{$import->id}/stock-report", ['stock_report' => new UploadedFile($sample, 'CLOSING SSR (1).PDF', 'application/pdf', null, true)])
            ->assertSessionHasNoErrors()->assertRedirect();

        $import->refresh();
        $this->assertCount(17, $import->stock_report['items']);
        $this->assertTrue(collect($import->stock_report_checks)->every('ok'), json_encode($import->stock_report_checks));
        $this->get("/imports/{$import->id}/stock-report")->assertOk();
    }
}
