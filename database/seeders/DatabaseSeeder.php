<?php

namespace Database\Seeders;

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
            StockMovement::firstOrCreate(['product_id' => $product->id, 'type' => StockMovement::OPENING], ['units' => $opening, 'moved_on' => '2026-10-01', 'note' => 'Opening stock from the Excel sheet']);
        }

        foreach ([
            ['Hamdan Enterprises', 'Mardan', 'matrix'], ['Monal Enterprises', 'Batkhela', 'matrix'], ['Saaj Enterprises', 'Swabi', 'matrix'],
            ['Irfan Enterprises', 'Timergara', 'lineitem'], ['Umar Drug Agencies', 'Peshawar', 'lineitem'], ['Friends Enterprises', 'Buner', 'monthly'],
            ['Umer Enterprises', 'Timergara', null],
        ] as [$name, $city, $layout]) {
            Distributor::updateOrCreate(['name' => $name], ['city' => $city, 'layout' => $layout]);
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
