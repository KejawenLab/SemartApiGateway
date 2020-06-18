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

## Fast

![Screenshot](response.png)

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

- [ ] Implement Load Balancer
    - [X] Random Method 
    - [X] Round Robin Method
    - [X] Sticky (Master/Slave) Method
    - [ ] Weight Method
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
