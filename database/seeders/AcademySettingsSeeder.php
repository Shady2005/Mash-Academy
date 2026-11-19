<?php

namespace Database\Seeders;

use App\Models\AcademySetting;
use Illuminate\Database\Seeder;

class AcademySettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AcademySetting::defaults() as $key => $value) {
            AcademySetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        AcademySetting::clearCache();
    }
}
