<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Request;

use KejawenLab\SemartApiGateway\Gateway;
use KejawenLab\SemartApiGateway\Route\RouteFactory;
use KejawenLab\SemartApiGateway\Service\NoServiceAvailableException;
use KejawenLab\SemartApiGateway\Service\Resolver;
use KejawenLab\SemartApiGateway\Service\ServiceFactory;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class RequestHandler
{
    private $authenticationHandler;

    private $serviceResolver;

    private $routeFactory;

    private $serviceFactory;

    private $redis;

    private $trustedIps;

    public function __construct(
        AuthenticationHandler $authenticationHandler,
        Resolver $serviceResolver,
        ServiceFactory $serviceFactory,
        RouteFactory $routeFactory,
        \Redis $redis,
        array $trustedIps
    ) {
        $this->authenticationHandler = $authenticationHandler;
        $this->serviceResolver = $serviceResolver;
        $this->serviceFactory = $serviceFactory;
        $this->routeFactory = $routeFactory;
        $this->redis = $redis;
        $this->trustedIps = $trustedIps;
    }

    public function handle(string $routeName, Request $request): Response
    {
        $options = OptionBuilder::build($request);
        $route = $this->routeFactory->get($routeName);
        $key = sha1(sprintf('%s_%s', $route->getPath(), serialize($options)));
        $statusCode = 200;

        if (!$route->isPublic()) {
            if ($auth = $request->headers->get('Authorization')) {
                $options['headers']['Authorization'] = $auth;
            } else {
                if (in_array($request->getClientIp(), $this->trustedIps)) {
                    $options['headers']['Authorization'] = sprintf('Bearer %s', $this->authenticationHandler->getAccessToken());
                }
            }
        }

        if ($data = $this->redis->get($key)) {
            $data = unserialize($data);

            return new Response($data['content'], $statusCode, [
                'Content-Type' => $data['content-type'],
                'Semart-Gateway-Version' => Gateway::VERSION,
                'Semart-Gateway-Service-Id' => 'cache',
            ]);
        }

        if (!$service = $this->serviceResolver->resolve($routeName)) {
            throw new NoServiceAvailableException();
        }

        try {
            $client = HttpClient::create();
            if ($route->getTimeout()) {
                set_time_limit($route->getTimeout());
            }

            $response = $client->request($request->getMethod(), $service->getUrl($route->getPath()), $options);
            $statusCode = $response->getStatusCode();
            $headers = array_map(function ($value) {
                return $value[0];
            }, $response->getHeaders());

            $data = serialize([
                'content' => $response->getContent(),
                'headers' => $headers,
            ]);

            if (app()['gateway.verify_path'] === $request->getPathInfo()) {
                $this->redis->set($key, $data);
                $this->redis->expire($key, app()['gateway.auth_cache_lifetime']);
                app()->pool($key);
            } elseif ($request->isMethodCacheable() && Response::HTTP_OK === $response->getStatusCode()) {
                $this->redis->set($key, $data);
                $this->redis->expire($key, $route->getCacheLifetime());
                app()->pool($key);
            }

            $data = unserialize($data);

            $symfonyResponse = new Response($data['content'], $statusCode, array_merge([
                'Semart-Gateway-Version' => Gateway::VERSION,
                'Semart-Gateway-Service-Id' => $service->getName(),
            ], $data['headers']));
        } catch (TransportExceptionInterface $e) {
            $this->serviceFactory->down($service);

            return $this->handle($routeName, $request);
        } catch (NoServiceAvailableException $e) {
            $symfonyResponse = new JsonResponse(['error' => $e->getCode(), 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR, [
                'Semart-Gateway-Version' => Gateway::VERSION,
                'Semart-Gateway-Service-Id' => $service->getName(),
            ]);
        } catch (ClientException $e) {
            $symfonyResponse = new JsonResponse(['error' => $e->getCode(), 'message' => sprintf('Cant call URL "%s"', $service->getUrl($route->getPath()))], Response::HTTP_INTERNAL_SERVER_ERROR, [
                'Semart-Gateway-Version' => Gateway::VERSION,
                'Semart-Gateway-Service-Id' => $service->getName(),
            ]);
        } finally {
            if (!isset($symfonyResponse)) {
                $symfonyResponse = new JsonResponse(['error' => 500, 'message' => 'Cant determine error'], Response::HTTP_INTERNAL_SERVER_ERROR, [
                    'Semart-Gateway-Version' => Gateway::VERSION,
                    'Semart-Gateway-Service-Id' => $service->getName(),
                ]);
            }

            app()->stat($service, [
                'path' => $request->getPathInfo(),
                'ip' => $request->getClientIp(),
                'method' => $request->getMethod(),
                'code' => $symfonyResponse->getStatusCode(),
            ]);
        }

        return $symfonyResponse;
    }
}
