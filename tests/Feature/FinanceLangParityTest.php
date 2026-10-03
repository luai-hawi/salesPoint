<?php

function finance_flatten_keys(array $input, string $prefix = ''): array
{
    $keys = [];
    foreach ($input as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        $keys[] = $full;
        if (is_array($value)) {
            $keys = array_merge($keys, finance_flatten_keys($value, $full));
        }
    }

    sort($keys);

    return $keys;
}

test('finance language files have matching keys', function () {
    $en = require base_path('resources/lang/en/finance.php');
    $ar = require base_path('resources/lang/ar/finance.php');

    expect(finance_flatten_keys($en))->toBe(finance_flatten_keys($ar));
});
