<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Config;

use KejawenLab\SemartApiGateway\Gateway;
use KejawenLab\SemartApiGateway\Request\AuthenticationHandler;
use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class AuthenticationConfigBuilder implements ConfigBuilderInterface
{
    public function build(Gateway $container, array $config): void
    {
        $container->set(AuthenticationHandler::class, function () use ($container, $config) {
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

            $container->set('gateway.verify_path', function () use ($container, $config) {
                return sprintf('%s%s', $container->get('gateway.prefix'), $config['gateway']['auth']['verify_path']);
            });

            $container->set('gateway.auth_cache_lifetime', function () use ($config) {
                return $config['gateway']['auth']['token']['lifetime'];
            });

            return new AuthenticationHandler(
                $container->get(\Redis::class),
                sprintf('%s%s', $host, $container->get('gateway.prefix')),
                $config['gateway']['auth']['login'],
                $config['gateway']['auth']['token'],
                $config['gateway']['auth']['credential']
            );
        });
    }
}
