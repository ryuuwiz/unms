<?php

test('returns a redirect to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
