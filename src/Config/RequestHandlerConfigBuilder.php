<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Config;

use Elastica\Client;
use KejawenLab\SemartApiGateway\Gateway;
use KejawenLab\SemartApiGateway\Handler\HandlerFactory;
use KejawenLab\SemartApiGateway\Handler\RandomHandler;
use KejawenLab\SemartApiGateway\Handler\RoundRobinHandler;
use KejawenLab\SemartApiGateway\Handler\StickyHandler;
use KejawenLab\SemartApiGateway\Handler\WeightHandler;
use KejawenLab\SemartApiGateway\Request\AuthenticationHandler;
use KejawenLab\SemartApiGateway\Request\RequestHandler;
use KejawenLab\SemartApiGateway\Route\RouteFactory;
use KejawenLab\SemartApiGateway\Service\Resolver;
use KejawenLab\SemartApiGateway\Service\ServiceFactory;
use KejawenLab\SemartApiGateway\Service\Statistic;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class RequestHandlerConfigBuilder implements ConfigBuilderInterface
{
    public function build(Gateway $container, array $config): void
    {
        $container->set(RandomHandler::class, function () use ($container) {
            return new RandomHandler();
        });

        $container->set(RoundRobinHandler::class, function () use ($container) {
            return new RoundRobinHandler();
        });

        $container->set(StickyHandler::class, function () use ($container) {
            return new StickyHandler();
        });

        $container->set(WeightHandler::class, function () use ($container) {
            return new WeightHandler();
        });

        $container->set(HandlerFactory::class, function () use ($container) {
            return new HandlerFactory([
                $container->get(RoundRobinHandler::class),
                $container->get(RandomHandler::class),
                $container->get(StickyHandler::class),
                $container->get(WeightHandler::class),
            ]);
        });

        $container->set(Resolver::class, function () use ($container) {
            return new Resolver($container->get(RouteFactory::class), $container->get(HandlerFactory::class), $container->get(ServiceFactory::class));
        });

        $container->set(Statistic::class, function () use ($container) {
            return new Statistic($container->get(ServiceFactory::class), $container->get(Client::class));
        });

        $container->set(RequestHandler::class, function () use ($container) {
            return new RequestHandler(
                $container->get(AuthenticationHandler::class),
                $container->get(Resolver::class),
                $container->get(ServiceFactory::class),
                $container->get(RouteFactory::class),
                $container->get(\Redis::class),
                $container->get('gateway.trusted_ips')
            );
        });
    }
}
