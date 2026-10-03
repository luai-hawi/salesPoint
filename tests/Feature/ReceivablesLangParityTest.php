<?php

use Tests\Support\Builds;

uses(Builds::class);

function flattenReceivablesKeys(array $items, string $prefix = ''): array
{
    $keys = [];
    foreach ($items as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        $keys[] = $full;

        if (is_array($value)) {
            $keys = array_merge($keys, flattenReceivablesKeys($value, $full));
        }
    }

    sort($keys);

    return $keys;
}

test('receivables language files have identical keys', function () {
    $en = require resource_path('lang/en/receivables.php');
    $ar = require resource_path('lang/ar/receivables.php');

    expect(flattenReceivablesKeys($en))->toBe(flattenReceivablesKeys($ar));
});
