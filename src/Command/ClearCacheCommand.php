<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class ClearCacheCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('clear-cache')
            ->setDescription('Semart Api Gateway Clear Configuration Cache')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        app()->clean();
        $output->writeln('<info>Clear all cache</info>');

        return 0;
    }
}
