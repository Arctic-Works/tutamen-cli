<?php

declare(strict_types=1);

namespace Tutamen\Cli\Console;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tutamen\Cli\Skills\SkillInstaller;

/**
 * Install the bundled `tutamen-security` agent skill into Claude Code or
 * Codex CLI. The skill ships with this package, so a CLI-only customer can
 * install it without ever touching the Tutamen repo:
 *
 *   tutamen skill:install                 # .claude/skills in this project
 *   tutamen skill:install --global        # ~/.claude/skills (all projects)
 *   tutamen skill:install --agent=codex   # .codex/skills instead
 *   tutamen skill:install --print         # write SKILL.md to stdout
 */
final class SkillInstallCommand extends Command
{
    public function __construct(private readonly ?SkillInstaller $installer = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('skill:install')
            ->setDescription('Install the Tutamen agent skill into Claude Code or Codex')
            ->addOption('agent', null, InputOption::VALUE_REQUIRED, 'Target agent: '.implode('|', SkillInstaller::agents()), 'claude')
            ->addOption('global', null, InputOption::VALUE_NONE, 'Install for all projects (in your home directory) instead of just this one')
            ->addOption('print', null, InputOption::VALUE_NONE, 'Print the skill to stdout instead of installing it (for any other agent)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $installer = $this->installer ?? $this->defaultInstaller();

        if ((bool) $input->getOption('print')) {
            $output->write($installer->contents());

            return Command::SUCCESS;
        }

        $agent = (string) $input->getOption('agent');

        if (! SkillInstaller::isValidAgent($agent)) {
            $io->error("Unknown --agent '{$agent}'. Use one of: ".implode(', ', SkillInstaller::agents()).'.');

            return Command::FAILURE;
        }

        try {
            $result = $installer->install($agent, (bool) $input->getOption('global'));
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $verb = $result['status'] === SkillInstaller::STATUS_UPDATED ? 'Updated' : 'Installed';
        $io->success("{$verb} the tutamen-security skill at {$result['path']}");
        $io->writeln(sprintf(
            'Ask %s to "scan this repo for security issues" and it will run `tutamen scan --agent` and follow the findings.',
            $agent === 'codex' ? 'Codex' : 'Claude Code',
        ));

        return Command::SUCCESS;
    }

    private function defaultInstaller(): SkillInstaller
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: (string) getcwd();

        return new SkillInstaller(rtrim((string) $home, '/'), (string) getcwd());
    }
}
