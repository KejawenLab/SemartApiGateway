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

## Note

This Api Gateway can only handle JSON data. If you want to more data types support, please use [Kong](https://github.com/Kong/kong)

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

Please Check [Main Configuration Example](gateway.yaml.example) and [Routes Configuration Example](routes.yaml.example)

## TODO

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
- [ ] Implement Rate Limiter
    - [X] Limit Resource
    - [ ] Limit Request
- [ ] Implement Health Check
- [ ] Create Dashboard

## License

MIT License
