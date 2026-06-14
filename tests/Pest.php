<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Create a throwaway directory under the system temp dir for a test.
 */
function tempDir(string $prefix = 'tutamen-cli-test-'): string
{
    $dir = sys_get_temp_dir().'/'.$prefix.bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);

    return $dir;
}

/**
 * Recursively delete a directory.
 */
function removeDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }

    @rmdir($dir);
}

/**
 * Initialise a git repo in $dir with a deterministic identity and a `main`
 * default branch, so branch-name assertions are stable across machines.
 */
function initGitRepo(string $dir): void
{
    runGit($dir, ['init', '-q', '-b', 'main']);
    runGit($dir, ['config', 'user.email', 'test@tutamen.dev']);
    runGit($dir, ['config', 'user.name', 'Tutamen Test']);
    runGit($dir, ['config', 'commit.gpgsign', 'false']);
}

/**
 * @param  list<string>  $args
 */
function runGit(string $dir, array $args): void
{
    $process = new Process(['git', ...$args], $dir);
    $process->run();
}

function writeFile(string $dir, string $relative, string $contents): void
{
    $path = $dir.'/'.$relative;
    $parent = dirname($path);

    if (! is_dir($parent)) {
        mkdir($parent, 0700, true);
    }

    file_put_contents($path, $contents);
}
