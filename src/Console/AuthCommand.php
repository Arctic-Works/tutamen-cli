<?php

declare(strict_types=1);

namespace Tutamen\Cli\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tutamen\Cli\Config\Config;

/**
 * Store the server URL and API token in the per-user config (0600). The token
 * is created in the dashboard under Settings → API tokens.
 */
final class AuthCommand extends Command
{
    public function __construct(private readonly ?Config $config = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('auth')
            ->setDescription('Save your Tutamen server URL and API token')
            ->addOption('server', null, InputOption::VALUE_REQUIRED, 'Tutamen server URL', 'https://app.tutamen.io')
            ->addOption('token', null, InputOption::VALUE_REQUIRED, 'API token (created in Settings → API tokens)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = $this->config ?? Config::fromEnvironment();

        $server = (string) $input->getOption('server');
        $token = $input->getOption('token');

        if (! is_string($token) || $token === '') {
            $question = (new Question('Paste your API token: '))->setHidden(true);
            $token = (string) $io->askQuestion($question);
        }

        if ($token === '') {
            $io->error('A token is required. Create one in the dashboard under Settings → API tokens.');

            return Command::FAILURE;
        }

        $config->save($server, $token);

        $io->success('Authenticated. Credentials saved to '.$config->path());

        return Command::SUCCESS;
    }
}
