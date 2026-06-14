<?php

declare(strict_types=1);

use Tutamen\Cli\Findings\ExitCode;

function findings(string ...$severities): array
{
    return array_map(fn (string $s): array => ['severity' => $s], $severities);
}

it('is clean when there are no findings', function () {
    expect(ExitCode::forFindings([], 'any'))->toBe(ExitCode::CLEAN)
        ->and(ExitCode::forFindings([], 'critical'))->toBe(ExitCode::CLEAN);
});

it('fails on any finding when the threshold is "any"', function () {
    expect(ExitCode::forFindings(findings('info'), 'any'))->toBe(ExitCode::FINDINGS)
        ->and(ExitCode::forFindings(findings('low'), 'any'))->toBe(ExitCode::FINDINGS);
});

it('fails only at or above the threshold severity', function () {
    expect(ExitCode::forFindings(findings('high', 'low'), 'high'))->toBe(ExitCode::FINDINGS)
        ->and(ExitCode::forFindings(findings('medium', 'low'), 'high'))->toBe(ExitCode::CLEAN)
        ->and(ExitCode::forFindings(findings('critical'), 'high'))->toBe(ExitCode::FINDINGS);
});

it('treats medium threshold as medium-and-above', function () {
    expect(ExitCode::forFindings(findings('medium'), 'medium'))->toBe(ExitCode::FINDINGS)
        ->and(ExitCode::forFindings(findings('low', 'info'), 'medium'))->toBe(ExitCode::CLEAN);
});
