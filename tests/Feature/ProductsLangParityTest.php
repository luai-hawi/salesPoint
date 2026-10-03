<?php

test('products ui language files have identical keys', function () {
    $en = require base_path('resources/lang/en/products_ui.php');
    $ar = require base_path('resources/lang/ar/products_ui.php');

    expect(flattenProductsLangKeys($en))->toBe(flattenProductsLangKeys($ar));
});

/**
 * @return list<string>
 */
function flattenProductsLangKeys(array $items, string $prefix = ''): array
{
    $keys = [];

    foreach ($items as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        $keys[] = $path;

        if (is_array($value)) {
            $keys = array_merge($keys, flattenProductsLangKeys($value, $path));
        }
    }

    sort($keys);

    return $keys;
}
