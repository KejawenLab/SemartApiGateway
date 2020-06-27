<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Config;

use KejawenLab\SemartApiGateway\Gateway;
use Pimple\Exception\UnknownIdentifierException;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class ContainerConfigBuilder implements ConfigBuilderInterface
{
    public function build(Gateway $container, array $config): void
    {
        foreach ($config['gateway']['containers'] as $name => $service) {
            $container->set($name, function () use ($container, $name, $service) {
                $arguments = [];
                foreach ($service as $key => $argument) {
                    try {
                        $arguments[$key] = $container->get($argument);
                    } catch (UnknownIdentifierException $e) {
                        $arguments[$key] = $argument;
                    }
                }

                return new $name(...$arguments);
            });
        }
    }
}
