<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Command;

use KejawenLab\SemartApiGateway\Gateway;
use Symfony\Component\Console\Application as Base;
use Symfony\Component\Console\Command\Command;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class Application extends Base
{
    public function __construct(Gateway $kernel)
    {
        $kernel->build();

        /** @var Command $command */
        foreach ($kernel['gateway.commands'] as $command) {
            $this->add($command);
        }

        parent::__construct(Gateway::NAME, Gateway::VERSION);
    }
}
