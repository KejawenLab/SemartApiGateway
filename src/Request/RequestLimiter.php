<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Request;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class RequestLimiter
{
    private const CACHE_KEY = 'bb72ffb1a3064d36cfe615ce5ec2bba2d762fe95';

    private const REQUEST_LIMIT_PER_SECOND = 17;

    private const REQUEST_LIMIT_LIFETIME = 60;

    private $redis;

    private $requestLimit;

    private $limitLifetime;

    public function __construct(\Redis $redis, int $requestLimit = self::REQUEST_LIMIT_PER_SECOND, int $limitLifetime = self::REQUEST_LIMIT_LIFETIME)
    {
        $this->redis = $redis;
        $this->requestLimit = $requestLimit;
        $this->limitLifetime = $limitLifetime;
    }

    public function allow(Request $request, array $excludes = []): bool
    {
        $path = $request->getPathInfo();
        if (in_array($path, $excludes)) {
            return true;
        }

        $key = sprintf('%s:%s:%s', static::CACHE_KEY, $path, $request->getClientIp());
        if (!$limit = $this->redis->get($key)) {
            $limit = 1;
            $this->redis->set($key, $limit);
            $this->redis->expire($key, $this->limitLifetime);
            app()->pool($key);

            return true;
        }

        $limit = (int) $limit;
        if ($limit >= $this->requestLimit) {
            return false;
        }

        $limit++;
        $this->redis->set($key, $limit);
        $this->redis->expire($key, $this->limitLifetime);
        app()->pool($key);

        return true;
    }
}
