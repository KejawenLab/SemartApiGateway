<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Config;

use KejawenLab\SemartApiGateway\Gateway;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
interface ConfigBuilderInterface
{
    public function build(Gateway $container, array $config): void;
}
