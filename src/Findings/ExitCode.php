<?php

declare(strict_types=1);

namespace Tutamen\Cli\Findings;

/**
 * Maps a scan result to a process exit code, the contract the pre-push hook
 * (and CI) relies on:
 *   0 — clean, or nothing at/above the threshold
 *   1 — findings at or above the --fail-on threshold
 *   2 — the scan could not be run (auth, network, server error)
 */
final class ExitCode
{
    public const CLEAN = 0;

    public const FINDINGS = 1;

    public const ERROR = 2;

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    public static function forFindings(array $findings, string $failOn): int
    {
        if ($findings === []) {
            return self::CLEAN;
        }

        // "any" trips on a single finding of any severity, including info.
        if (strtolower($failOn) === 'any') {
            return self::FINDINGS;
        }

        $threshold = Severity::weight($failOn);

        foreach ($findings as $finding) {
            $severity = (string) ($finding['severity'] ?? 'info');

            if (Severity::weight($severity) >= $threshold) {
                return self::FINDINGS;
            }
        }

        return self::CLEAN;
    }
}
