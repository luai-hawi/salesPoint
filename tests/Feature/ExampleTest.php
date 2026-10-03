<?php

it('redirects the home page to the sales point', function () {
    $response = $this->get('/');

    $response->assertRedirect('/dashboard');
});