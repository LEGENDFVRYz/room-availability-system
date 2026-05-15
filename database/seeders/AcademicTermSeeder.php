<?php

namespace Database\Seeders;

use App\Enums\Semester;
use App\Models\AcademicTerm;
use Illuminate\Database\Seeder;

class AcademicTermSeeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            [
                'year_start' => 2024,
                'semester'   => Semester::Second,
                'starts_on'  => '2025-01-06',
                'ends_on'    => '2025-05-31',
                'is_current' => false,
                'is_active'  => false,
            ],
            [
                'year_start' => 2025,
                'semester'   => Semester::First,
                'starts_on'  => '2025-08-18',
                'ends_on'    => '2025-12-20',
                'is_current' => false,
                'is_active'  => true,
            ],
            [
                'year_start' => 2025,
                'semester'   => Semester::Second,
                'starts_on'  => '2026-01-05',
                'ends_on'    => '2026-05-30',
                'is_current' => true,
                'is_active'  => true,
            ],
        ];

        foreach ($terms as $term) {
            AcademicTerm::create($term);
        }
    }
}
