<?php

declare(strict_types=1);

use Tutamen\Cli\Config\ProjectConfig;

beforeEach(function () {
    $this->repo = tempDir('tutamen-project-');
});

afterEach(function () {
    removeDir($this->repo);
});

it('returns null when no .tutamen.json exists', function () {
    $config = ProjectConfig::load($this->repo);

    expect($config->failOn())->toBeNull()
        ->and($config->hookBranches())->toBeNull();
});

it('reads hook failOn and branches', function () {
    writeFile($this->repo, '.tutamen.json', json_encode([
        'hooks' => ['branches' => '^(main|release/.*)$', 'failOn' => 'high'],
    ]));

    $config = ProjectConfig::load($this->repo);

    expect($config->failOn())->toBe('high')
        ->and($config->hookBranches())->toBe('^(main|release/.*)$');
});

it('prefers hooks.failOn over a top-level failOn', function () {
    writeFile($this->repo, '.tutamen.json', json_encode([
        'failOn' => 'low',
        'hooks' => ['failOn' => 'critical'],
    ]));

    expect(ProjectConfig::load($this->repo)->failOn())->toBe('critical');
});

it('refuses to read a .tutamen.json that contains a legacy id-prefixed token', function () {
    writeFile($this->repo, '.tutamen.json', json_encode([
        'token' => '7|abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG',
    ]));

    expect(fn () => ProjectConfig::load($this->repo))
        ->toThrow(RuntimeException::class, 'token');
});

it('refuses to read a .tutamen.json with a bare token under a secret-ish key', function () {
    writeFile($this->repo, '.tutamen.json', json_encode([
        'apiKey' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCD',
    ]));

    expect(fn () => ProjectConfig::load($this->repo))
        ->toThrow(RuntimeException::class, 'secret');
});

it('writes hook settings while preserving existing keys', function () {
    writeFile($this->repo, '.tutamen.json', json_encode(['somethingElse' => true]));

    ProjectConfig::writeHookSettings($this->repo, '^main$', 'high');

    $written = json_decode((string) file_get_contents($this->repo.'/.tutamen.json'), true);

    expect($written)->toMatchArray([
        'somethingElse' => true,
        'hooks' => ['branches' => '^main$', 'failOn' => 'high'],
    ]);
});
