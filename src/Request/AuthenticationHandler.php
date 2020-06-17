<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Request;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class AuthenticationHandler
{
    public const HEADER = 'Gateway-User';

    private const ACCESS_TOKEN_KEY = '01c706e1fce86fa6c77221a4e5e61b91f2f2985f';

    private const USER_CREDENTIAL_KEY = '8010fb9ecbef0ac1339177629e95b77b88cb0676';

    private $redis;

    private $host;

    private $login;

    private $verify;

    private $header;

    private $token;

    private $credential;

    private $accessToken;

    private $userCredential;

    public function __construct(\Redis $redis, string $host, array $login, array $verify, array $token, array $credential, string $header = self::HEADER)
    {
        $this->redis = $redis;
        $this->host = $host;
        $this->header = $header;

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

    public function getHeader(): string
    {
        return $this->header;
    }

    public function getUserCredential(?string $token = null)
    {
        if (!$credential = $this->redis->get(static::USER_CREDENTIAL_KEY)) {
            $this->auth($token);
        }

        $this->userCredential = unserialize($credential);

        return $this->userCredential;
    }

    private function auth(?string $token = null): void
    {
        if ($token && $this->userCredential) {
            return;
        }

        $client = HttpClient::create();
        if (!$token) {
            $response = $client->request($this->login['method'], sprintf('%s%s', $this->host, $this->login['path']), [
                $this->credential['type'] => [
                    $this->credential['username']['field'] => $this->credential['username']['value'],
                    $this->credential['password']['field'] => $this->credential['password']['value'],
                ],
            ]);

            if (Response::HTTP_OK === $response->getStatusCode()) {
                $content = json_decode($response->getContent(), true);

                Assert::keyExists($content, $this->token['key']);
                $token = $content[$this->token['key']];

                $this->redis->set(static::ACCESS_TOKEN_KEY, $token);
                $this->redis->expire(static::ACCESS_TOKEN_KEY, $this->token['lifetime']);
                app()->pool(static::ACCESS_TOKEN_KEY);
            }

            $this->auth($token);
        }

        $response = $client->request($this->verify['method'], sprintf('%s%s', $this->host, $this->verify['path']), [
            'auth_bearer' => $this->accessToken,
        ]);

        if (Response::HTTP_OK === $response->getStatusCode()) {
            $credential = serialize(json_decode($response->getContent(), true));

            $this->redis->set(static::USER_CREDENTIAL_KEY, $credential);
            $this->redis->expire(static::USER_CREDENTIAL_KEY, $this->token['lifetime']);

            app()->pool(static::USER_CREDENTIAL_KEY);
        }
    }
}
