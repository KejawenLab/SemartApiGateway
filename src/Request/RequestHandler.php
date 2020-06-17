<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Request;

use KejawenLab\SemartApiGateway\Route\RouteFactory;
use KejawenLab\SemartApiGateway\Service\NoServiceAvailableException;
use KejawenLab\SemartApiGateway\Service\Resolver;
use KejawenLab\SemartApiGateway\Service\ServiceFactory;
use Symfony\Component\HttpClient\HttpClient;
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

    public function __construct(AuthenticationHandler $authenticationHandler, Resolver $serviceResolver, ServiceFactory $serviceFactory, RouteFactory $routeFactory, \Redis $redis)
    {
        $this->authenticationHandler = $authenticationHandler;
        $this->serviceResolver = $serviceResolver;
        $this->serviceFactory = $serviceFactory;
        $this->routeFactory = $routeFactory;
        $this->redis = $redis;
    }

    public function handle(string $routeName, Request $request): Response
    {
        if (!$service = $this->serviceResolver->resolve($routeName)) {
            throw new NoServiceAvailableException();
        }

        $options = OptionBuilder::build($request);
        $route = $this->routeFactory->get($routeName);
        if (!$route->isPublic()) {
            if ($auth = $request->headers->get('Authorization')) {
                $options['headers']['Authorization'] = $auth;

                $token = explode(' ', $auth);
                if (2 === count($token)) {
                    $token = $token[1];
                } else {
                    $token = $token[0];
                }

                $options['headers'][$this->authenticationHandler->getHeader()] = json_encode($this->authenticationHandler->getUserCredential($token));
            }
        }

        try {
            $key = sha1(serialize($options));
            $statusCode = 200;
            if (!$data = $this->redis->get($key)) {
                $client = HttpClient::create();
                $response = $client->request($request->getMethod(), $service->getUrl($route->getPath()), $options);
                $headers = array_map(function ($value) {
                    return $value[0];
                }, $response->getHeaders());

                $statusCode = $response->getStatusCode();
                $data = serialize([
                    'content' => $response->getContent(),
                    'content-type' => $headers['content-type'],
                ]);

                if (Request::METHOD_GET && $request->getMethod() && Response::HTTP_OK === $response->getStatusCode()) {
                    $this->redis->set($key, $data);
                    $this->redis->expire($key, $route->getCacheLifetime());
                    app()->pool($key);
                }
            }

            $data = unserialize($data);

            return new Response($data['content'], $statusCode, ['content-type' => $data['content-type']]);
        } catch (TransportExceptionInterface $e) {
            $this->serviceFactory->down($service);
            dump($this->serviceFactory);

            return $this->handle($routeName, $request);
        } catch (NoServiceAvailableException $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
