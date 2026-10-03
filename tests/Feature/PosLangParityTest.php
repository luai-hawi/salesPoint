<?php

function flattenLangKeys(array $items, string $prefix = ''): array
{
    $keys = [];

    foreach ($items as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $keys = array_merge($keys, flattenLangKeys($value, $full));
        } else {
            $keys[] = $full;
        }
    }

    sort($keys);

    return $keys;
}

function usedPosKeys(): array
{
    $files = [
        resource_path('views\\dashboard.blade.php'),
        resource_path('views\\pos\\held\\index.blade.php'),
        resource_path('views\\pos\\partials\\layout-drawer.blade.php'),
        app_path('Http\\Controllers\\HeldBillController.php'),
        app_path('Http\\Controllers\\PosController.php'),
    ];

    $keys = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match_all("/__\\(\\s*['\\\"]pos\\.([A-Za-z0-9_\\.]+)['\\\"]/", $contents, $matches)) {
            foreach ($matches[1] as $match) {
                $keys[] = $match;
            }
        }
    }

    $keys = array_merge($keys, [
        'preset_classic',
        'preset_focus',
        'preset_visual',
        'preset_cashier',
        'preset_custom',
        'preset_classic_desc',
        'preset_focus_desc',
        'preset_visual_desc',
        'preset_cashier_desc',
        'preset_custom_desc',
    ]);

    $keys = array_values(array_unique($keys));
    sort($keys);

    return $keys;
}

test('pos language files stay in parity', function () {
    $en = require resource_path('lang\\en\\pos.php');
    $ar = require resource_path('lang\\ar\\pos.php');

    expect(flattenLangKeys($ar))->toBe(flattenLangKeys($en));
});

test('every used pos key exists in both languages', function () {
    $en = require resource_path('lang\\en\\pos.php');
    $ar = require resource_path('lang\\ar\\pos.php');
    $enKeys = flattenLangKeys($en);
    $arKeys = flattenLangKeys($ar);

    foreach (usedPosKeys() as $key) {
        expect($enKeys)->toContain($key)
            ->and($arKeys)->toContain($key);
    }
});
