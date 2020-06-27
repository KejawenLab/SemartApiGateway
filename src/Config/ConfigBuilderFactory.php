<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Config;

use KejawenLab\SemartApiGateway\Gateway;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class ConfigBuilderFactory implements ConfigBuilderInterface
{
    public function build(Gateway $container, array $config): void
    {
        foreach ($this->getBuilders() as $builder) {
            $builder->build($container, $config);
        }
    }

    /**
     * @return ConfigBuilderInterface[]
     */
    public function getBuilders(): iterable
    {
        yield new ContainerConfigBuilder();
        yield new CommandConfigBuilder();
        yield new AuthenticationConfigBuilder();
        yield new ServiceConfigBuilder();
        yield new RequestHandlerConfigBuilder();
        yield new RouteConfigBuilder();
    }
}
