<?php

namespace Database\Seeders;

use App\Modules\Distribution\Enums\BonusSource;
use App\Modules\Sales\Enums\StockMovementType;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Distribution\Models\Territory;
use App\Modules\Sales\Models\StockMovement;
use Illuminate\Database\Seeder;

/**
 * Starting data from the client's Excel sheet. Ex-FP, cost and opening units are theirs;
 * sales tax, withholding tax, incentive % and bonus schemes are SAMPLE values to confirm.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'owner@example.com'], ['name' => 'Owner', 'password' => env('SEED_OWNER_PASSWORD') ?: 'change-me-now', 'role' => 'owner']);

        // code, name, Ex-FP, trade price, cost, opening units, sales tax %, WHT %, incentive %, bonus buy+free
        $products = [
            ['GYN', 'Gynofol Tab', 325, 417, 74, 3294, 0, 0.5, 5, 10, 2],
            ['PRO', 'Prolif-D Tab', 892, 1190, 136, 2100, 0, 0.5, 5, 10, 2],
            ['EQU', 'Equate Lotion / V-Wash', 425, 467.5, 115, 1500, 10, 1, 4, 10, 2],
            ['VEL', 'Velfer Tab', 325, 417, 70, 3200, 0, 0.5, 5, 10, 2],
            ['WIZ', 'Wiz-G Tab', 325, 416.5, 76, 1200, 5, 0.5, 5, 10, 2],
            ['FEM', 'Femil Tab', 325, 417, 70, 1900, 0, 0.5, 5, 10, 2],
            ['BIO', 'Bio Ron Tab', 650, 935, 140, 1000, 0, 0.5, 5, 10, 2],
            ['ROS', 'Rosfer Sachet', 325, 417, 93, 0, 5, 0.5, 4, 10, 2],
            ['STK', 'Stark-D Sachet', 350, 501.5, 93, 60, 5, 0.5, 4, 10, 2],
            ['ENC', 'Encinta Cap', 330, 442, 97, 700, 0, 0.5, 5, 10, 2],
            ['PRV', 'Proviem 200g Milk', 460, 675, 170, 370, 10, 1, 3, 10, 1],
            ['HAV', 'Havoroids Cap', 650, 893, 212, 22, 0, 0.5, 5, 10, 2],
        ];

        foreach ($products as [$code, $name, $exfp, $tp, $cost, $opening, $st, $wht, $incentive, $buy, $free]) {
            $product = Product::updateOrCreate(['code' => $code], ['name' => $name]);
            $product->rates()->updateOrCreate(['effective_from' => '2026-01-01'], [
                'exfp_price' => $exfp, 'trade_price' => $tp, 'cost_price' => $cost, 'sales_tax_rate' => $st,
                'withholding_tax_rate' => $wht, 'incentive_rate' => $incentive, 'bonus_buy' => $buy, 'bonus_free' => $free,
            ]);
            StockMovement::firstOrCreate(['product_id' => $product->id, 'type' => StockMovementType::Opening], ['units' => $opening, 'moved_on' => '2026-10-01', 'note' => 'Opening stock from the Excel sheet']);
        }

        // Each distributor's file profile, read from its October 2026 PDF (December 2025 for Saaj).
        foreach ([
            ['Hamdan Enterprises', 'Mardan', 'matrix', 'HAMDAN ENTERPRISES', 'SoftTouch product grid. No customer codes, no bonus and no line values: prices come from the grand-total row. "(N)" columns are new-price stock of the same product.'],
            ['Monal Enterprises', 'Batkhela', 'matrix', 'MONAL ENTERPRISES', 'SoftTouch product grid with its own column order. No customer codes, no bonus and no line values.'],
            ['Saaj Enterprises', 'Swabi', 'matrix', 'SAAJ ENTERPRISES', 'SoftTouch product grid. Old and "N(R)" columns of the same product sit side by side; both link to one product.'],
            ['Irfan Enterprises', 'Timergara', 'lineitem', 'IRFAN ENTERPRISES', 'SoftTouch Sale Report: customer codes, its own item codes, bonus, TP amount and net amount.'],
            ['Umar Drug Agencies', 'Peshawar', 'lineitem', 'UMAR DRUG AGENCIES', 'SoftTouch Sale Report with customer and item codes and bonus. Returns print negative units and bonus; some lines are bonus only.'],
            ['Friends Enterprises', 'Buner', 'monthly', 'FRIENDS ENTERPRISES', 'Customer annual sales with Jan–Dec columns, T.P and amount. No bonus column. Export one month only (from and to dates in the same month).'],
            ['Umer Enterprises', 'Timergara', null, null, null],
        ] as [$name, $city, $layout, $header, $notes]) {
            Distributor::updateOrCreate(['name' => $name], ['city' => $city, 'layout' => $layout, 'header_name' => $header, 'bonus_source' => BonusSource::File, 'format_notes' => $notes]);
        }

        foreach ([
            ['Uzma Bibi', 'Mardan 1'], ['Saba Bibi', 'Mardan 2'], ['Mustafa Kamal', 'Mardan 3'], ['Sara Bibi', 'Swabi'], ['Nosheen Bibi', 'Charsadda'],
            ['Namdar Khan', 'Peshawar 1'], ['Rabia Bibi', 'Peshawar 2'], ['Marjan Bibi', 'Batkhela'], ['Marwa Bibi', 'Buner'], ['Nazo', 'Timergara'],
        ] as [$name, $territory]) {
            MedicalRep::updateOrCreate(['name' => $name], ['territory_id' => Territory::firstOrCreate(['name' => $territory])->id]);
        }

        $this->call(ProductAliasSeeder::class);
    }
}
