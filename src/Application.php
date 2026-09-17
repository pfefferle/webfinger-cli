<?php

declare(strict_types=1);

namespace Pfefferle\WebFinger;

use Pfefferle\WebFinger\Command\WebFingerCommand;
use Symfony\Component\Console\Application as BaseApplication;

/**
 * Single-command console application: `webfinger <resource>`.
 */
final class Application extends BaseApplication
{
    public const VERSION = '2.0.0';

    public function __construct(?Client $client = null, ?Formatter $formatter = null)
    {
        parent::__construct('WebFinger', self::VERSION);

        $command = new WebFingerCommand($client ?? new Client(), $formatter ?? new Formatter());
        $this->addCommand($command);
        $this->setDefaultCommand((string) $command->getName(), true);
    }
}
