<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;

class CourierSeeder extends Seeder
{
    /** Editable examples only; no external courier integration is configured. */
    public function run(): void
    {
        foreach ([
            ['name' => 'J&T Express', 'code' => 'JT_EXPRESS', 'sort_order' => 10],
            ['name' => 'Pos Malaysia', 'code' => 'POS_MALAYSIA', 'sort_order' => 20],
            ['name' => 'Ninja Van', 'code' => 'NINJA_VAN', 'sort_order' => 30],
            ['name' => 'DHL Express', 'code' => 'DHL_EXPRESS', 'sort_order' => 40],
            ['name' => 'Other / Manual Courier', 'code' => 'MANUAL', 'sort_order' => 99],
        ] as $courier) {
            Courier::query()->firstOrCreate(['code' => $courier['code']], $courier + ['is_active' => true, 'supports_api' => false]);
        }
    }
}
