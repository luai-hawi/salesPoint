<?php

use Tests\Support\Builds;

uses(Builds::class);

test('admin language files have identical keys', function () {
    $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($array as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $keys = array_merge($keys, $flatten($value, $full));
            } else {
                $keys[] = $full;
            }
        }

        return $keys;
    };

    $en = require lang_path('en/admin.php');
    $ar = require lang_path('ar/admin.php');

    $enKeys = $flatten($en);
    $arKeys = $flatten($ar);
    sort($enKeys);
    sort($arKeys);

    expect($enKeys)->toEqual($arKeys);
});
