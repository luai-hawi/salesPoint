<?php

use Tests\Support\Builds;

uses(Builds::class);

function flattenPayablesKeys(array $items, string $prefix = ''): array
{
    $keys = [];
    foreach ($items as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        $keys[] = $full;

        if (is_array($value)) {
            $keys = array_merge($keys, flattenPayablesKeys($value, $full));
        }
    }

    sort($keys);

    return $keys;
}

test('payables language files have identical keys', function () {
    $en = require resource_path('lang/en/payables.php');
    $ar = require resource_path('lang/ar/payables.php');

    expect(flattenPayablesKeys($en))->toBe(flattenPayablesKeys($ar));
});
