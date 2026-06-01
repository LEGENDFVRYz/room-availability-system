<?php

namespace App\Services\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class ValueNormalizer
{
    public function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    public function enumString(mixed $value): string
    {
        $value = $this->enumValue($value);

        return $value === null ? '' : (string) $value;
    }

    public function date(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        return Carbon::parse($value)->toDateString();
    }

    public function time(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }

    public function timeString(mixed $value): string
    {
        return $this->time($value) ?? '';
    }

    public function dateString(mixed $value): string
    {
        return $this->date($value) ?? '';
    }

    public function dateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        return (string) $value;
    }

    public function dateTimeToTime(?Carbon $value, Carbon $fallback): string
    {
        return ($value ?? $fallback)->format('H:i');
    }
}
