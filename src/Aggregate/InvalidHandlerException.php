<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Aggregate;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class InvalidHandlerException extends \InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct(sprintf('Handler must implement interface "%s"', AggregateRequestInterface::class));
    }
}
