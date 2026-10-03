<?php

use Illuminate\Support\Facades\File;

/**
 * Every literal translation key used by a view, controller, route or service must exist in BOTH languages.
 * A missing key renders as the raw key ("pos.held_bill") to the user.
 */
function collectLiteralTranslationKeys(): array
{
    $roots = [base_path('resources/views'), base_path('app'), base_path('routes')];
    $pattern = '/(?:__|trans|trans_choice|@lang)\(\s*([\'"])([a-z][a-z0-9_]*\.[^\'"\r\n]+?)\1\s*[,)]/';
    $found = [];

    foreach ($roots as $root) {
        foreach (File::allFiles($root) as $file) {
            if (! in_array($file->getExtension(), ['php'], true)) {
                continue;
            }

            if (! preg_match_all($pattern, file_get_contents($file->getRealPath()), $matches)) {
                continue;
            }

            foreach ($matches[2] as $key) {
                if (str_contains($key, '$') || str_contains($key, '{') || str_contains($key, '::')) {
                    continue;
                }

                $found[$key][] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            }
        }
    }

    ksort($found);

    return $found;
}

it('has every literal translation key in both English and Arabic', function () {
    $translator = app('translator');
    $missing = [];

    foreach (collectLiteralTranslationKeys() as $key => $files) {
        foreach (['en', 'ar'] as $locale) {
            if (! $translator->hasForLocale($key, $locale)) {
                $missing[] = "[$locale] $key  (" . $files[0] . ')';
            }
        }
    }

    expect($missing)->toBe([]);
});
