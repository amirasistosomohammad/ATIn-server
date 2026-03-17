<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Purchase Request', 'code' => 'PR'],
            ['name' => 'Purchase Order', 'code' => 'PO'],
            ['name' => 'Inspection and Acceptance Report', 'code' => 'IAR'],
            ['name' => 'Delivery Receipt', 'code' => 'DR'],
            ['name' => 'Disbursement Voucher', 'code' => 'DV'],
            ['name' => 'Other', 'code' => null],
        ];

        foreach ($types as $type) {
            DocumentType::create([
                'name' => $type['name'],
                'code' => $type['code'],
                'is_active' => true,
            ]);
        }
    }
}
