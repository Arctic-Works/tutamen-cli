<?php

declare(strict_types=1);

namespace Tutamen\Cli\Snapshot;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Thin wrapper over the git commands the snapshot builder needs. Kept tiny and
 * explicit so the snapshot logic stays readable and testable against real temp
 * repositories.
 */
final class Git
{
    public function __construct(private readonly string $workingDir)
    {
    }

    public function isRepository(): bool
    {
        return $this->run(['rev-parse', '--is-inside-work-tree'])[0] === 0;
    }

    public function topLevel(): string
    {
        [$exit, $out] = $this->run(['rev-parse', '--show-toplevel']);

        if ($exit !== 0) {
            throw new RuntimeException('Not a git repository (run tutamen from inside your project).');
        }

        return trim($out);
    }

    public function currentBranch(): ?string
    {
        [$exit, $out] = $this->run(['rev-parse', '--abbrev-ref', 'HEAD']);
        $branch = trim($out);

        return $exit === 0 && $branch !== '' && $branch !== 'HEAD' ? $branch : null;
    }

    public function headSha(): ?string
    {
        [$exit, $out] = $this->run(['rev-parse', 'HEAD']);

        return $exit === 0 ? trim($out) : null;
    }

    /**
     * Tracked files (respects .gitignore by construction — only tracked paths
     * are listed, and .git is never among them).
     *
     * @return list<string>
     */
    public function trackedFiles(): array
    {
        return $this->nulSeparated(['ls-files', '-z']);
    }

    /**
     * Untracked files that are not ignored (git's --exclude-standard applies
     * .gitignore, .git/info/exclude and global excludes).
     *
     * @return list<string>
     */
    public function untrackedFiles(): array
    {
        return $this->nulSeparated(['ls-files', '--others', '--exclude-standard', '-z']);
    }

    /**
     * @param  list<string>  $args
     * @return list<string>
     */
    private function nulSeparated(array $args): array
    {
        [, $out] = $this->run($args);

        return array_values(array_filter(explode("\0", $out), static fn (string $path): bool => $path !== ''));
    }

    /**
     * @param  list<string>  $args
     * @return array{0: int, 1: string}
     */
    private function run(array $args): array
    {
        $process = new Process(['git', ...$args], $this->workingDir);
        $process->run();

        return [$process->getExitCode() ?? 1, $process->getOutput()];
    }
}
