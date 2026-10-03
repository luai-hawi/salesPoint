<?php

use Tests\Support\Builds;

uses(Builds::class);

it('renders the bilingual 404 page for unknown urls', function () {
    $this->get('/definitely-missing-page-' . uniqid())
        ->assertNotFound()
        ->assertSee('الصفحة غير موجودة', false)
        ->assertSee('Page not found', false);
});

it('renders the bilingual 403 page when an employee lacks a permission', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, []);

    $this->actingAs($employee)->get(route('shopowner.team.index'))
        ->assertForbidden()
        ->assertSee('لا تملك صلاحية الوصول إلى هذه الصفحة', false)
        ->assertSee('You do not have access to this page', false);
});

it('keeps the error translations identical in both languages', function () {
    $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($array as $key => $value) {
            is_array($value)
                ? $keys = array_merge($keys, $flatten($value, $prefix . $key . '.'))
                : $keys[] = $prefix . $key;
        }
        sort($keys);

        return $keys;
    };

    expect($flatten(require resource_path('lang/en/errors.php')))
        ->toBe($flatten(require resource_path('lang/ar/errors.php')));
});

it('has a view for every handled error status', function () {
    foreach (['403', '404', '419', '429', '500', '503'] as $status) {
        expect(view()->exists("errors.$status"))->toBeTrue();
    }
});
