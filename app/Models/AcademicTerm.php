<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    use HasFactory;

    protected $table = 'tbl_academic_terms';

    protected $fillable = [
        'year_start',
        'semester',
        'starts_on',
        'ends_on',
        'is_current',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year_start' => 'integer',
            'semester'   => Semester::class,
            'starts_on'  => 'date',
            'ends_on'    => 'date',
            'is_current' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    /** e.g. "2025-2026" */
    public function getSchoolYearLabelAttribute(): string
    {
        $yearEnd = $this->year_start + 1;
        return "{$this->year_start}-{$yearEnd}";
    }

    /** e.g. "SY 2025-2026 — 1st Semester" */
    public function getLabelAttribute(): string
    {
        $yearEnd = $this->year_start + 1;
        return "SY {$this->year_start}-{$yearEnd} — {$this->semester->label()}";
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
