<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Request;

use Symfony\Component\HttpClient\HttpClient;
use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class AuthenticationHandler
{
    public const HEADER = 'Gateway-User';

    private const ACCESS_TOKEN_KEY = '01c706e1fce86fa6c77221a4e5e61b91f2f2985f';

    private $redis;

    private $host;

    private $login;

    private $verify;

    private $token;

    private $credential;

    public function __construct(\Redis $redis, string $host, array $login, array $verify, array $token, array $credential)
    {
        $this->redis = $redis;
        $this->host = $host;

        Assert::keyExists($login, 'path');
        Assert::keyExists($login, 'method');
        $this->login = $login;

        Assert::keyExists($verify, 'path');
        Assert::keyExists($verify, 'method');
        $this->verify = $verify;

        Assert::keyExists($token, 'key');
        Assert::keyExists($token, 'lifetime');
        $this->token = $token;

        Assert::keyExists($credential, 'type');
        Assert::keyExists($credential, 'username');
        Assert::keyExists($credential, 'password');
        Assert::keyExists($credential['username'], 'field');
        Assert::keyExists($credential['username'], 'value');
        Assert::keyExists($credential['password'], 'field');
        Assert::keyExists($credential['password'], 'value');
        $this->credential = $credential;
    }

    public function getAccessToken(): string
    {
        if (!$token = $this->redis->get(static::ACCESS_TOKEN_KEY)) {
            return $this->auth();
        }

        return $token;
    }

    private function auth(): string
    {
        $client = HttpClient::create();
        $response = $client->request($this->login['method'], sprintf('%s%s', $this->host, $this->login['path']), [
            $this->credential['type'] => [
                $this->credential['username']['field'] => $this->credential['username']['value'],
                $this->credential['password']['field'] => $this->credential['password']['value'],
            ],
        ]);

        $content = json_decode($response->getContent(), true);

        Assert::keyExists($content, $this->token['key']);
        $token = $content[$this->token['key']];

        $this->redis->set(static::ACCESS_TOKEN_KEY, $token);
        $this->redis->expire(static::ACCESS_TOKEN_KEY, $this->token['lifetime']);
        app()->pool(static::ACCESS_TOKEN_KEY);

        return $token;
    }
}
