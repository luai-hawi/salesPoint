<?php

test('staff language files have identical keys', function () {
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

        sort($keys);

        return $keys;
    };

    $en = $flatten(require base_path('resources/lang/en/staff.php'));
    $ar = $flatten(require base_path('resources/lang/ar/staff.php'));

    expect($ar)->toBe($en);
});
