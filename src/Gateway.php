<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway;

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
use Pimple\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
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
    public const VERSION = '1.0@dev';

    private const CACHE_KEY = '56235eacd73c9387b42f56959e01b6174ac35d94';

    private const CONFIG_KEY = '2e048eac73bd0908d9c2afb73aa7cc688960f8e6';

    public function __construct(\Redis $redis, string $environtment = 'dev')
    {
        parent::__construct();

        $this['gateway.cache'] = function () use ($redis) {
            return $redis;
        };

        $this['gateway.cacheable'] = function () use ($environtment) {
            return 'prod' === strtolower($environtment);
        };
    }

    public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true): Response
    {
        $this->build();

        $requestLimiter = new RequestLimiter($this['gateway.cache']);
        if ($this['gateway.cacheable'] && !$requestLimiter->allow($request, $this['gateway.exclude_paths'])) {
            return new Response(null, Response::HTTP_TOO_MANY_REQUESTS);
        }

        $routeCollection = new RouteCollection();
        /** @var RouteFactory $routeFactory */
        $routeFactory = $this['gateway.route_factory'];
        foreach ($routeFactory->routes() as $route) {
            $routeCollection->add($route->getName(), new SymfonyRoute(sprintf('%s%s', $this['gateway.prefix'], $route->getPath()), [], $route->getRequirements(), [], null, [], $route->getMethods()), $route->getPriority());
        }

        $matcher = new UrlMatcher($routeCollection, new RequestContext());
        $match = $matcher->matchRequest($request);

        /** @var RequestHandler $requestHandler */
        $requestHandler = $this['gateway.request_handler'];

        return $requestHandler->handle($match['_route'], $request);
    }

    private function build(): void
    {
        if (!$this['gateway.cacheable']) {
            $this->clean();

            $gateway = Yaml::parse(file_get_contents(sprintf('%s/gateway.yaml', GATEWAY_ROOT)));
            $routes = Yaml::parse(file_get_contents(sprintf('%s/routes.yaml', GATEWAY_ROOT)));

            Assert::keyExists($gateway, 'gateway');
            Assert::keyExists($routes, 'gateway');
            Assert::keyExists($routes['gateway'], 'routes');

            $gateway['gateway']['routes'] = $routes['gateway']['routes'];

            $config = serialize($gateway);

            $this['gateway.cache']->set(static::CONFIG_KEY, $config);
        } else {
            $config = $this['gateway.cache']->get(static::CONFIG_KEY);
        }

        $config = unserialize($config);

        $this['gateway.prefix'] = '';
        if (array_key_exists('prefix', $config['gateway'])) {
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

        $this['gateway.trusted_ips'] = function ($c) use ($config) {
            return $config['gateway']['trusted_ips'];
        };

        $this['gateway.exclude_paths'] = function ($c) use ($config) {
            $excludes = $config['gateway']['exclude_paths'];
            foreach ($excludes as $key => $value) {
                $excludes[$key] = sprintf('%s%s', $c['gateway.prefix'], $value);
            }

            return $excludes;
        };

        $this['gateway.handler.random'] = function ($c) {
            return new RandomHandler();
        };

        $this['gateway.handler.roundrobin'] = function ($c) {
            return new RoundRobinHandler();
        };

        $this['gateway.handler.sticky'] = function ($c) {
            return new StickyHandler();
        };

        $this['gateway.handler.weight'] = function ($c) {
            return new WeightHandler();
        };

        $this['gateway.handler_factory'] = function ($c) {
            return new HandlerFactory([
                $c['gateway.handler.roundrobin'],
                $c['gateway.handler.random'],
                $this['gateway.handler.sticky'],
                $this['gateway.handler.weight'],
            ]);
        };

        $this['gateway.service_resolver'] = function ($c) {
            return new Resolver($c['gateway.route_factory'], $c['gateway.handler_factory']);
        };

        $this['gateway.request_handler'] = function ($c) {
            return new RequestHandler(
                $c['gateway.authentication_handler'],
                $c['gateway.service_resolver'],
                $c['gateway.service_factory'],
                $c['gateway.route_factory'],
                $c['gateway.cache'],
                $c['gateway.trusted_ips']
            );
        };
    }

    private function buildServices(array $config): void
    {
        $this['gateway.service_factory'] = function ($c) use ($config) {
            $factory = new ServiceFactory($c['gateway.cache']);
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
    }

    private function buildRoutes(array $config): void
    {
        $this['gateway.route_factory'] = function ($c) use ($config) {
            $factory = new RouteFactory($c['gateway.cache']);
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
                $serviceFactory = $this['gateway.service_factory'];
                foreach ($route['handlers'] as $handler) {
                    $handlers[] = $serviceFactory->get($handler);
                }

                $cacheLifetime = 0;
                if (array_key_exists('cache_lifetime', $route)) {
                    $cacheLifetime = (int) $route['cache_lifetime'];
                }

                $factory->addRoute(new Route($name, $route['path'], $handlers, $methods, $balance, $priority, $public, $requirements, $cacheLifetime));
            }

            return $factory;
        };
    }

    private function buildAuthenticationHandler(array $config): void
    {
        $this['gateway.authentication_handler'] = function ($c) use ($config) {
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
                $c['gateway.cache'],
                sprintf('%s%s', $host, $c['gateway.prefix']),
                $config['gateway']['auth']['login'],
                $config['gateway']['auth']['token'],
                $config['gateway']['auth']['credential']
            );
        };
    }

    public function pool(string $key): void
    {
        if (!$keys = $this['gateway.cache']->get(static::CACHE_KEY)) {
            $keys = serialize([]);
        }

        $keys = unserialize($keys);
        if (!in_array($key, $keys)) {
            $keys = array_merge($keys, [$key]);
            $this['gateway.cache']->set(static::CACHE_KEY, serialize($keys));
        }
    }

    public function clean(): void
    {
        if (!$keys = $this['gateway.cache']->get(static::CACHE_KEY)) {
            return;
        }

        $this['gateway.cache']->del(array_merge(unserialize($keys), [static::CACHE_KEY]));
    }
}
