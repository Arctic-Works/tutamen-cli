<?php

declare(strict_types=1);

use Symfony\Component\Console\Tester\CommandTester;
use Tutamen\Cli\Config\Config;
use Tutamen\Cli\Console\ScanCommand;
use Tutamen\Cli\Findings\ExitCode;
use Tutamen\Cli\Tests\Support\FakeApiClient;

beforeEach(function () {
    $this->repo = tempDir('tutamen-scan-repo-');
    initGitRepo($this->repo);
    writeFile($this->repo, 'app/Service.php', "<?php\n");
    runGit($this->repo, ['add', '.']);
    runGit($this->repo, ['commit', '-q', '-m', 'init']);

    $this->cwd = getcwd();
    chdir($this->repo);
});

afterEach(function () {
    chdir($this->cwd);
    removeDir($this->repo);
});

function completedEnvelope(array $findings): array
{
    return [
        'id' => 'cli-scan-123',
        'status' => 'completed',
        'finished' => true,
        'stats' => ['total_findings' => count($findings)],
        'findings' => $findings,
    ];
}

function runScan(FakeApiClient $api, array $args): CommandTester
{
    $command = new ScanCommand($api, sleeper: fn (int $s) => null);
    $tester = new CommandTester($command);
    $tester->execute(array_merge(['--server' => 'http://localhost', '--token' => '1|tok'], $args));

    return $tester;
}

it('uploads a snapshot, polls, renders findings and exits 1', function () {
    $api = new FakeApiClient([
        ['status' => 'queued', 'finished' => false],
        completedEnvelope([
            ['rule_id' => 'laravel.app-key-exposed', 'severity' => 'critical', 'file_path' => '.env', 'start_line' => 3, 'message' => 'APP_KEY committed.'],
        ]),
    ]);

    $tester = runScan($api, ['--fail-on' => 'high']);

    expect($tester->getStatusCode())->toBe(ExitCode::FINDINGS)
        ->and($api->createCalls)->toBe(1)
        ->and($api->createMeta['project_name'])->toBe(basename($this->repo))
        ->and($api->createMeta['branch'])->toBe('main')
        ->and($tester->getDisplay())->toContain('laravel.app-key-exposed');
});

it('exits 0 when the scan is clean', function () {
    $api = new FakeApiClient([completedEnvelope([])]);

    $tester = runScan($api, []);

    expect($tester->getStatusCode())->toBe(ExitCode::CLEAN)
        ->and($tester->getDisplay())->toContain('clean');
});

it('prints only the raw envelope with --json', function () {
    $api = new FakeApiClient([completedEnvelope([
        ['rule_id' => 'laravel.debug-enabled', 'severity' => 'high', 'file_path' => 'config/app.php', 'start_line' => 1, 'message' => 'Debug on.'],
    ])]);

    $tester = runScan($api, ['--json' => true]);
    $decoded = json_decode(trim($tester->getDisplay()), true);

    expect($decoded)->toBeArray()
        ->and($decoded['status'])->toBe('completed')
        ->and($tester->getStatusCode())->toBe(ExitCode::FINDINGS);
});

it('errors (exit 2) when not authenticated', function () {
    $command = new ScanCommand(new FakeApiClient([completedEnvelope([])]), sleeper: fn (int $s) => null, config: new Config(tempDir('tutamen-empty-')));
    $tester = new CommandTester($command);
    $tester->execute([]); // no --server/--token, empty config

    expect($tester->getStatusCode())->toBe(ExitCode::ERROR)
        ->and($tester->getDisplay())->toContain('tutamen auth');
});

it('errors (exit 2) outside a git repository', function () {
    $plain = tempDir('tutamen-nongit-');
    chdir($plain);

    $tester = runScan(new FakeApiClient([completedEnvelope([])]), []);

    expect($tester->getStatusCode())->toBe(ExitCode::ERROR)
        ->and($tester->getDisplay())->toContain('git repository');

    chdir($this->repo);
    removeDir($plain);
});

it('errors (exit 2) when the scan fails server-side', function () {
    $api = new FakeApiClient([['status' => 'failed', 'finished' => true, 'error' => 'container exploded', 'findings' => null]]);

    $tester = runScan($api, []);

    expect($tester->getStatusCode())->toBe(ExitCode::ERROR)
        ->and($tester->getDisplay())->toContain('container exploded');
});

it('skips scanning in hook mode when the branch is excluded', function () {
    writeFile($this->repo, '.tutamen.json', json_encode(['hooks' => ['branches' => '^main$']]));
    runGit($this->repo, ['checkout', '-q', '-b', 'feature/login']);

    $api = new FakeApiClient([completedEnvelope([])]);
    $tester = runScan($api, ['--hook' => true]);

    expect($tester->getStatusCode())->toBe(ExitCode::CLEAN)
        ->and($api->createCalls)->toBe(0)
        ->and($tester->getDisplay())->toContain('skipping');
});
