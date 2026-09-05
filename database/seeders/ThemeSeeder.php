<?php

namespace Database\Seeders;

use App\Domains\Themes\Services\ThemeNormalizer;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    /**
     * Seed the baseline canonical theme dictionary (docs/25).
     */
    public function run(ThemeNormalizer $normalizer): void
    {
        $normalizer->seedDefaultThemes();
    }
}
