<?php

declare(strict_types=1);

namespace Tutamen\Cli\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tutamen\Cli\Config\Config;

/**
 * Forget the stored credentials by deleting the config file.
 */
final class AuthLogoutCommand extends Command
{
    public function __construct(private readonly ?Config $config = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('auth:logout')->setDescription('Remove the stored server URL and API token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = $this->config ?? Config::fromEnvironment();

        if ($config->clear()) {
            $io->success('Logged out. Credentials removed.');
        } else {
            $io->writeln('Already logged out.');
        }

        return Command::SUCCESS;
    }
}
