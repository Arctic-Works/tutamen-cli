<?php

declare(strict_types=1);

namespace Tutamen\Cli\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tutamen\Cli\Hooks\HookInstaller;
use Tutamen\Cli\Snapshot\Git;

/**
 * Remove the tutamen pre-push hook block, leaving any foreign hook content
 * intact. With no flags it cleans both native and Husky hooks.
 */
final class HooksUninstallCommand extends Command
{
    public function __construct(private readonly ?HookInstaller $installer = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('hooks:uninstall')
            ->setDescription('Remove the tutamen pre-push hook')
            ->addOption('husky', null, InputOption::VALUE_NONE, 'Only remove the Husky hook (.husky/pre-push)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $installer = $this->installer ?? new HookInstaller;

        $git = new Git((string) getcwd());

        if (! $git->isRepository()) {
            $io->error('tutamen hooks:uninstall must be run inside a git repository.');

            return Command::FAILURE;
        }

        $repoRoot = $git->topLevel();
        $huskyOnly = (bool) $input->getOption('husky');

        $removed = false;

        if ($huskyOnly) {
            $removed = $installer->uninstall($repoRoot, husky: true);
        } else {
            // Clean both kinds so a switch between native and Husky never leaves
            // a stale hook behind.
            $removed = $installer->uninstall($repoRoot, husky: false) || $removed;
            $removed = $installer->uninstall($repoRoot, husky: true) || $removed;
        }

        if ($removed) {
            $io->success('Removed the tutamen pre-push hook.');
        } else {
            $io->writeln('No tutamen pre-push hook was installed.');
        }

        return Command::SUCCESS;
    }
}
