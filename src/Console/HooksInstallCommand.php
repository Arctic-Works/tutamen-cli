<?php

declare(strict_types=1);

namespace Tutamen\Cli\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tutamen\Cli\Config\ProjectConfig;
use Tutamen\Cli\Findings\Severity;
use Tutamen\Cli\Hooks\HookInstaller;
use Tutamen\Cli\Snapshot\Git;

/**
 * Install a pre-push hook (native or Husky). --branches / --fail-on are written
 * into the committed .tutamen.json so the whole team shares them; the hook
 * itself just runs `tutamen scan --hook`, which reads that config at push time.
 */
final class HooksInstallCommand extends Command
{
    public function __construct(private readonly ?HookInstaller $installer = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('hooks:install')
            ->setDescription('Install a pre-push hook that scans before each push')
            ->addOption('husky', null, InputOption::VALUE_NONE, 'Install a Husky hook (.husky/pre-push) instead of a native one')
            ->addOption('branches', null, InputOption::VALUE_REQUIRED, 'Regex of branches to scan (written to .tutamen.json)')
            ->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Severity threshold to fail on (written to .tutamen.json)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $installer = $this->installer ?? new HookInstaller;

        $git = new Git((string) getcwd());

        if (! $git->isRepository()) {
            $io->error('tutamen hooks:install must be run inside a git repository.');

            return Command::FAILURE;
        }

        $repoRoot = $git->topLevel();

        $branches = $input->getOption('branches');
        $failOn = $input->getOption('fail-on');

        if (is_string($failOn) && ! Severity::isValidThreshold($failOn)) {
            $io->error("Invalid --fail-on value '{$failOn}'. Use one of: ".implode(', ', Severity::thresholds()));

            return Command::FAILURE;
        }

        if (is_string($branches) || is_string($failOn)) {
            ProjectConfig::writeHookSettings($repoRoot, is_string($branches) ? $branches : null, is_string($failOn) ? $failOn : null);
            $io->writeln('Wrote hook settings to '.ProjectConfig::FILENAME.' (commit it so your team shares them).');
        }

        $husky = (bool) $input->getOption('husky');
        $status = $installer->install($repoRoot, $husky);
        $path = $installer->hookPath($repoRoot, $husky);

        $io->success(match ($status) {
            HookInstaller::STATUS_CREATED => "Created pre-push hook at {$path}",
            HookInstaller::STATUS_UPDATED => "Updated the tutamen block in {$path}",
            default => "Appended the tutamen block to your existing hook at {$path}",
        });

        return Command::SUCCESS;
    }
}
