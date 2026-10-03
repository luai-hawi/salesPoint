<?php

function featureLangKeys(array $translations, string $prefix = ''): array
{
    $keys = [];

    foreach ($translations as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $keys = array_merge($keys, featureLangKeys($value, $path));
        } else {
            $keys[] = $path;
        }
    }

    sort($keys);

    return $keys;
}

test('team language files keep identical keys', function () {
    $en = require base_path('resources/lang/en/team.php');
    $ar = require base_path('resources/lang/ar/team.php');

    expect(featureLangKeys($ar))->toBe(featureLangKeys($en));
});

test('settings language files keep identical keys', function () {
    $en = require base_path('resources/lang/en/settings.php');
    $ar = require base_path('resources/lang/ar/settings.php');

    expect(featureLangKeys($ar))->toBe(featureLangKeys($en));
});
