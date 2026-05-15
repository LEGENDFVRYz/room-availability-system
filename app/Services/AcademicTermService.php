<?php

namespace App\Services;

use App\Models\AcademicTerm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AcademicTermService
{
    public function getAll(): Collection
    {
        return AcademicTerm::orderByDesc('year_start')
            ->orderBy('semester')
            ->get();
    }

    public function getCurrent(): ?AcademicTerm
    {
        return AcademicTerm::current()->first();
    }

    public function create(array $data): AcademicTerm
    {
        return AcademicTerm::create([
            'year_start' => $data['year_start'],
            'semester'   => $data['semester'],
            'starts_on'  => $data['starts_on'] ?? null,
            'ends_on'    => $data['ends_on'] ?? null,
            'is_current' => false,
            'is_active'  => true,
        ]);
    }

    public function setAsCurrent(AcademicTerm $term): void
    {
        DB::transaction(function () use ($term) {
            AcademicTerm::where('is_current', true)->update(['is_current' => false]);
            $term->update(['is_current' => true]);
        });
    }

    public function findOrCreateAndSetCurrent(int $yearStart, int $semester): AcademicTerm
    {
        $term = AcademicTerm::firstOrCreate(
            ['year_start' => $yearStart, 'semester' => $semester],
            ['is_current' => false, 'is_active' => true],
        );

        $this->setAsCurrent($term);

        return $term;
    }
}
