<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Request;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class OptionBuilder
{
    public static function build(Request $request): array
    {
        $options = [];
        if (Request::METHOD_GET === $request->getMethod() && $query = $request->query->all()) {
            $options['query'] = $query;
        }

        if (in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_PUT])) {
            if ($body = $request->request->all()) {
                $options['body'] = $body;
            }

            if ($content = $request->getContent()) {
                if (isset($options['body'])) {
                    unset($options['body']);
                }

                $options['json'] = json_decode($request->getContent(), true);
            }
        }

        return $options;
    }
}
