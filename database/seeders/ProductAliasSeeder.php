<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Models\ProductAlias;
use Illuminate\Database\Seeder;

/**
 * How each distributor spelled the products in its October 2026 PDF, read by the portal's own parser.
 * Only spellings that clearly name one product are linked. "EQUAT FLORA" / "Equate Flora" and
 * "PROVIEM SUPPLEMENT" are left for the client to link on the review screen, because they may be
 * different products from Equate Lotion and Proviem 200g Milk.
 */
class ProductAliasSeeder extends Seeder
{
    private const ALIASES = [
        'Hamdan Enterprises' => [
            'PROLIF- D TAB (N)' => 'PRO', 'PROLIF- D TAB' => 'PRO', 'GYNOF OL TAB (N)' => 'GYN', 'HAVOR OIDS CAP' => 'HAV', 'ENCINT A TAB' => 'ENC',
        ],
        'Monal Enterprises' => [
            'STARK- D SACHE' => 'STK', 'WIZ-G TAB' => 'WIZ', 'VELFER TAB' => 'VEL', 'EQUAT E LOTION' => 'EQU', 'GYNOF OL TAB' => 'GYN', 'FEMIL TAB' => 'FEM',
            'PROLIF- D TAB' => 'PRO', 'HAVOR OIDS CAP' => 'HAV', 'ENCINT A CAP' => 'ENC', 'BIORON TAB' => 'BIO', 'PROVIE M 200 G' => 'PRV',
        ],
        'Saaj Enterprises' => [
            'GYNOF OL N(R)' => 'GYN', 'PROLIF D TAB N' => 'PRO',
        ],
        'Irfan Enterprises' => [
            'GYNOFOL TAB' => 'GYN', 'EQUATE -V- WASH' => 'EQU', 'VELFER TAB' => 'VEL', 'STARK-D SACHET' => 'STK', 'WIZ-G TAB' => 'WIZ',
            'ENCINTA CAP' => 'ENC', 'PROLIF-D TAB' => 'PRO', 'BIORON TAB' => 'BIO',
        ],
        'Umar Drug Agencies' => [
            'PROLIF-D TAB NEW' => 'PRO', 'PROLIF.D TAB' => 'PRO', 'GYNOFOL TAB' => 'GYN', 'GYNOFOL 1000MCG TAB NEW' => 'GYN', 'BIORON TAB' => 'BIO',
            'WIZ-G TAB' => 'WIZ', 'FEMIL TABS NEW' => 'FEM', "FEMIL TAB 10'S" => 'FEM', 'VELFER TAB NEW' => 'VEL', 'VELFER TAB' => 'VEL',
            'HAVOROIDS CAP' => 'HAV', 'ENCINTA CAP' => 'ENC',
        ],
        'Friends Enterprises' => [
            'Velfer Tab' => 'VEL', 'PRVIEM MILK' => 'PRV', 'Gynofol 1000mcg Tab' => 'GYN', 'Prolif.D Tab' => 'PRO', 'STARK-D SACHET(10000IU)' => 'STK',
            'FemIl Tab' => 'FEM', 'BIORON TAB' => 'BIO', 'ENCINTA CAP' => 'ENC', 'Wiz-G Tab' => 'WIZ', 'HAVOROIDS CAP' => 'HAV',
        ],
    ];

    /** The distributor's own item codes, printed next to the name in line-item and annual files. */
    private const CODES = [
        'Irfan Enterprises' => [
            'GYNOFOL TAB' => '955', 'EQUATE -V- WASH' => '962', 'VELFER TAB' => '952', 'STARK-D SACHET' => '957', 'WIZ-G TAB' => '965',
            'ENCINTA CAP' => '959', 'PROLIF-D TAB' => '961', 'BIORON TAB' => '958',
        ],
        'Umar Drug Agencies' => [
            'PROLIF-D TAB NEW' => '1119', 'PROLIF.D TAB' => '499', 'GYNOFOL TAB' => '501', 'GYNOFOL 1000MCG TAB NEW' => '1231', 'BIORON TAB' => '970',
            'WIZ-G TAB' => '678', 'FEMIL TABS NEW' => '1188', "FEMIL TAB 10'S" => '723', 'VELFER TAB NEW' => '1155', 'VELFER TAB' => '646',
            'HAVOROIDS CAP' => '495', 'ENCINTA CAP' => '821',
        ],
        'Friends Enterprises' => [
            'Velfer Tab' => '679', 'PRVIEM MILK' => '1143', 'Gynofol 1000mcg Tab' => '676', 'Prolif.D Tab' => '678', 'STARK-D SACHET(10000IU)' => '796',
            'FemIl Tab' => '675', 'BIORON TAB' => '927', 'ENCINTA CAP' => '712', 'Wiz-G Tab' => '680', 'HAVOROIDS CAP' => '795',
        ],
    ];

    public function run(): void
    {
        $products = Product::pluck('id', 'code');

        foreach (self::ALIASES as $distributorName => $aliases) {
            $distributor = Distributor::where('name', $distributorName)->first();

            if (! $distributor) {
                continue;
            }

            foreach ($aliases as $nameInFile => $code) {
                ProductAlias::updateOrCreate(
                    ['distributor_id' => $distributor->id, 'name_in_file' => $nameInFile],
                    ['product_id' => $products[$code], 'code_in_file' => self::CODES[$distributorName][$nameInFile] ?? null],
                );
            }
        }
    }
}
