<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Handler;

use KejawenLab\SemartApiGateway\Route\Route;
use KejawenLab\SemartApiGateway\Service\Service;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class HandlerFactory
{
    /**
     * @var HandlerInterface[]
     */
    private $handlers;

    public function __construct(array $handlers)
    {
        foreach ($handlers as $handler) {
            $this->addHandler($handler);
        }
    }

    public function handle(Route $route): ?Service
    {
        foreach ($this->handlers as $handler) {
            if ($handler->support($route)) {
                return $handler->handle($route);
            }
        }

        throw new CanNotHandleRouteException();
    }

    private function addHandler(HandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }
}
