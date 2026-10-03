<?php

test('hr owner language files stay in parity', function () {
    $en = require resource_path('lang/en/hr_owner.php');
    $ar = require resource_path('lang/ar/hr_owner.php');

    $flatten = function (array $array, string $prefix = '') use (&$flatten) {
        $keys = [];
        foreach ($array as $key => $value) {
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

    expect($flatten($ar))->toEqual($flatten($en));
});
