<?php

declare(strict_types=1);

namespace Tutamen\Cli\Snapshot;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Builds the gzipped tarball uploaded for a CLI scan.
 *
 * The snapshot is the set of tracked files at their CURRENT working-tree
 * content (so a secret you just edited but have not committed is still
 * caught — "check before push" must be honest about a dirty tree), optionally
 * plus untracked-and-unignored files. `.git/`, `vendor/` and `node_modules/`
 * are excluded for free: they are either never tracked or git-ignored, so they
 * never appear in the file list. Files listed but deleted on disk are skipped.
 */
final class SnapshotBuilder
{
    public function __construct(private readonly Git $git)
    {
    }

    /**
     * @return string absolute path to the created .tar.gz (caller deletes it)
     */
    public function build(string $repoRoot, bool $includeUntracked = false): string
    {
        $files = $this->git->trackedFiles();

        if ($includeUntracked) {
            $files = [...$files, ...$this->git->untrackedFiles()];
        }

        $files = array_values(array_unique($files));

        $stage = $this->makeTempDir('tutamen-stage-');

        try {
            foreach ($files as $relative) {
                $source = rtrim($repoRoot, '/').'/'.$relative;

                // ls-files lists index entries; a locally deleted file has no
                // working-tree content to snapshot, so skip it.
                if (! is_file($source)) {
                    continue;
                }

                $target = $stage.'/'.$relative;
                $dir = dirname($target);

                if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
                    throw new RuntimeException("Could not stage snapshot directory: {$dir}");
                }

                copy($source, $target);
            }

            $archive = $this->makeTempDir('tutamen-snap-').'/snapshot.tar.gz';
            $this->compress($stage, $archive);

            return $archive;
        } finally {
            $this->deleteDir($stage);
        }
    }

    private function compress(string $stage, string $archive): void
    {
        // `-C stage .` tars the staged tree with repo-relative paths and no
        // absolute or leading components. Universally supported tar flags.
        // COPYFILE_DISABLE stops macOS bsdtar from adding ._* AppleDouble
        // sidecar entries.
        $process = new Process(['tar', '-czf', $archive, '-C', $stage, '.'], env: ['COPYFILE_DISABLE' => '1']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Failed to build snapshot archive: '.trim($process->getErrorOutput()));
        }
    }

    private function makeTempDir(string $prefix): string
    {
        $base = sys_get_temp_dir().'/'.$prefix.bin2hex(random_bytes(6));

        if (! @mkdir($base, 0700, true) && ! is_dir($base)) {
            throw new RuntimeException("Could not create temp directory: {$base}");
        }

        return $base;
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
