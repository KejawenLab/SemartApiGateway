<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway;

use Elastica\Client;
use KejawenLab\SemartApiGateway\Aggregate\ApiAggregationFactory;
use KejawenLab\SemartApiGateway\Command\ClearCacheCommand;
use KejawenLab\SemartApiGateway\Command\CreateIndexCommand;
use KejawenLab\SemartApiGateway\Command\HealthCheckCommand;
use KejawenLab\SemartApiGateway\Handler\HandlerFactory;
use KejawenLab\SemartApiGateway\Handler\HandlerInterface;
use KejawenLab\SemartApiGateway\Handler\RandomHandler;
use KejawenLab\SemartApiGateway\Handler\RoundRobinHandler;
use KejawenLab\SemartApiGateway\Handler\StickyHandler;
use KejawenLab\SemartApiGateway\Handler\WeightHandler;
use KejawenLab\SemartApiGateway\Request\AuthenticationHandler;
use KejawenLab\SemartApiGateway\Request\RequestHandler;
use KejawenLab\SemartApiGateway\Request\RequestLimiter;
use KejawenLab\SemartApiGateway\Route\Route;
use KejawenLab\SemartApiGateway\Route\RouteFactory;
use KejawenLab\SemartApiGateway\Service\Resolver;
use KejawenLab\SemartApiGateway\Service\Service;
use KejawenLab\SemartApiGateway\Service\ServiceFactory;
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
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Yaml;
use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class Gateway extends Container implements HttpKernelInterface
{
    public const NAME = 'Semart Api Gateway';

    public const VERSION = '1.0@dev';

    private const CACHE_KEY = '56235eacd73c9387b42f56959e01b6174ac35d94';

    private const CONFIG_KEY = '2e048eac73bd0908d9c2afb73aa7cc688960f8e6';

    public function __construct(\Redis $redis, Client $client, string $environtment = 'dev')
    {
        parent::__construct();

        $this[\Redis::class] = function () use ($redis) {
            return $redis;
        };

        $this[Client::class] = function () use ($client) {
            return $client;
        };

        $this['gateway.cacheable'] = function () use ($environtment) {
            return 'prod' === strtolower($environtment);
        };
    }

    public function pool(string $key): void
    {
        if (!$keys = $this[\Redis::class]->get(static::CACHE_KEY)) {
            $keys = serialize([]);
        }

        $keys = unserialize($keys);
        if (!in_array($key, $keys)) {
            $keys = array_merge($keys, [$key]);
            $this[\Redis::class]->set(static::CACHE_KEY, serialize($keys));
        }
    }

    public function clean(): void
    {
        if (!$keys = $this[\Redis::class]->get(static::CACHE_KEY)) {
            return;
        }

        $this[\Redis::class]->del(array_merge(unserialize($keys), [static::CACHE_KEY]));
    }

    public function stat(Service $service, array $data): void
    {
        $this[Statistic::class]->stat($service, $data);
    }

    public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true): Response
    {
        $this->build();

        $requestLimiter = new RequestLimiter($this[\Redis::class]);
        $allow = $requestLimiter->allow($request, $this['gateway.exclude_paths']);
        $trusted = in_array($request->getClientIp(), $this['gateway.trusted_ips']);
        if ($this['gateway.cacheable'] && !$trusted && !$allow) {
            return new Response(null, Response::HTTP_TOO_MANY_REQUESTS);
        }

        $routeCollection = new RouteCollection();

        /** @var RouteFactory $routeFactory */
        $routeFactory = $this[RouteFactory::class];
        foreach ($routeFactory->routes() as $route) {
            $routeCollection->add(
                $route->getName(),
                new SymfonyRoute(
                    sprintf('%s%s', $this['gateway.prefix'], $route->getPath()),
                    [],
                    $route->getRequirements(),
                    [],
                    null,
                    [],
                    $route->getMethods()
                ),
                $route->getPriority()
            );
        }

        $routeCollection->add(Statistic::ROUTE_NAME, new SymfonyRoute(Statistic::ROUTE_PATH, [], [], [], null, [], ['GET']));
        $routeCollection->add(ServiceStatus::ROUTE_NAME, new SymfonyRoute(ServiceStatus::ROUTE_PATH, [], [], [], null, [], ['GET']));

        $aggregateFactory = new ApiAggregationFactory($this[\Redis::class]);

        $matcher = new UrlMatcher($aggregateFactory->registerRoutes($routeCollection, $this['gateway.aggregates'], $this['gateway.prefix']), new RequestContext());
        try {
            $match = $matcher->matchRequest($request);
        } catch (NoConfigurationException $e) {
            throw new NotFoundHttpException();
        } catch (ResourceNotFoundException $e) {
            throw new NotFoundHttpException();
        }

        foreach ($match as $key => $value) {
            $request->attributes->set($key, $value);
        }

        if ($request->attributes->get('handler')) {
            return $aggregateFactory->handle($request);
        }

        $routeName = $request->attributes->get('_route');
        if ($routeName === Statistic::ROUTE_NAME) {
            return new JsonResponse($this[Statistic::class]->statistic());
        }

        if ($routeName === ServiceStatus::ROUTE_NAME) {
            return new JsonResponse($this[ServiceStatus::class]->status());
        }

        /** @var RequestHandler $requestHandler */
        $requestHandler = $this[RequestHandler::class];

        return $requestHandler->handle($routeName, $request);
    }

    public function build(): void
    {
        if (!$this['gateway.cacheable']) {
            $this->clean();

            $config = serialize($this->parseConfig());
            $this[\Redis::class]->set(static::CONFIG_KEY, $config);
        } else {
            $config = $this[\Redis::class]->get(static::CONFIG_KEY);
            if (!$config) {
                $config = serialize($this->parseConfig());
                $this[\Redis::class]->set(static::CONFIG_KEY, $config);
            }
        }

        app()->pool(static::CONFIG_KEY);
        $config = unserialize($config);

        $this['gateway.prefix'] = '';
        if (array_key_exists('prefix', $config['gateway']) && $config['gateway']['prefix']) {
            $this['gateway.prefix'] = $config['gateway']['prefix'];
        }

        Assert::keyExists($config, 'gateway');
        Assert::keyExists($config['gateway'], 'auth');
        Assert::keyExists($config['gateway'], 'services');
        Assert::keyExists($config['gateway'], 'routes');
        Assert::keyExists($config['gateway'], 'trusted_ips');
        Assert::keyExists($config['gateway'], 'exclude_paths');
        Assert::isArray($config['gateway']['exclude_paths']);

        $this->buildAuthenticationHandler($config);
        $this->buildServices($config);
        $this->buildRoutes($config);
        $this->buildCommands();

        $this['gateway.aggregates'] = function () use ($config) {
            return $config['gateway']['aggregates'];
        };

        $this['gateway.trusted_ips'] = function () use ($config) {
            return $config['gateway']['trusted_ips'];
        };

        $this['gateway.exclude_paths'] = function ($c) use ($config) {
            $excludes = $config['gateway']['exclude_paths'];
            foreach ($excludes as $key => $value) {
                $excludes[$key] = sprintf('%s%s', $c['gateway.prefix'], $value);
            }

            return $excludes;
        };

        $this[RandomHandler::class] = function ($c) {
            return new RandomHandler();
        };

        $this[RoundRobinHandler::class] = function ($c) {
            return new RoundRobinHandler();
        };

        $this[StickyHandler::class] = function ($c) {
            return new StickyHandler();
        };

        $this[WeightHandler::class] = function ($c) {
            return new WeightHandler();
        };

        $this[HandlerFactory::class] = function ($c) {
            return new HandlerFactory([
                $c[RoundRobinHandler::class],
                $c[RandomHandler::class],
                $this[StickyHandler::class],
                $this[WeightHandler::class],
            ]);
        };

        $this[Resolver::class] = function ($c) {
            return new Resolver($c[RouteFactory::class], $c[HandlerFactory::class], $c[ServiceFactory::class]);
        };

        $this[Statistic::class] = function ($c) {
            return new Statistic($c[ServiceFactory::class], $c[Client::class]);
        };

        $this[RequestHandler::class] = function ($c) {
            return new RequestHandler(
                $c[AuthenticationHandler::class],
                $c[Resolver::class],
                $c[ServiceFactory::class],
                $c[RouteFactory::class],
                $c[\Redis::class],
                $c['gateway.trusted_ips']
            );
        };
    }

    public function get(string $service)
    {
        return $this[$service];
    }

    public function set(string $name, callable $value): void
    {
        $this[$name] = $value;
    }

    private function buildServices(array $config): void
    {
        $this[ServiceFactory::class] = function ($c) use ($config) {
            $factory = new ServiceFactory($c[\Redis::class], $c[Client::class]);
            if ($this['gateway.cacheable']) {
                $factory->populate();

                return $factory;
            }

            $services = $config['gateway']['services'];
            foreach ($services as $name => $service) {
                Assert::isArray($service);

                if (array_key_exists('host', $service)) {
                    $host = $service['host'];
                } else {
                    Assert::keyExists($config['gateway'], 'host');

                    $host = $config['gateway']['host'];
                }

                $healthCheck = null;
                if (array_key_exists('health_check_path', $service)) {
                    $healthCheck = $service['health_check_path'];
                }

                $version = null;
                if (array_key_exists('version', $service)) {
                    $version = $service['version'];
                }

                $limit = -1;
                if (array_key_exists('limit', $service)) {
                    $limit = $service['limit'];
                }

                $weight = 1;
                if (array_key_exists('weight', $service)) {
                    $weight = $service['weight'];
                }

                $factory->addService(new Service($name, sprintf('%s%s', $host, $c['gateway.prefix']), $healthCheck, $version, $limit, $weight));
            }

            return $factory;
        };

        $this[ServiceStatus::class] = function ($c) {
            return new ServiceStatus($c[ServiceFactory::class]);
        };
    }

    private function buildRoutes(array $config): void
    {
        $this[RouteFactory::class] = function ($c) use ($config) {
            $factory = new RouteFactory($c[\Redis::class]);
            if ($this['gateway.cacheable']) {
                $factory->populate();

                return $factory;
            }

            $routes = $config['gateway']['routes'];
            foreach ($routes as $name => $route) {
                Assert::keyExists($route, 'path');
                Assert::keyExists($route, 'handlers');
                Assert::isArray($route['handlers']);

                $methods = ['GET'];
                if (array_key_exists('methods', $route) && is_array($route['methods'])) {
                    $methods = $route['methods'];
                }

                $balance = HandlerInterface::BALANCE_ROUNDROBIN;
                if (array_key_exists('balance', $route)) {
                    $balance = $route['balance'];
                }

                $priority = 0;
                if (array_key_exists('priority', $route)) {
                    $priority = (int) $route['priority'];
                }

                $public = false;
                if (array_key_exists('public', $route)) {
                    $public = (bool) $route['public'];
                }

                $requirements = [];
                if (array_key_exists('requirements', $route) && is_array($route['requirements'])) {
                    $requirements = $route['requirements'];
                }

                $handlers = [];
                /** @var ServiceFactory $serviceFactory */
                $serviceFactory = $this[ServiceFactory::class];
                foreach ($route['handlers'] as $handler) {
                    $handlers[] = $serviceFactory->get($handler);
                }

                $cacheLifetime = 0;
                if (array_key_exists('cache_lifetime', $route)) {
                    $cacheLifetime = (int) $route['cache_lifetime'];
                }

                $timeout = 0;
                if (array_key_exists('timeout', $route)) {
                    $timeout = (int) $route['timeout'];
                }

                $factory->addRoute(new Route($name, $route['path'], $handlers, $methods, $balance, $priority, $public, $requirements, 0, $cacheLifetime, $timeout));
            }

            return $factory;
        };
    }

    private function buildAuthenticationHandler(array $config): void
    {
        $this[AuthenticationHandler::class] = function ($c) use ($config) {
            if (array_key_exists('host', $config['gateway']['auth'])) {
                $host = $config['gateway']['auth']['host'];
            } else {
                Assert::keyExists($config['gateway'], 'host');

                $host = $config['gateway']['host'];
            }

            Assert::keyExists($config['gateway']['auth'], 'login');
            Assert::keyExists($config['gateway']['auth'], 'verify_path');
            Assert::keyExists($config['gateway']['auth'], 'token');
            Assert::keyExists($config['gateway']['auth'], 'credential');

            $c['gateway.verify_path'] = sprintf('%s%s', $c['gateway.prefix'], $config['gateway']['auth']['verify_path']);
            $c['gateway.auth_cache_lifetime'] = $config['gateway']['auth']['token']['lifetime'];

            return new AuthenticationHandler(
                $c[\Redis::class],
                sprintf('%s%s', $host, $c['gateway.prefix']),
                $config['gateway']['auth']['login'],
                $config['gateway']['auth']['token'],
                $config['gateway']['auth']['credential']
            );
        };
    }

    private function buildCommands(): void
    {
        $this['gateway.commands'] = function ($c) {
            yield new HealthCheckCommand($c[ServiceFactory::class], $c[RouteFactory::class]);
            yield new ClearCacheCommand();
            yield new CreateIndexCommand($c[Client::class], $this[ServiceFactory::class]);
        };
    }

    private function parseConfig(): array
    {
        $gateway = Yaml::parse(file_get_contents(sprintf('%s/gateway.yaml', GATEWAY_ROOT)));
        $routes = Yaml::parse(file_get_contents(sprintf('%s/routes.yaml', GATEWAY_ROOT)));
        $services = Yaml::parse(file_get_contents(sprintf('%s/services.yaml', GATEWAY_ROOT)));
        $aggregates = Yaml::parse(file_get_contents(sprintf('%s/aggregates.yaml', GATEWAY_ROOT)));

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

        $gateway['gateway']['routes'] = $routes['gateway']['routes'];
        $gateway['gateway']['services'] = $services['gateway']['services'];

        return $gateway;
    }
}
