<?php

declare(strict_types=1);

namespace Tutamen\Cli\Hooks;

/**
 * Decides whether the pre-push hook should scan the current branch, given the
 * optional regex from .tutamen.json. No pattern means "every branch".
 */
final class BranchMatcher
{
    public static function shouldRun(?string $branch, ?string $pattern): bool
    {
        if ($pattern === null || $pattern === '') {
            return true;
        }

        if ($branch === null || $branch === '') {
            // Detached HEAD with a pattern configured: be safe and scan.
            return true;
        }

        // Anchor loosely with delimiters; an invalid regex falls back to "run".
        $delimited = '#'.str_replace('#', '\#', $pattern).'#';

        // Swallow the compile warning a malformed pattern would emit; we treat
        // it as "run" rather than crash a developer's push.
        set_error_handler(static fn (): bool => true);

        try {
            $result = preg_match($delimited, $branch);
        } finally {
            restore_error_handler();
        }

        return $result === false ? true : $result === 1;
    }
}
