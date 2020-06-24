<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Server;

use Swoole\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class ResponseFactory
{
    public static function process(Response $swoole, SymfonyResponse $symfony): void
    {
        foreach ($symfony->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            $swoole->header($name, \implode(', ', $values));
        }

        foreach ($symfony->headers->getCookies() as $cookie) {
            $swoole->cookie(
                $cookie->getName(),
                $cookie->getValue() ?? '',
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain() ?? '',
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
                $cookie->getSameSite() ?? ''
            );
        }

        $swoole->status($symfony->getStatusCode());
        $swoole->header('Content-Type', $symfony->headers->get('Content-Type'));
        if ($symfony instanceof BinaryFileResponse) {
            $swoole->sendfile($symfony->getFile()->getRealPath());
        } else {
            $swoole->end($symfony->getContent());
        }
    }
}
