<?php

namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Net_WebFinger;

/**
 * A WebFinger CLI class
 *
 * @author Matthias Pfefferle <matthias@pfefferle.org>
 */
class WebFingerCommand extends Command
{
    /**
     * Configure the command line interface
     */
    protected function configure()
    {
        $this
            ->setName('webfinger')
            ->setDescription('Get someones infos')
            ->setDefinition(array())
            ->addArgument('resource', InputArgument::REQUIRED, 'Who do you want to lookup?')
            ->addOption('insecure', 'i', InputOption::VALUE_NONE, 'Fallback to http.');
    }

    /**
     * Execute the command
     *
     * @param InputInterface  $input  the user input
     * @param OutputInterface $output the command line output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $webfinger  = new Net_WebFinger();

        // is http fallback enabled
        if ($input->getOption('insecure')) {
            $webfinger->fallbackToHttp = true;
        }

        $react = $webfinger->finger($input->getArgument("resource"));

        $output->writeln("<info>Data source URL: {$react->url}</info>");

        $output->writeln("<comment>Information secure: " . var_export($react->secure, true) . "</comment>");

        // check for errors
        if ($react->error !== null) {
            $this->displayError($react->error, $output);
            return Command::FAILURE;
        }

        $helper = new \Lib\WebFingerHelper($react);

        // show profile
        $output->writeln("\n<info>Profile:</info>");

        $profile = $helper->getProfileTableView();

        $table = new Table($output);
        $table->setRows($profile);
        $table->setStyle('compact');
        $table->render();

        // show alternate identifier
        $output->writeln("\n<info>Alternate Identifier:</info>");

        foreach ($react->aliases as $alias) {
            $output->writeln(" * $alias");
        }

        // show links
        $output->writeln("\n<info>More Links:</info>");

        $links = $helper->getLinksTableView();

        $table = new Table($output);
        $table
            ->setHeaders(array('Type', 'Link'))
            ->setRows($links);
        $table->render();

        return Command::SUCCESS;
    }

    /**
     * handle errors
     *
     * @param \Exception                                       $error  the error stack
     * @param Symfony\Component\Console\Output\OutputInterface $output the ooutput interface
     * @param string                                           $prefix the prefix to indent the text
     */
    protected function displayError(\Exception $error, OutputInterface $output, $prefix = '')
    {
        $output->writeln('<error>' . $prefix . $error->getMessage() . '</error>');
        if ($error->getPrevious()) {
            $this->displayError($error->getPrevious(), $output, "\t - ");
        }
    }
}
