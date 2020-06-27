<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway;

use Elastica\Client;
use KejawenLab\SemartApiGateway\Aggregate\ApiAggregationFactory;
use KejawenLab\SemartApiGateway\Config\ConfigBuilderFactory;
use KejawenLab\SemartApiGateway\Request\RequestHandler;
use KejawenLab\SemartApiGateway\Request\RequestLimiter;
use KejawenLab\SemartApiGateway\Route\SymfonyRouteBuilder;
use KejawenLab\SemartApiGateway\Service\Service;
use KejawenLab\SemartApiGateway\Service\ServiceStatus;
use KejawenLab\SemartApiGateway\Service\Statistic;
use Pimple\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Yaml\Yaml;
use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class Gateway extends Container implements HttpKernelInterface
{
    public const NAME = 'Semart Api Gateway';

    public const VERSION = '0.8';

    public const SEMART_GATEWAY_HOME = 'SEMART_GATEWAY_HOME';

    private const CACHE_KEY = '56235eacd73c9387b42f56959e01b6174ac35d94';

    private const CONFIG_KEY = '2e048eac73bd0908d9c2afb73aa7cc688960f8e6';

    private $redis;

    public function __construct(\Redis $redis, Client $client, string $environtment = 'dev')
    {
        parent::__construct();

        $this->set(\Redis::class, function () use ($redis) {
            return $redis;
        });

        $this->set(Client::class, function () use ($client) {
            return $client;
        });

        $this->set('gateway.cacheable', function () use ($environtment) {
            return 'prod' === strtolower($environtment);
        });
    }

    public function pool(string $key): void
    {
        $keys = [];
        if ($cached = $this->getCache()->get(static::CACHE_KEY)) {
            $keys = unserialize($cached);
        }

        if (!in_array($key, $keys)) {
            $keys = array_merge($keys, [$key]);
            $this->getCache()->set(static::CACHE_KEY, serialize($keys));
        }
    }

    public function clean(): void
    {
        $keys = [];
        if ($cached = $this->getCache()->get(static::CACHE_KEY)) {
            $keys = unserialize($cached);
        }

        $this->getCache()->del(array_merge($keys, [static::CACHE_KEY]));
    }

    public function stat(Service $service, array $data): void
    {
        $this->get(Statistic::class)->stat($service, $data);
    }

    public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true): Response
    {
        $this->build();

        $requestLimiter = new RequestLimiter($this->getCache());
        $allow = $requestLimiter->allow($request, $this->get('gateway.exclude_paths'));
        $trusted = in_array($request->getClientIp(), $this->get('gateway.trusted_ips'));
        if ($this->get('gateway.cacheable') && !$trusted && !$allow) {
            return new Response(null, Response::HTTP_TOO_MANY_REQUESTS);
        }

        $aggregateFactory = new ApiAggregationFactory($this->getCache());
        $matcher = new UrlMatcher(SymfonyRouteBuilder::build($this, $aggregateFactory), new RequestContext());
        try {
            $match = $matcher->matchRequest($request);
        } catch (NoConfigurationException $e) {
            throw new NotFoundHttpException();
        } catch (ResourceNotFoundException $e) {
            throw new NotFoundHttpException();
        }

        $routeName = $match['_route'];
        if ($routeName === static::SEMART_GATEWAY_HOME) {
            return new JsonResponse(['name' => static::NAME, 'version' => static::VERSION]);
        }

        if ($routeName === Statistic::ROUTE_NAME) {
            return new JsonResponse($this[Statistic::class]->statistic());
        }

        if ($routeName === ServiceStatus::ROUTE_NAME) {
            return new JsonResponse($this[ServiceStatus::class]->status());
        }

        foreach ($match as $key => $value) {
            $request->attributes->set($key, $value);
        }

        if ($request->attributes->get('handler')) {
            return $aggregateFactory->handle($request);
        }

        return $this->get(RequestHandler::class)->handle($routeName, $request);
    }

    public function build(): void
    {
        if (!$this->get('gateway.cacheable')) {
            $this->clean();

            $config = serialize($this->parseConfig());
            $this->getCache()->set(static::CONFIG_KEY, $config);
        } else {
            $config = $this->getCache()->get(static::CONFIG_KEY);
            if (!$config) {
                $config = serialize($this->parseConfig());
                $this->getCache()->set(static::CONFIG_KEY, $config);
            }
        }

        app()->pool(static::CONFIG_KEY);
        $config = unserialize($config);

        $this->set('gateway.prefix', function () use ($config) {
            $prefix = '';
            if (array_key_exists('prefix', $config['gateway']) && is_string($config['gateway']['prefix'])) {
                $prefix = $config['gateway']['prefix'];
            }

            return $prefix;
        });

        Assert::keyExists($config, 'gateway');
        Assert::keyExists($config['gateway'], 'auth');
        Assert::keyExists($config['gateway'], 'services');
        Assert::keyExists($config['gateway'], 'routes');
        Assert::keyExists($config['gateway'], 'trusted_ips');
        Assert::keyExists($config['gateway'], 'exclude_paths');
        Assert::isArray($config['gateway']['trusted_ips']);
        Assert::isArray($config['gateway']['exclude_paths']);

        $configBuilder = new ConfigBuilderFactory();
        $configBuilder->build($this, $config);

        $this->set('gateway.aggregates', function () use ($config) {
            return $config['gateway']['aggregates'];
        });

        $this->set('gateway.trusted_ips', function () use ($config) {
            return $config['gateway']['trusted_ips'];
        });

        $this->set('gateway.exclude_paths', function ($c) use ($config) {
            $excludes = $config['gateway']['exclude_paths'];
            foreach ($excludes as $key => $value) {
                $excludes[$key] = sprintf('%s%s', $c->get('gateway.prefix'), $value);
            }

            return $excludes;
        });
    }

    public function get(string $service)
    {
        return $this[$service];
    }

    public function set(string $name, callable $value): void
    {
        $this[$name] = $value;
    }

    private function getCache(): \Redis
    {
        if (null === $this->redis) {
            $this->redis = $this->get(\Redis::class);
        }

        return $this->redis;
    }

    private function parseConfig(): array
    {
        $gateway = Yaml::parse(file_get_contents(sprintf('%s/gateway.yaml', GATEWAY_ROOT)));
        $routes = Yaml::parse(file_get_contents(sprintf('%s/routes.yaml', GATEWAY_ROOT)));
        $services = Yaml::parse(file_get_contents(sprintf('%s/services.yaml', GATEWAY_ROOT)));
        $aggregates = Yaml::parse(file_get_contents(sprintf('%s/aggregates.yaml', GATEWAY_ROOT)));
        $containers = Yaml::parse(file_get_contents(sprintf('%s/di.yaml', GATEWAY_ROOT)));

        Assert::keyExists($gateway, 'gateway');
        Assert::keyExists($routes, 'gateway');
        Assert::keyExists($services['gateway'], 'services');

        $gateway['gateway']['aggregates'] = [];
        if (
            is_array($aggregates) &&
            array_key_exists('gateway', $aggregates) &&
            array_key_exists('aggregates', $aggregates['gateway']) &&
            is_array($aggregates['gateway']['aggregates'])
        ) {
            $gateway['gateway']['aggregates'] = $aggregates['gateway']['aggregates'];
        }

        $gateway['gateway']['containers'] = [];
        if (is_array($containers) && array_key_exists('containers', $containers) && is_array($containers['containers'])) {
            $gateway['gateway']['containers'] = $containers['containers'];
        }

        $gateway['gateway']['routes'] = $routes['gateway']['routes'];
        $gateway['gateway']['services'] = $services['gateway']['services'];

        return $gateway;
    }
}
