# Semart Api Gateway

Semart Api Gateway is Fast, Simple Yet Powerful API Gateway base on Symfony Component that aim to simplify your day

## Requirements

>
> * PHP >= 7.2
>
> * Redis
>
> * PHP Redis Extension
>

## Workflow

![Workflow](flow.png)

## Super Fast

![Screenshot](response.png)

>
> Screenshot use [SemartApiSkeleton](https://github.com/KejawenLab/SemartApiSkeleton) demo on my Digital Ocean VPS with 1 GB of RAM
>

## Install

```bash
git clone https://github.com/KejawenLab/SemartApiGateway.git gateway
cd gateway
cp .env.example .env
cp gateway.yaml.example gateway.yaml
cp routes.yaml.example routes.yaml
composer update
```

## Configuration

Please Check [Main Configuration Example](gateway.yaml.example), [Service Configuration Example](services.yaml.example) and [Routes Configuration Example](routes.yaml.example)

## Statistic

You can see statistic by call path `/gateway/statistic` and the response be like

![Reponse](statistic.png)

## Services Status

You can see statistic by call path `/gateway/status` and the response be like

![Response](status.png)

## TODO

- [X] Trusted IPs
- [X] Implement Load Balancer
    - [X] Random Method 
    - [X] Round Robin Method
    - [X] Sticky (Master/Slave) Method
    - [X] Weight Method
- [X] Multiple Service Per Route
- [X] Authorization
    - [X] Authorization Header (`Bearer`) Forwarder
    - [X] Trusted Ip List for Internal Call
    - [X] Authetication For Internal Call
- [X] Api Versioning Per Service
- [X] Public and Private Api
- [X] Implement Rate Limiter
    - [X] Limit Resource
    - [X] Limit Request
    - [ ] Request Timeout
- [X] Implement Health Check
- [X] Statistic Api

## License

MIT License
