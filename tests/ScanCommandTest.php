<?php

declare(strict_types=1);

use Symfony\Component\Console\Tester\CommandTester;
use Tutamen\Cli\Agent\PromptCache;
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

function runAgentScan(FakeApiClient $api, PromptCache $cache, array $args = []): CommandTester
{
    $command = new ScanCommand($api, sleeper: fn (int $s) => null, promptCache: $cache);
    $tester = new CommandTester($command);
    $tester->execute(array_merge(['--server' => 'http://localhost', '--token' => '1|tok', '--agent' => true], $args));

    return $tester;
}

function freshPromptCache(): PromptCache
{
    return new PromptCache(tempDir('tutamen-agent-cache-').'/agent-prompt.json', fn (): int => 1000);
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

it('emits a single agent envelope merging prompt, scan and findings', function () {
    $api = new FakeApiClient(
        [completedEnvelope([
            ['rule_id' => 'laravel.app-key-exposed', 'severity' => 'critical', 'file_path' => '.env', 'start_line' => 3, 'message' => 'APP_KEY committed.', 'fix_md' => 'Rotate it.'],
        ])],
        prompt: ['version' => 7, 'prompt' => 'Summarize, ask, fix, re-scan.'],
    );

    $tester = runAgentScan($api, freshPromptCache(), ['--fail-on' => 'high']);
    $decoded = json_decode(trim($tester->getDisplay()), true);

    expect($decoded)->toBeArray()
        ->and($decoded['envelope_version'])->toBe(1)
        ->and($decoded['prompt_version'])->toBe(7)
        ->and($decoded['prompt'])->toBe('Summarize, ask, fix, re-scan.')
        ->and($decoded['scan']['id'])->toBe('cli-scan-123')
        ->and($decoded['scan']['status'])->toBe('completed')
        ->and($decoded['findings'][0]['rule_id'])->toBe('laravel.app-key-exposed')
        ->and($decoded['findings'][0]['fix_md'])->toBe('Rotate it.')
        ->and($api->promptCalls)->toBe(1)
        ->and($tester->getStatusCode())->toBe(ExitCode::FINDINGS);
});

it('prints only JSON in agent mode — never the findings table', function () {
    $api = new FakeApiClient([completedEnvelope([
        ['rule_id' => 'laravel.debug-enabled', 'severity' => 'high', 'file_path' => 'config/app.php', 'start_line' => 1, 'message' => 'Debug on.'],
    ])]);

    $tester = runAgentScan($api, freshPromptCache());

    // Whole stdout parses as one JSON document — no human-formatted lines mixed in.
    expect(json_decode(trim($tester->getDisplay()), true))->toBeArray()
        ->and($tester->getDisplay())->not->toContain('Scanning…');
});

it('honours the prompt cache within the TTL and refetches once it expires', function () {
    $now = 5000;
    $cache = new PromptCache(tempDir('tutamen-agent-cache-ttl-').'/agent-prompt.json', function () use (&$now): int {
        return $now;
    });
    $api = new FakeApiClient([completedEnvelope([])]);

    runAgentScan($api, $cache);
    runAgentScan($api, $cache);
    expect($api->promptCalls)->toBe(1);

    $now += 301; // past the 5-minute TTL
    runAgentScan($api, $cache);
    expect($api->promptCalls)->toBe(2);
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
