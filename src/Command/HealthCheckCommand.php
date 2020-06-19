<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Command;

use KejawenLab\SemartApiGateway\Service\ServiceFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class HealthCheckCommand extends Command
{
    private $serviceFactory;

    public function __construct(ServiceFactory $serviceFactory)
    {
        $this->serviceFactory = $serviceFactory;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('health-check')
            ->setDescription('Semart Api Gateway Health Check')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        dump($this->serviceFactory);

        return 0;
    }
}
