<?php

declare(strict_types=1);

use Tutamen\Cli\Agent\PromptCache;

beforeEach(function () {
    $this->dir = tempDir('tutamen-prompt-cache-');
    $this->path = $this->dir.'/agent-prompt.json';
});

afterEach(function () {
    removeDir($this->dir);
});

it('fetches once and serves from cache within the TTL', function () {
    $now = 1000;
    $cache = new PromptCache($this->path, fn (): int => $now);

    $calls = 0;
    $fetch = function () use (&$calls): array {
        $calls++;

        return ['version' => 1, 'prompt' => 'p'.$calls];
    };

    $first = $cache->remember('http://localhost', $fetch);
    $second = $cache->remember('http://localhost', $fetch);

    expect($calls)->toBe(1)
        ->and($first['prompt'])->toBe('p1')
        ->and($second['prompt'])->toBe('p1');
});

it('refetches once the TTL has elapsed', function () {
    $now = 1000;
    $cache = new PromptCache($this->path, function () use (&$now): int {
        return $now;
    });

    $calls = 0;
    $fetch = function () use (&$calls): array {
        $calls++;

        return ['version' => $calls, 'prompt' => 'p'.$calls];
    };

    $cache->remember('http://localhost', $fetch);
    $now = 1000 + 301; // just past the 5-minute TTL
    $fresh = $cache->remember('http://localhost', $fetch);

    expect($calls)->toBe(2)
        ->and($fresh['version'])->toBe(2)
        ->and($fresh['prompt'])->toBe('p2');
});

it('refetches when the server changes', function () {
    $now = 1000;
    $cache = new PromptCache($this->path, fn (): int => $now);

    $calls = 0;
    $fetch = function () use (&$calls): array {
        $calls++;

        return ['version' => 1, 'prompt' => 'p'.$calls];
    };

    $cache->remember('http://a', $fetch);
    $cache->remember('http://b', $fetch);

    expect($calls)->toBe(2);
});
