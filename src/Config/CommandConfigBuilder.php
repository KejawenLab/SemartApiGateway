<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Config;

use Elastica\Client;
use KejawenLab\SemartApiGateway\Command\ClearCacheCommand;
use KejawenLab\SemartApiGateway\Command\CreateIndexCommand;
use KejawenLab\SemartApiGateway\Command\HealthCheckCommand;
use KejawenLab\SemartApiGateway\Gateway;
use KejawenLab\SemartApiGateway\Route\RouteFactory;
use KejawenLab\SemartApiGateway\Service\ServiceFactory;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class CommandConfigBuilder implements ConfigBuilderInterface
{
    public function build(Gateway $container, array $config): void
    {
        $container->set('gateway.commands', function () use ($container) {
            yield new HealthCheckCommand($container->get(ServiceFactory::class), $container->get(RouteFactory::class));
            yield new ClearCacheCommand();
            yield new CreateIndexCommand($container->get(Client::class), $container->get(ServiceFactory::class));
        });
    }
}
