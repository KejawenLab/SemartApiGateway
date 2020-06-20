# Semart Api Gateway Configuration

## Main Configuration

```yaml
gateway:
    prefix: /api
    host: https://arsiteknologi.com:8080
    trusted_ips: ['127.0.0.1', '::1']
    exclude_paths:
        - /public/settings
    auth:
        host: https://arsiteknologi.com:8080
        login:
            path: /login
            method: POST
        credential:
            type: json
            username:
                field: username
                value: admin
            password:
                field: password
                value: admin
        verify_path: /me #mostly profile path
        token:
            key: token
            lifetime: 3600

```

>
> * `prefix` is the prefix of url for all services and routes. Set to null or empty string if want to avoid it.
>
> * `host` is global host. if you don't set the host in auth or services, it value will be applied.
>
> * `trusted_ips` are ip list that have special policy such as no request limit and auto login when call private URL without any credential.
>
> * `exclude_paths` are path(s) that no request limiter applied. It mean, we don't limit request to these resources.
>
> * `auth.host` is the host of authentication service. if not set, global host will be applied.
>
> * `auth.login` is the login path and method. the prefix added to login path.
>
> * `auth.credential` are credentnial to supply when login called.
>
> * `auth.verify_path` is path that will check the token is valid or not. Basically all private page can used here.
>
> * `auth.token` is config to tell how to get the token from response. ex: `{ token: 'thisIsSecretToken'}`, so the key is `token`.
> 

## Route

```yaml
gateway:
    routes:
        route1:
            path: /me
            methods: [GET]
            priority: 0
            public: false
            cache_lifetime: 5
            balance: roundrobin # roundrobin | random | sticky (master/slave) NB: Please noted, some balance method may not work during development
            timeout: 0
            handlers:
                - service1
                - service2
```

## Service

```yaml
gateway:
    services:
        service4:
            host: https://arsiteknologi.com:8080
            health_check_path: /status
            version: v1
            limit: 1000
            weight: 3
```
