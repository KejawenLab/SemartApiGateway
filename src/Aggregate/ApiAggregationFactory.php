<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Aggregate;

use KejawenLab\SemartApiGateway\Gateway;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class ApiAggregationFactory
{
    private $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function registerRoutes(RouteCollection $routeCollection, array $agregates, string $prefix = ''): RouteCollection
    {
        foreach ($agregates as $name => $agregate) {
            Assert::isArray($agregate);
            Assert::keyExists($agregate, 'path');
            Assert::keyExists($agregate, 'handler');

            $priority = 0;
            $cacheLifetime = 0;
            if (array_key_exists('priority', $agregate) && is_numeric($agregate['priority'])) {
                $priority = (int) $agregate['priority'];
            }

            if (array_key_exists('cache_lifetime', $agregate) && is_numeric($agregate['cache_lifetime'])) {
                $cacheLifetime = (int) $agregate['cache_lifetime'];
            }

            $routeCollection->add($name, new Route(
                sprintf('%s%s', $prefix, $agregate['path']),
                ['cache_lifetime' => $cacheLifetime, 'handler' => $agregate['handler']],
                [],
                [],
                null,
                [],
                ['GET']
            ), $priority);
        }

        return $routeCollection;
    }

    public function handle(Request $request): Response
    {
        $handler = $request->attributes->get('handler');
        $key = sprintf('%s_%s', $request->getPathInfo(), serialize($request->query->all()));
        if (!class_exists($handler)) {
            throw new HandlerNotFoundException();
        }

        $handler = new $handler();
        if (!$handler instanceof AggregateRequestInterface) {
            throw new InvalidHandlerException();
        }

        $statusCode = 200;
        if ($data = $this->redis->get($key)) {
            $data = unserialize($data);

            return new Response($data['content'], $statusCode, array_merge([
                'Semart-Gateway-Version' => Gateway::VERSION,
                'Semart-Gateway-Service-Id' => 'Cache',
            ]));
        }

        $response = $handler->handle($request);
        $headers = array_map(function ($value) {
            return $value[0];
        }, $response->headers->all());

        $data = [
            'content' => $response->getContent(),
            'headers' => $headers,
        ];

        if (Response::HTTP_OK === $response->getStatusCode()) {
            $this->redis->set($key, serialize($data));
            $this->redis->expire($key, (int) $request->attributes->get('cache_lifetime', 0));
            app()->pool($key);
        }

        return new Response($data['content'], $response->getStatusCode(), array_merge([
            'Semart-Gateway-Version' => Gateway::VERSION,
            'Semart-Gateway-Service-Id' => 'Aggregate',
        ], $data['headers']));
    }
}
