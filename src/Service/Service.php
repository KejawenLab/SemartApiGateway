<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Service;

use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
class Service
{
    private $name;

    private $host;

    private $version;

    private $healthCheckPath;

    private $limit;

    private $weight;

    private $down;

    private $hit;

    public function __construct(
        string $name,
        string $host,
        ?string $healthCheckPath = null,
        ?string $version = null,
        int $limit = -1,
        int $weight = 1,
        bool $down = false,
        int $hit = 0
    ) {
        $this->name = $name;
        $this->host = $host;
        $this->healthCheckPath = $healthCheckPath;
        $this->version = $version;
        $this->limit = $limit;
        $this->weight = $weight;
        $this->down = $down;
        $this->hit = $hit;
    }

    public static function createFromArray(array $service): self
    {
        Assert::keyExists($service, 'name');
        Assert::keyExists($service, 'host');
        Assert::keyExists($service, 'health_check_path');

        $version = null;
        $limit = -1;
        $weight = 1;
        $down = false;
        $hit = 0;

        if (array_key_exists('version', $service)) {
            $version = $service['version'];
        }

        if (array_key_exists('limit', $service)) {
            $limit = $service['limit'];
        }

        if (array_key_exists('weight', $service)) {
            $weight = $service['weight'];
        }

        if (array_key_exists('down', $service)) {
            $down = $service['down'];
        }

        if (array_key_exists('hit', $service)) {
            $hit = $service['hit'];
        }

        return new self($service['name'], $service['host'], $service['health_check_path'], $version, $limit, $weight, $down, $hit);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'host' => $this->getHost(),
            'health_check_path' => $this->getHealthCheckPath(),
            'version' => $this->getVersion(),
            'limit' => $this->getLimit(),
            'weight' => $this->getWeight(),
            'enabled' => $this->isEnabled(),
            'down' => $this->isDown(),
            'hit' => $this->getHit(),
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getHealthCheckPath(): ?string
    {
        return $this->healthCheckPath;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function getHit(): int
    {
        return $this->hit;
    }

    public function isEnabled(): bool
    {
        if ($limit = $this->isLimit()) {
            $this->resetHit();
        }

        if (!$limit) {
            return !$this->isDown();
        }

        return $limit;
    }

    public function isDown(): bool
    {
        return $this->down;
    }

    public function isUp(): bool
    {
        return !$this->down;
    }

    public function down(): void
    {
        $this->down = true;
    }

    public function up(): void
    {
        $this->down = false;
    }

    public function hit(): void
    {
        $this->hit++;
    }

    public function isLimit(): bool
    {
        return -1 !== $this->limit && $this->limit <= $this->hit;
    }

    public function resetHit(): void
    {
        $this->hit = 0;
    }

    public function getUrl(string $path): string
    {
        return sprintf('%s%s%s', $this->getHost(), !$this->getVersion()? $this->getVersion(): sprintf('/%s', $this->getVersion()), $path);
    }
}
