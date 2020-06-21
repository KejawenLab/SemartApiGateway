<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Command;

use Elastica\Client;
use Elastica\Document;
use KejawenLab\SemartApiGateway\Service\ServiceFactory;
use KejawenLab\SemartApiGateway\Service\Statistic;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class CreateIndexCommand extends Command
{
    private $elasticsearch;

    private $serviceFactory;

    public function __construct(Client $elasticsearch, ServiceFactory $serviceFactory)
    {
        $this->elasticsearch = $elasticsearch;
        $this->serviceFactory = $serviceFactory;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('create-index')
            ->setDescription('Semart Api Gateway Create Elasticsearch Index')
            ->addOption('override', InputOption::VALUE_NONE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $override = false;
        if ($input->getOption('override')) {
            $override = true;
        }

        $statisticIndex = $this->elasticsearch->getIndex(Statistic::INDEX_NAME);
        $statisticIndex->create([], $override);
        $servicesIndex = $this->elasticsearch->getIndex(ServiceFactory::INDEX_NAME);
        $servicesIndex->create([], $override);

        foreach ($this->serviceFactory->getServices() as $service) {
            $servicesIndex->addDocument(new Document(sha1(sprintf('%s_%s', ServiceFactory::CACHE_KEY, $service->getName())), $service->toArray()));
        }

        return 0;
    }
}
