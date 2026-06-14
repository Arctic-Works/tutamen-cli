<?php

declare(strict_types=1);

namespace Tutamen\Cli\Findings;

/**
 * Severity weights, mirroring the server's FindingSeverity::weight() so the
 * CLI's --fail-on threshold means exactly what the dashboard means.
 */
final class Severity
{
    private const WEIGHTS = [
        'critical' => 50,
        'high' => 40,
        'medium' => 30,
        'low' => 20,
        'info' => 10,
    ];

    public static function weight(string $severity): int
    {
        return self::WEIGHTS[strtolower($severity)] ?? 0;
    }

    /**
     * The accepted --fail-on values: every severity, plus the catch-all "any".
     *
     * @return list<string>
     */
    public static function thresholds(): array
    {
        return [...array_keys(self::WEIGHTS), 'any'];
    }

    public static function isValidThreshold(string $threshold): bool
    {
        return in_array(strtolower($threshold), self::thresholds(), true);
    }
}
