<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Config;

use Elastica\Client;
use KejawenLab\SemartApiGateway\Gateway;
use KejawenLab\SemartApiGateway\Service\Service;
use KejawenLab\SemartApiGateway\Service\ServiceFactory;
use KejawenLab\SemartApiGateway\Service\ServiceStatus;
use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class ServiceConfigBuilder implements ConfigBuilderInterface
{
    public function build(Gateway $container, array $config): void
    {
        $container->set(ServiceFactory::class, function () use ($container, $config) {
            $factory = new ServiceFactory($container->get(\Redis::class), $container->get(Client::class));
            if ($container->get('gateway.cacheable')) {
                $factory->populate();

                return $factory;
            }

            $services = $config['gateway']['services'];
            foreach ($services as $name => $service) {
                Assert::isArray($service);

                if (array_key_exists('host', $service)) {
                    $host = $service['host'];
                } else {
                    Assert::keyExists($config['gateway'], 'host');

                    $host = $config['gateway']['host'];
                }

                $healthCheck = null;
                if (array_key_exists('health_check_path', $service)) {
                    $healthCheck = $service['health_check_path'];
                }

                $version = null;
                if (array_key_exists('version', $service)) {
                    $version = $service['version'];
                }

                $limit = -1;
                if (array_key_exists('limit', $service)) {
                    $limit = $service['limit'];
                }

                $weight = 1;
                if (array_key_exists('weight', $service)) {
                    $weight = $service['weight'];
                }

                $factory->addService(new Service($name, sprintf('%s%s', $host, $container->get('gateway.prefix')), $healthCheck, $version, $limit, $weight));
            }

            return $factory;
        });

        $container->set(ServiceStatus::class, function () use ($container) {
            return new ServiceStatus($container->get(ServiceFactory::class));
        });
    }
}
