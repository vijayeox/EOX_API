<?php
namespace Oxzion\AppDelegate;

use Analytics\Service\QueryService;
use Logger;

trait AnalyticsTrait
{
    protected $logger;
    private $queryService;
    private $dataSource;
    private $appName;
    private $entityName;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
    public function setQueryService(QueryService $queryService)
    {
        $this->logger->info("SET Query SERVICE -> " . get_class($queryService));
        $this->queryService = $queryService;
    }
    public function setQueryConfig($data)
    {
        $this->logger->info("SET Config -> " . print_r($data, true));
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'dataSource':
                    $this->setDataSource($data[$key]);
                    break;
                case 'appName':
                    $this->setAppName($data[$key]);
                    break;
                case 'entityName':
                    $this->setEntityName($data[$key]);
                    break;
            }
        }
    }
    public function setDataSource($uuid)
    {
        $this->logger->info("SET Data Source -> " . $uuid);
        $this->dataSource = $uuid;
    }
    public function setAppName(String $name = '')
    {
        $this->logger->info("SET App Name -> " . $name);
        $this->appName = $name;
    }
    public function setEntityName(String $name = null)
    {
        $this->logger->info("SET Entity Name -> $name");
        $this->entityName = $name;
    }

    public function runQuery(Array $filters)
    {
        $this->logger->info("runQuery -> ".print_r($filters, true));
        return $this->queryService->previewQuery([
            'datasource_id' => $this->dataSource,
            'configuration' => $filters + ['app_name' => $this->appName, 'entity_name' => $this->entityName]
        ]);
    }

}