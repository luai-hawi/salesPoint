<?php

use Tests\Support\Builds;

uses(Builds::class);

test('sync language files keep identical keys', function () {
    $en = require resource_path('lang/en/sync.php');
    $ar = require resource_path('lang/ar/sync.php');

    $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($items as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $keys = array_merge($keys, $flatten($value, $full));
            } else {
                $keys[] = $full;
            }
        }

        sort($keys);

        return $keys;
    };

    expect($flatten($ar))->toBe($flatten($en));
});
