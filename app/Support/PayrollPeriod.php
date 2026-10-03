<?php

namespace App\Support;

use Carbon\Carbon;
use InvalidArgumentException;

class PayrollPeriod
{
    public static function isValid(?string $value): bool
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return false;
        }

        return self::tryParse($value) !== null;
    }

    public static function normalize(string $value): string
    {
        $date = self::tryParse($value);

        if (! $date) {
            throw new InvalidArgumentException('Invalid payroll period.');
        }

        return $date->format('Y-m');
    }

    private static function tryParse(string $value): ?Carbon
    {
        try {
            $date = Carbon::createFromFormat('!Y-m', $value, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        if (! $date || $date->format('Y-m') !== $value) {
            return null;
        }

        return $date->startOfMonth();
    }
}
