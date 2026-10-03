<?php

use Symfony\Component\Process\Process;
use Tests\Support\Builds;

uses(Builds::class);

test('service worker javascript is syntactically valid', function () {
    $process = new Process(['node', '--check', public_path('sw.js')]);
    $process->run();

    expect($process->getExitCode())
        ->toBe(0, $process->getErrorOutput() ?: $process->getOutput());
});

test('manifest is valid json', function () {
    $decoded = json_decode(file_get_contents(public_path('pwa/manifest.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)
        ->toBeArray()
        ->and($decoded['start_url'] ?? null)->toBe('/dashboard')
        ->and($decoded['scope'] ?? null)->toBe('/');
});
