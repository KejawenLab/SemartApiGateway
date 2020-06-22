<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Aggregate;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class HandlerNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $handler)
    {
        parent::__construct(sprintf('Handler class "%s" is not found', $handler));
    }
}
