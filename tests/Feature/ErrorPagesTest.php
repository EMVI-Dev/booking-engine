<?php

use Symfony\Component\HttpKernel\Exception\HttpException;

test('error pages render successfully with unified layout', function (int $status, string $expectedCode, string $expectedHeading) {
    $view = view("errors.{$status}", [
        'exception' => new HttpException($status),
    ])->render();

    expect($view)
        ->toContain($expectedCode)
        ->toContain($expectedHeading)
        ->toContain('fa-solid fa-compass');
})->with([
    [401, '401', 'Authentication required'],
    [403, '403', 'Access restricted'],
    [404, '404', 'This page is not here'],
    [419, '419', 'Session expired'],
    [429, '429', 'Too many requests'],
    [500, '500', 'Something went wrong'],
    [503, '503', 'Under maintenance'],
]);

test('404 route returns aligned error page response', function () {
    $response = $this->get('/non-existent-page-url-xyz');

    $response->assertNotFound();
    $response->assertSee('404');
    $response->assertSee('This page is not here');
});
