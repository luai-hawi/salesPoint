<?php

test('restaurant language files stay in parity', function () {
    $en = require resource_path('lang/en/restaurant.php');
    $ar = require resource_path('lang/ar/restaurant.php');

    $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $keys = array_merge($keys, $flatten($value, $path));
            } else {
                $keys[] = $path;
            }
        }

        sort($keys);

        return $keys;
    };

    expect($flatten($en))->toBe($flatten($ar));
});
