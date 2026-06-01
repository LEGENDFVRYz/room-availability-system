<?php

namespace App\Services;

use App\Models\AcademicTerm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AcademicTermService
{
    private readonly NoticeService $noticeService;

    public function __construct(?NoticeService $noticeService = null)
    {
        $this->noticeService = $noticeService ?? app(NoticeService::class);
    }

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


    public function getCurrentOrActive(string|Carbon|null $date = null): ?AcademicTerm
    {
        $current = $this->getCurrent();

        if ($current) {
            return $current;
        }

        if ($date) {
            $resolved = $this->resolveForDate($date);

            if ($resolved) {
                return $resolved;
            }
        }

        return AcademicTerm::query()
            ->where('is_active', true)
            ->orderByDesc('year_start')
            ->orderByDesc('semester')
            ->latest('id')
            ->first();
    }

    public function resolveForDate(string|Carbon $date): ?AcademicTerm
    {
        $selectedDate = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();

        return AcademicTerm::query()
            ->where(function ($query) use ($selectedDate) {
                $query->whereNull('starts_on')
                    ->orWhereDate('starts_on', '<=', $selectedDate);
            })
            ->where(function ($query) use ($selectedDate) {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $selectedDate);
            })
            ->orderByDesc('is_current')
            ->orderByDesc('is_active')
            ->latest('id')
            ->first()
            ?? $this->getCurrent();
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

    public function setAsCurrent(AcademicTerm $term, ?int $userId = null): void
    {
        DB::transaction(function () use ($term) {
            AcademicTerm::where('is_current', true)->update(['is_current' => false]);
            $term->update(['is_current' => true]);
        });

        $this->noticeService->announceAcademicTermSet($term->refresh(), $userId);
    }

    public function findOrCreateAndSetCurrent(int $yearStart, int $semester, ?int $userId = null): AcademicTerm
    {
        $term = AcademicTerm::firstOrCreate(
            ['year_start' => $yearStart, 'semester' => $semester],
            ['is_current' => false, 'is_active' => true],
        );

        $this->setAsCurrent($term, $userId);

        return $term;
    }
}
