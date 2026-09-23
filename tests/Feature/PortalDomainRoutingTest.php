<?php

test('portal.* names resolve to the dedicated portal domain, without the legacy /portal prefix', function () {
    expect(route('portal.login'))->toBe('http://'.config('app.portal_domain').'/login')
        ->and(route('portal.dashboard'))->toBe('http://'.config('app.portal_domain').'/dashboard');
});

test('the legacy /portal path on the main domain still works, for already-sent signed links', function () {
    $this->get('/portal/login')->assertOk();
});

test('the new portal domain serves the same login page at its root', function () {
    $this->get('http://'.config('app.portal_domain').'/login')->assertOk();
});
