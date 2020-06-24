<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Server;

use Swoole\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class RequestFactory
{
    public static function toSymfonyRequest(Request $request)
    {
        $server = \array_change_key_case($request->server, CASE_UPPER);
        foreach ($request->header as $key => $value) {
            $server['HTTP_'.\mb_strtoupper(\str_replace('-', '_', $key))] = $value;
        }

        return new SymfonyRequest(
            $request->get ?? [],
            $request->post ?? [],
            [],
            $request->cookie ?? [],
            $request->files ?? [],
            $server,
            $request->rawContent()
        );
    }
}
