<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Service;

use Elastica\Client;
use Elastica\Document;
use Elastica\Query\BoolQuery;
use Elastica\Query\Match;
use Elastica\Search;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class Statistic
{
    public const ROUTE_NAME = 'gateway_statistic';

    public const ROUTE_PATH = 'gateway/statistic';

    public const INDEX_NAME = 'semart_gateway_statistic';

    private $factory;

    private $elasticsearch;

    public function __construct(ServiceFactory $factory, Client $elasticsearch)
    {
        $this->factory = $factory;
        $this->elasticsearch = $elasticsearch;
    }

    public function statistic(): array
    {
        $result = [];
        $services = $this->factory->getServices();
        foreach ($services as $service) {
            $success = $this->getSuccess($service);
            $fail = $this->getFail($service);
            $total = $success + $fail;

            $result[$service->getName()] = [
                'hit' => $total,
                'uptime' => 0 === $total? 100: (($success / $total) * 100),
            ];
        }

        return $result;
    }

    public function stat(Service $service, array $data): void
    {
        $statisticIndex = $this->elasticsearch->getIndex(static::INDEX_NAME);
        $statisticIndex->addDocument(new Document(Uuid::uuid4()->toString(), [
            'service' => $service->getName(),
            'path' => $data['path'],
            'ip' => $data['ip'],
            'method' => $data['method'],
            'hit' => date('Y-m-d H:i:s'),
            'code' => $data['code'],
        ]));
    }

    private function getSuccess(Service $service): int
    {
        $statisticIndex = $this->elasticsearch->getIndex(static::INDEX_NAME);

        $query = new BoolQuery();
        $query->addShould($this->boolMatchMustQuery([
            ['field' => 'service', 'value' => $service->getName()],
            ['field' => 'code', 'value' => Response::HTTP_OK],
        ]));
        $query->addShould($this->boolMatchMustQuery([
            ['field' => 'service', 'value' => $service->getName()],
            ['field' => 'code', 'value' => Response::HTTP_CREATED],
        ]));
        $query->addShould($this->boolMatchMustQuery([
            ['field' => 'service', 'value' => $service->getName()],
            ['field' => 'code', 'value' => Response::HTTP_NO_CONTENT],
        ]));

        $search = new Search($this->elasticsearch);
        $search->setQuery($query);
        $search->addIndex($statisticIndex);

        return $search->search()->count();
    }

    private function getFail(Service $service): int
    {
        $statisticIndex = $this->elasticsearch->getIndex(static::INDEX_NAME);

        $search = new Search($this->elasticsearch);
        $search->setQuery($this->boolMatchMustQuery([
            ['field' => 'service', 'value' => $service->getName()],
            ['field' => 'code', 'value' => Response::HTTP_INTERNAL_SERVER_ERROR],
        ]));
        $search->addIndex($statisticIndex);

        return $search->search()->count();
    }

    private function boolMatchMustQuery(array $params): BoolQuery
    {
        $query = new BoolQuery();
        foreach ($params as $param) {
            $query->addMust((new Match())->setField($param['field'], $param['value']));
        }

        return $query;
    }
}
