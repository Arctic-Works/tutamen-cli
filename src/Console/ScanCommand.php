<?php

declare(strict_types=1);

namespace Tutamen\Cli\Console;

use Closure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tutamen\Cli\Config\Config;
use Tutamen\Cli\Config\ProjectConfig;
use Tutamen\Cli\Findings\ExitCode;
use Tutamen\Cli\Findings\FindingsRenderer;
use Tutamen\Cli\Findings\Severity;
use Tutamen\Cli\Hooks\BranchMatcher;
use Tutamen\Cli\Http\ApiClient;
use Tutamen\Cli\Http\ApiException;
use Tutamen\Cli\Http\GuzzleApiClient;
use Tutamen\Cli\Snapshot\Git;
use Tutamen\Cli\Snapshot\SnapshotBuilder;

/**
 * Snapshot the local working tree, upload it, wait for the server-side scan,
 * and print the findings with a meaningful exit code (0 clean / 1 findings at
 * or above the threshold / 2 error). The pre-push hook and CI both rely on
 * that exit code.
 */
final class ScanCommand extends Command
{
    private const POLL_INTERVAL_SECONDS = 2;

    private readonly ApiClient $api;

    private readonly Closure $sleeper;

    public function __construct(?ApiClient $api = null, ?Closure $sleeper = null, private readonly ?Config $config = null)
    {
        parent::__construct();
        $this->api = $api ?? new GuzzleApiClient;
        $this->sleeper = $sleeper ?? static fn (int $seconds) => sleep($seconds);
    }

    protected function configure(): void
    {
        $this->setName('scan')
            ->setDescription('Scan the local working tree before pushing')
            ->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Threshold that makes the scan fail: critical|high|medium|low|any')
            ->addOption('include-untracked', null, InputOption::VALUE_NONE, 'Also scan untracked (but not git-ignored) files')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the raw results envelope and nothing else')
            ->addOption('hook', null, InputOption::VALUE_NONE, 'Hook mode: honour .tutamen.json branch gating')
            ->addOption('server', null, InputOption::VALUE_REQUIRED, 'Override the configured server URL')
            ->addOption('token', null, InputOption::VALUE_REQUIRED, 'Override the configured API token')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Client wait budget in seconds', '600');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = (bool) $input->getOption('json');

        $git = new Git((string) getcwd());

        if (! $git->isRepository()) {
            return $this->fail($output, $json, 'tutamen must be run inside a git repository.');
        }

        $repoRoot = $git->topLevel();
        $git = new Git($repoRoot);

        $config = $this->config ?? Config::fromEnvironment();
        $stored = $config->load();
        $server = (string) ($input->getOption('server') ?? $stored['server'] ?? '');
        $token = (string) ($input->getOption('token') ?? $stored['token'] ?? '');

        if ($server === '' || $token === '') {
            return $this->fail($output, $json, 'Not authenticated. Run `tutamen auth` first.');
        }

        try {
            $project = ProjectConfig::load($repoRoot);
        } catch (\RuntimeException $e) {
            return $this->fail($output, $json, $e->getMessage());
        }

        $branch = $git->currentBranch();

        if ((bool) $input->getOption('hook') && ! BranchMatcher::shouldRun($branch, $project->hookBranches())) {
            if (! $json) {
                $output->writeln("tutamen: branch '{$branch}' is excluded by .tutamen.json — skipping scan.");
            }

            return ExitCode::CLEAN;
        }

        $failOn = (string) ($input->getOption('fail-on') ?? $project->failOn() ?? 'any');

        if (! Severity::isValidThreshold($failOn)) {
            return $this->fail($output, $json, "Invalid --fail-on value '{$failOn}'. Use one of: ".implode(', ', Severity::thresholds()));
        }

        $tarball = null;

        try {
            $tarball = (new SnapshotBuilder($git))->build($repoRoot, (bool) $input->getOption('include-untracked'));

            $created = $this->api->createScan($server, $token, $tarball, [
                'project_name' => basename($repoRoot),
                'branch' => $branch,
                'commit_sha' => $git->headSha(),
            ]);

            $envelope = $this->poll($created['id'], $server, $token, (int) $input->getOption('timeout'), $output, $json);
        } catch (ApiException $e) {
            return $this->fail($output, $json, $e->getMessage());
        } catch (\RuntimeException $e) {
            return $this->fail($output, $json, $e->getMessage());
        } finally {
            if ($tarball !== null && is_file($tarball)) {
                @unlink($tarball);
            }
        }

        if (($envelope['status'] ?? null) === 'failed') {
            return $this->fail($output, $json, 'Scan failed: '.($envelope['error'] ?? 'unknown error'));
        }

        /** @var list<array<string, mixed>> $findings */
        $findings = is_array($envelope['findings'] ?? null) ? $envelope['findings'] : [];

        if ($json) {
            $output->writeln((string) json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $output->writeln((new FindingsRenderer)->render($envelope));
        }

        return ExitCode::forFindings($findings, $failOn);
    }

    /**
     * @return array<string, mixed>
     */
    private function poll(string $id, string $server, string $token, int $timeout, OutputInterface $output, bool $json): array
    {
        $waited = 0;

        if (! $json) {
            $output->writeln('Scanning… (this usually takes 15–60s)');
        }

        while (true) {
            $envelope = $this->api->getScan($server, $token, $id);

            if (($envelope['finished'] ?? false) === true
                || in_array($envelope['status'] ?? null, ['completed', 'failed'], true)) {
                return $envelope;
            }

            if ($waited >= $timeout) {
                throw new ApiException('Timed out waiting for the scan to finish.');
            }

            ($this->sleeper)(self::POLL_INTERVAL_SECONDS);
            $waited += self::POLL_INTERVAL_SECONDS;
        }
    }

    private function fail(OutputInterface $output, bool $json, string $message): int
    {
        if ($json) {
            $output->writeln((string) json_encode(['error' => $message]));
        } else {
            $output->writeln('<error>tutamen: '.$message.'</error>');
        }

        return ExitCode::ERROR;
    }
}
