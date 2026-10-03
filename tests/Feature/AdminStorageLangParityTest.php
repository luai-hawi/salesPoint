<?php

test('admin storage language files share identical keys', function () {
    $en = require resource_path('lang/en/admin_storage.php');
    $ar = require resource_path('lang/ar/admin_storage.php');

    expect(array_keys_recursive($en))->toBe(array_keys_recursive($ar));
});

function array_keys_recursive(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        $keys[] = $fullKey;

        if (is_array($value)) {
            $keys = array_merge($keys, array_keys_recursive($value, $fullKey));
        }
    }

    sort($keys);

    return $keys;
}
