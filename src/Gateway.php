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
use Pimple\Exception\UnknownIdentifierException;
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

    public const VERSION = '0.8';

    private const SEMART_GATEWAY_HOME = 'SEMART_GATEWAY_HOME';

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
        if (($cached = $this->getCache()->get(static::CACHE_KEY)) && is_array($cached)) {
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
        if (($cached = $this->getCache()->get(static::CACHE_KEY)) && is_array($cached)) {
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

        /** @var RouteFactory $routeFactory */
        $routeFactory = $this->get(RouteFactory::class);
        $prefix = $this->get('gateway.prefix');
        $routeCollection = new RouteCollection();
        foreach ($routeFactory->routes() as $route) {
            $routeCollection->add(
                $route->getName(),
                new SymfonyRoute(
                    sprintf('%s%s', $prefix, $route->getPath()),
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
        $routeCollection->add(static::SEMART_GATEWAY_HOME, new SymfonyRoute('/', [], [], [], null, [], ['GET']));

        $aggregateFactory = new ApiAggregationFactory($this->getCache());
        $matcher = new UrlMatcher($aggregateFactory->registerRoutes($routeCollection, $this->get('gateway.aggregates'), $prefix), new RequestContext());
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

        $this->buildContainers($config);
        $this->buildAuthenticationHandler($config);
        $this->buildServices($config);
        $this->buildRoutes($config);
        $this->buildCommands();

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

        $this->set(RandomHandler::class, function ($c) {
            return new RandomHandler();
        });

        $this->set(RoundRobinHandler::class, function ($c) {
            return new RoundRobinHandler();
        });

        $this->set(StickyHandler::class, function ($c) {
            return new StickyHandler();
        });

        $this->set(WeightHandler::class, function ($c) {
            return new WeightHandler();
        });

        $this->set(HandlerFactory::class, function ($c) {
            return new HandlerFactory([
                $c->get(RoundRobinHandler::class),
                $c->get(RandomHandler::class),
                $c->get(StickyHandler::class),
                $c->get(WeightHandler::class),
            ]);
        });

        $this->set(Resolver::class, function ($c) {
            return new Resolver($c->get(RouteFactory::class), $c->get(HandlerFactory::class), $c->get(ServiceFactory::class));
        });

        $this->set(Statistic::class, function ($c) {
            return new Statistic($c->get(ServiceFactory::class), $c->get(Client::class));
        });

        $this->set(RequestHandler::class, function ($c) {
            return new RequestHandler(
                $c->get(AuthenticationHandler::class),
                $c->get(Resolver::class),
                $c->get(ServiceFactory::class),
                $c->get(RouteFactory::class),
                $c->get(\Redis::class),
                $c->get('gateway.trusted_ips')
            );
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

    private function buildServices(array $config): void
    {
        $this->set(ServiceFactory::class, function ($c) use ($config) {
            $factory = new ServiceFactory($c->get(\Redis::class), $c->get(Client::class));
            if ($c->get('gateway.cacheable')) {
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

                $factory->addService(new Service($name, sprintf('%s%s', $host, $c->get('gateway.prefix')), $healthCheck, $version, $limit, $weight));
            }

            return $factory;
        });

        $this->set(ServiceStatus::class, function ($c) {
            return new ServiceStatus($c->get(ServiceFactory::class));
        });
    }

    private function buildRoutes(array $config): void
    {
        $this->set(RouteFactory::class, function ($c) use ($config) {
            $factory = new RouteFactory($c->get(\Redis::class));
            if ($c->get('gateway.cacheable')) {
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
        });
    }

    private function buildAuthenticationHandler(array $config): void
    {
        $this->set(AuthenticationHandler::class, function ($c) use ($config) {
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

            $c->set('gateway.verify_path', function ($c) use ($config) {
                return sprintf('%s%s', $c->get('gateway.prefix'), $config['gateway']['auth']['verify_path']);
            });

            $c->set('gateway.auth_cache_lifetime', function () use ($config) {
                return $config['gateway']['auth']['token']['lifetime'];
            });

            return new AuthenticationHandler(
                $c->getCache(),
                sprintf('%s%s', $host, $c->get('gateway.prefix')),
                $config['gateway']['auth']['login'],
                $config['gateway']['auth']['token'],
                $config['gateway']['auth']['credential']
            );
        });
    }

    private function buildCommands(): void
    {
        $this->set('gateway.commands', function ($c) {
            yield new HealthCheckCommand($c->get(ServiceFactory::class), $c->get(RouteFactory::class));
            yield new ClearCacheCommand();
            yield new CreateIndexCommand($c->get(Client::class), $c->get(ServiceFactory::class));
        });
    }

    private function buildContainers(array $config): void
    {
        foreach ($config['gateway']['containers'] as $name => $container) {
            $this->set($name, function () use ($name, $container) {
                $arguments = [];
                foreach ($container as $key => $argument) {
                    try {
                        $arguments[$key] = $this->get($argument);
                    } catch (UnknownIdentifierException $e) {
                        $arguments[$key] = $argument;
                    }
                }

                return new $name(...$arguments);
            });
        }
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
