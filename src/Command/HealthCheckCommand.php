<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Command;

use KejawenLab\SemartApiGateway\Route\RouteFactory;
use KejawenLab\SemartApiGateway\Service\ServiceFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class HealthCheckCommand extends Command
{
    private $serviceFactory;

    private $routeFactory;

    public function __construct(ServiceFactory $serviceFactory, RouteFactory $routeFactory)
    {
        $this->serviceFactory = $serviceFactory;
        $this->routeFactory = $routeFactory;

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
        $client = HttpClient::create();
        $routes = $this->routeFactory->routes();
        foreach ($routes as $route) {
            foreach ($route->getHandlers() as $service) {
                if ($service->getHealthCheckPath()) {
                    try {
                        $response = $client->request('GET', $service->getUrl($service->getHealthCheckPath()));
                        if (Response::HTTP_OK === $response->getStatusCode()) {
                            $this->serviceFactory->up($service);
                        } else {
                            $this->serviceFactory->down($service);
                        }
                    } catch (\Exception $e) {
                        $this->serviceFactory->down($service);
                    }
                }
            }
        }

        return 0;
    }
}
