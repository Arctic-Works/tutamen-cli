<?php

declare(strict_types=1);

use Tutamen\Cli\Findings\FindingsRenderer;

it('renders a fixture payload to a stable report', function () {
    $payload = json_decode((string) file_get_contents(__DIR__.'/Fixtures/findings-payload.json'), true);

    $expected = implode("\n", [
        'Findings (3):',
        '',
        '  CRITICAL  laravel.app-key-exposed       .env:3                    APP_KEY committed to the repository.',
        '  HIGH      laravel.debug-enabled         config/app.php:12         Debug mode is enabled.',
        '  MEDIUM    laravel.mass-assignment       app/Models/User.php:20    Model guards nothing.',
        '',
        'Summary: 1 critical, 1 high, 1 medium',
    ]);

    expect((new FindingsRenderer)->render($payload))->toBe($expected);
});

it('renders a clean working tree', function () {
    expect((new FindingsRenderer)->render(['findings' => []]))
        ->toBe('No findings. Working tree is clean.');
});
