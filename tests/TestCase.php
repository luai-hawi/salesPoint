<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();

        // Pin the clock to mid-day so "today" is the same calendar date in UTC and in the shop timezone;
        // otherwise date-based assertions fail when the suite runs between local midnight and UTC midnight.
        $this->travelTo(now()->utc()->setTime(10, 0));
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        // Feature requests must retain the session cookie like a real browser.
        $this->withCookie(config('session.cookie'), app('session')->getId());

        return $response;
    }
}
