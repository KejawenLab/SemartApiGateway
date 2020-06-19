<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Service;

use Symfony\Component\HttpFoundation\Response;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class Statistic
{
    private const CACHE_KEY = 'f8d0a9964a7b2285f2af4f599697182e3417a5cc';

    public const ROUTE_NAME = 'gateway_statistic';

    public const ROUTE_PATH = 'gateway/statistic';

    private $factory;

    private $redis;

    public function __construct(ServiceFactory $factory, \Redis $redis)
    {
        $this->factory = $factory;
        $this->redis = $redis;
    }

    public function init(): void
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->redis->del(static::CACHE_KEY);
        $statistic = [];
        $services = $this->factory->getServices();
        foreach ($services as $service) {
            $statistic[$service->getName()] = [];
        }

        $this->redis->set(static::CACHE_KEY, serialize($statistic));
    }

    public function statistic(): array
    {
        $result = [];
        $services = $this->factory->getServices();
        $statistic = $this->getStat();
        foreach ($services as $service) {
            if (!array_key_exists($service->getName(), $statistic)) {
                $result[$service->getName()] = [
                    'hit' => 0,
                    'uptime' => $service->isEnabled()? 100: 0,
                ];

                continue;
            }

            $hit = count($statistic[$service->getName()]);
            $uptime = count(array_filter($statistic[$service->getName()], function ($v) {
                if ($v['code'] >= Response::HTTP_OK && $v['code'] < Response::HTTP_INTERNAL_SERVER_ERROR) {
                    return true;
                }

                return false;
            }));
            $result[$service->getName()] = [
                'hit' => $hit,
                'uptime' => 0 === $hit ? 100: (($uptime / $hit) * 100),
            ];
        }

        return $result;
    }

    public function stat(Service $service, array $data): void
    {
        $statistic = $this->getStat();
        if (!array_key_exists($service->getName(), $statistic)) {
            $statistic[$service->getName()] = [];
        }

        $statistic[$service->getName()][] = $this->formatting($data);

        $this->redis->set(static::CACHE_KEY, serialize($statistic));
    }

    private function getStat(): array
    {
        $statistic = $this->redis->get(static::CACHE_KEY);
        if (!$statistic) {
            $this->init();

            $statistic = $this->redis->get(static::CACHE_KEY);
        }

        return unserialize($statistic);
    }

    private function formatting(array $data): array
    {
        return [
            'path' => $data['path'],
            'ip' => $data['ip'],
            'method' => $data['method'],
            'hit' => date('Y-m-d H:i:s'),
            'code' => $data['code'],
        ];
    }
}
