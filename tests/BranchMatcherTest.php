<?php

declare(strict_types=1);

use Tutamen\Cli\Hooks\BranchMatcher;

it('runs on every branch when no pattern is set', function () {
    expect(BranchMatcher::shouldRun('feature/x', null))->toBeTrue()
        ->and(BranchMatcher::shouldRun('feature/x', ''))->toBeTrue();
});

it('runs only on matching branches', function () {
    $pattern = '^(main|release/.*)$';

    expect(BranchMatcher::shouldRun('main', $pattern))->toBeTrue()
        ->and(BranchMatcher::shouldRun('release/1.0', $pattern))->toBeTrue()
        ->and(BranchMatcher::shouldRun('feature/login', $pattern))->toBeFalse();
});

it('runs on a detached HEAD to stay safe', function () {
    expect(BranchMatcher::shouldRun(null, '^main$'))->toBeTrue();
});

it('falls back to running when the pattern is invalid', function () {
    expect(BranchMatcher::shouldRun('main', '([unterminated'))->toBeTrue();
});
