<?php

declare(strict_types=1);

use Tutamen\Cli\Config\Config;

beforeEach(function () {
    $this->dir = tempDir('tutamen-config-');
});

afterEach(function () {
    removeDir($this->dir);
});

it('round-trips the server and token', function () {
    $config = new Config($this->dir);
    $config->save('https://app.tutamen.dev', '7|secrettoken');

    expect($config->isAuthenticated())->toBeTrue()
        ->and($config->load())->toMatchArray([
            'server' => 'https://app.tutamen.dev',
            'token' => '7|secrettoken',
        ]);
});

it('writes the config file with owner-only permissions', function () {
    $config = new Config($this->dir);
    $config->save('https://app.tutamen.dev', '7|secrettoken');

    expect(substr(sprintf('%o', fileperms($config->path())), -3))->toBe('600');
});

it('reports unauthenticated when no config exists', function () {
    expect((new Config($this->dir))->isAuthenticated())->toBeFalse();
});

it('clears stored credentials', function () {
    $config = new Config($this->dir);
    $config->save('https://app.tutamen.dev', '7|secrettoken');

    expect($config->clear())->toBeTrue()
        ->and($config->isAuthenticated())->toBeFalse();
});
