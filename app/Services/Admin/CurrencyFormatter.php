<?php

namespace App\Services\Admin;

class CurrencyFormatter
{
    public function all(): array
    {
        return config('currencies', []);
    }

    public function options(): array
    {
        return collect($this->all())->map(function (array $currency, string $code): array {
            return [
                'code' => $code,
                'symbol' => $currency['symbol'] ?? $code,
                'name_en' => $currency['name_en'] ?? $code,
                'name_ar' => $currency['name_ar'] ?? $code,
            ];
        })->values()->all();
    }

    public function isValid(?string $code): bool
    {
        return is_string($code) && array_key_exists($code, $this->all());
    }

    public function symbol(?string $code): string
    {
        return $this->all()[$code]['symbol'] ?? (string) $code;
    }

    public function format(float|int|string|null $amount, ?string $currency): string
    {
        $code = $currency ?: 'ILS';
        $symbol = $this->symbol($code);
        $number = number_format((float) ($amount ?? 0), 2);

        return "{$symbol} {$number} {$code}";
    }
}
