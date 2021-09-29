<?php
namespace Oxzion\AppDelegate;

use Logger;
use Oxzion\Service\ElasticService;

trait ElasticTrait
{
    protected $logger;
    private $elasticService;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
    public function setElasticService(ElasticService $elasticService)
    {
        $this->logger->info("SET Elastic Service -> " . get_class($elasticService));
        $this->elasticService = $elasticService;
    }
    public function setElasticConfig(array $config)
    {
        $this->logger->info("SET config -> " . print_r($config, true));
        $this->elasticService->setConfig($config);
    }

    public function runQuery($accountId, $index, Array $query)
    {
        $this->logger->info("runQuery -> ".print_r($query, true));
        $result = $this->elasticService->getQueryResults($accountId, $index, $query);
        $this->query = $this->elasticService->getElasticQuery();
        $finalResult['meta'] = $query;
        $finalResult['meta']['type'] = $result['type'];
        $finalResult['meta']['query'] = $result['query'];
        if (isset($result['total_count'])) {
            $finalResult['total_count'] = $result['total_count'];
        }
        if ($result['type']=='group') {
            $finalResult['data'] = $this->flattenResult($result['data'], $query);
        } else {
            if (isset($result['data'])) {
                if (isset($result['data']['value'])) {
                    $finalResult['data']  = $result['data']['value'];
                } else {
                    $finalResult['data']  = $result['data'];
                }
            }
            if (isset($query['select'])) {
                $finalResult['meta']['list'] = $query['select'];
            }
            if (isset($query['displaylist'])) {
                $finalResult['meta']['displaylist'] = $query['displaylist'];
            }
        }
        return $finalResult;
    }

}