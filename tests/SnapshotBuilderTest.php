<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tutamen\Cli\Snapshot\Git;
use Tutamen\Cli\Snapshot\SnapshotBuilder;

beforeEach(function () {
    $this->repo = tempDir('tutamen-snap-repo-');
    initGitRepo($this->repo);

    writeFile($this->repo, '.gitignore', "secret.log\nvendor/\n");
    writeFile($this->repo, 'app/Service.php', "<?php // v1\n");
    runGit($this->repo, ['add', '.']);
    runGit($this->repo, ['commit', '-q', '-m', 'init']);

    // Dirty the tracked file (uncommitted), add an ignored file and an
    // untracked-but-not-ignored file, and a vendor/ file (ignored).
    writeFile($this->repo, 'app/Service.php', "<?php // v2 modified\n");
    writeFile($this->repo, 'secret.log', "TOKEN=abc\n");
    writeFile($this->repo, 'vendor/autoload.php', "<?php\n");
    writeFile($this->repo, 'untracked.php', "<?php // new\n");
});

afterEach(function () {
    removeDir($this->repo);
});

function extractSnapshot(string $archive): string
{
    $out = tempDir('tutamen-snap-out-');
    (new Process(['tar', '-xzf', $archive, '-C', $out]))->run();

    return $out;
}

it('includes tracked files at their current (modified) working-tree content', function () {
    $archive = (new SnapshotBuilder(new Git($this->repo)))->build($this->repo);
    $out = extractSnapshot($archive);

    expect(file_get_contents($out.'/app/Service.php'))->toContain('v2 modified');

    removeDir($out);
    @unlink($archive);
});

it('excludes git-ignored files and the .git directory', function () {
    $archive = (new SnapshotBuilder(new Git($this->repo)))->build($this->repo);
    $out = extractSnapshot($archive);

    expect(is_file($out.'/secret.log'))->toBeFalse()
        ->and(is_file($out.'/vendor/autoload.php'))->toBeFalse()
        ->and(is_dir($out.'/.git'))->toBeFalse();

    removeDir($out);
    @unlink($archive);
});

it('excludes untracked files by default', function () {
    $archive = (new SnapshotBuilder(new Git($this->repo)))->build($this->repo);
    $out = extractSnapshot($archive);

    expect(is_file($out.'/untracked.php'))->toBeFalse();

    removeDir($out);
    @unlink($archive);
});

it('includes untracked-but-unignored files with --include-untracked', function () {
    $archive = (new SnapshotBuilder(new Git($this->repo)))->build($this->repo, includeUntracked: true);
    $out = extractSnapshot($archive);

    expect(is_file($out.'/untracked.php'))->toBeTrue()
        // Still never the ignored ones.
        ->and(is_file($out.'/secret.log'))->toBeFalse();

    removeDir($out);
    @unlink($archive);
});
