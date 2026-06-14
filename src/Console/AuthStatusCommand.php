<?php

declare(strict_types=1);

namespace Tutamen\Cli\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tutamen\Cli\Config\Config;

/**
 * Report whether the CLI is authenticated, showing the server and a masked
 * token (never the secret in full).
 */
final class AuthStatusCommand extends Command
{
    public function __construct(private readonly ?Config $config = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('auth:status')->setDescription('Show the current authentication status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = $this->config ?? Config::fromEnvironment();
        $data = $config->load();

        if (empty($data['server']) || empty($data['token'])) {
            $io->warning('Not authenticated. Run `tutamen auth` to get started.');

            return Command::FAILURE;
        }

        $io->writeln('Server: '.$data['server']);
        $io->writeln('Token:  '.$this->mask((string) $data['token']));

        return Command::SUCCESS;
    }

    private function mask(string $token): string
    {
        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 4).str_repeat('*', 6).substr($token, -4);
    }
}
