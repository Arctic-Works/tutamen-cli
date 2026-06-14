<?php

declare(strict_types=1);

use Tutamen\Cli\Hooks\HookInstaller;

beforeEach(function () {
    $this->repo = tempDir('tutamen-hook-');
    initGitRepo($this->repo); // creates .git/hooks
    $this->installer = new HookInstaller;
});

afterEach(function () {
    removeDir($this->repo);
});

it('creates a native pre-push hook that runs tutamen scan', function () {
    $status = $this->installer->install($this->repo);
    $path = $this->repo.'/.git/hooks/pre-push';

    expect($status)->toBe(HookInstaller::STATUS_CREATED)
        ->and(is_file($path))->toBeTrue()
        ->and(file_get_contents($path))->toContain('tutamen scan --hook')
        ->and(substr(sprintf('%o', fileperms($path)), -3))->toBe('755');
});

it('is idempotent: re-installing updates the block in place', function () {
    $this->installer->install($this->repo);
    $status = $this->installer->install($this->repo);

    $contents = file_get_contents($this->repo.'/.git/hooks/pre-push');

    expect($status)->toBe(HookInstaller::STATUS_UPDATED)
        ->and(substr_count($contents, 'tutamen scan --hook'))->toBe(1);
});

it('appends to an existing foreign hook without clobbering it', function () {
    $path = $this->repo.'/.git/hooks/pre-push';
    file_put_contents($path, "#!/bin/sh\necho \"my existing hook\"\n");

    $status = $this->installer->install($this->repo);
    $contents = file_get_contents($path);

    expect($status)->toBe(HookInstaller::STATUS_APPENDED)
        ->and($contents)->toContain('my existing hook')
        ->and($contents)->toContain('tutamen scan --hook');
});

it('uninstall removes only the tutamen block, leaving foreign content', function () {
    $path = $this->repo.'/.git/hooks/pre-push';
    file_put_contents($path, "#!/bin/sh\necho \"my existing hook\"\n");
    $this->installer->install($this->repo);

    expect($this->installer->uninstall($this->repo))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->toContain('my existing hook')
        ->and($contents)->not->toContain('tutamen scan --hook');
});

it('uninstall deletes a hook it solely created', function () {
    $this->installer->install($this->repo);
    $path = $this->repo.'/.git/hooks/pre-push';

    expect($this->installer->uninstall($this->repo))->toBeTrue()
        ->and(is_file($path))->toBeFalse();
});

it('uninstall reports nothing to remove for a foreign-only hook', function () {
    file_put_contents($this->repo.'/.git/hooks/pre-push', "#!/bin/sh\necho hi\n");

    expect($this->installer->uninstall($this->repo))->toBeFalse();
});

it('installs and uninstalls a husky hook', function () {
    $status = $this->installer->install($this->repo, husky: true);
    $path = $this->repo.'/.husky/pre-push';

    expect($status)->toBe(HookInstaller::STATUS_CREATED)
        ->and(is_file($path))->toBeTrue()
        ->and(file_get_contents($path))->toContain('tutamen scan --hook');

    expect($this->installer->uninstall($this->repo, husky: true))->toBeTrue()
        ->and(is_file($path))->toBeFalse();
});
