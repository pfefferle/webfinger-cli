<?php

declare(strict_types=1);

namespace Pfefferle\WebFinger\Command;

use Pfefferle\WebFinger\Client;
use Pfefferle\WebFinger\Formatter;
use Pfefferle\WebFinger\Resource;
use Pfefferle\WebFinger\WebFingerException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'webfinger', description: 'Look up a WebFinger resource, like finger for the web')]
final class WebFingerCommand extends Command
{
    public function __construct(
        private readonly Client $client,
        private readonly Formatter $formatter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('resource', InputArgument::REQUIRED, 'Who to look up: user@host, acct:user@host or a URL')
            ->addOption('insecure', 'i', InputOption::VALUE_NONE, 'Fall back to plain HTTP if HTTPS fails')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Print the raw JRD document as JSON')
            ->addOption('no-profile', null, InputOption::VALUE_NONE, 'Skip fetching the h-card from the profile page');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $resource = $this->client->finger(
                (string) $input->getArgument('resource'),
                fallbackToHttp: (bool) $input->getOption('insecure'),
            );
        } catch (WebFingerException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($input->getOption('json')) {
            $output->writeln(json_encode($resource->raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $this->render($resource, $io, $output, !$input->getOption('no-profile'));

        return Command::SUCCESS;
    }

    private function render(Resource $resource, SymfonyStyle $io, OutputInterface $output, bool $withProfile): void
    {
        $io->writeln(sprintf('<info>Source:</info> %s', $resource->url));
        $io->writeln($resource->secure
            ? '<info>Transport:</info> HTTPS'
            : '<comment>Transport:</comment> <error> insecure HTTP </error>');

        if ($resource->subject !== null) {
            $io->writeln(sprintf('<info>Subject:</info> %s', $resource->subject));
        }

        if ($withProfile) {
            $profile = $this->formatter->profileRows($resource);
            if ($profile) {
                $io->section('Profile');
                (new Table($output))->setRows($profile)->setStyle('compact')->render();
            }
        }

        if ($resource->aliases) {
            $io->section('Aliases');
            $io->listing($resource->aliases);
        }

        if ($resource->links) {
            $io->section('Links');
            (new Table($output))
                ->setHeaders(['Type', 'Link'])
                ->setRows($this->formatter->linkRows($resource))
                ->render();
        }
    }
}
