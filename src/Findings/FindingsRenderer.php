<?php

declare(strict_types=1);

namespace Tutamen\Cli\Findings;

/**
 * Renders a finished scan's findings into a plain-text report. Kept free of
 * ANSI/styling so it is deterministic and snapshot-testable; the command layer
 * adds severity colour when writing to a terminal.
 */
final class FindingsRenderer
{
    private const SEVERITY_ORDER = ['critical', 'high', 'medium', 'low', 'info'];

    /**
     * @param  array<string, mixed>  $envelope  the GET /scans/{id} response
     */
    public function render(array $envelope): string
    {
        /** @var list<array<string, mixed>> $findings */
        $findings = is_array($envelope['findings'] ?? null) ? $envelope['findings'] : [];

        if ($findings === []) {
            return 'No findings. Working tree is clean.';
        }

        $lines = ['Findings ('.count($findings).'):', ''];

        foreach ($findings as $finding) {
            $severity = strtoupper((string) ($finding['severity'] ?? 'info'));
            $rule = (string) ($finding['rule_id'] ?? 'unknown');
            $location = (string) ($finding['file_path'] ?? '?').':'.($finding['start_line'] ?? '?');
            $message = (string) ($finding['message'] ?? '');

            $lines[] = sprintf(
                '  %-8s  %-28s  %-24s  %s',
                $severity,
                $rule,
                $location,
                $message,
            );
        }

        $lines[] = '';
        $lines[] = 'Summary: '.$this->summary($findings);

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    private function summary(array $findings): string
    {
        $counts = [];

        foreach ($findings as $finding) {
            $severity = strtolower((string) ($finding['severity'] ?? 'info'));
            $counts[$severity] = ($counts[$severity] ?? 0) + 1;
        }

        $parts = [];

        foreach (self::SEVERITY_ORDER as $severity) {
            if (! empty($counts[$severity])) {
                $parts[] = $counts[$severity].' '.$severity;
            }
        }

        return $parts === [] ? '0 findings' : implode(', ', $parts);
    }
}
