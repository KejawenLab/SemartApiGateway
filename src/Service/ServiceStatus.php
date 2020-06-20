<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Service;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class ServiceStatus
{
    public const ROUTE_NAME = 'gateway_service_status';

    public const ROUTE_PATH = 'gateway/status';

    private $serviceFactory;

    public function __construct(ServiceFactory $serviceFactory)
    {
        $this->serviceFactory = $serviceFactory;
    }

    public function status(): array
    {
        $result = [];
        $services = $this->serviceFactory->getServices();
        foreach ($services as $service) {
            $result[$service->getName()] = [
                'enable' => $service->isEnabled(),
                'down' => $service->isDown(),
                'limit' => $service->isLimit(),
                'hit' => $service->getHit(),
            ];
        }

        return $result;
    }
}
