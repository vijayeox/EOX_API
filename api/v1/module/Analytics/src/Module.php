<?php

namespace Analytics;

use Oxzion\Error\ErrorHandler;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;

class Module implements ConfigProviderInterface
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e)
    {
        $eventManager = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'onDispatchError'), 0);
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'onRenderError'), 0);
    }

    public function getServiceConfig()
    {
        return [
            'factories' => [
                Service\DataSourceService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\DataSourceTable::class);
                    return new Service\DataSourceService(
                        $config,
                        $dbAdapter,
                        $table,
                        array("ELASTIC" => $container->get(\Oxzion\Analytics\Elastic\AnalyticsEngineImpl::class),
                            "MYSQL" => $container->get(\Oxzion\Analytics\Relational\AnalyticsEngineMySQLImpl::class),
                            "POSTGRES" => $container->get(\Oxzion\Analytics\Relational\AnalyticsEnginePostgresImpl::class),
                            "QUICKBOOKS" => $container->get(\Oxzion\Analytics\API\AnalyticsEngineQuickBooksImpl::class),
                            "API" => $container->get(\Oxzion\Analytics\API\AnalyticsEngineCustomAPIImpl::class))
                    );
                    // return new \Oxzion\ServiceLogWrapper($service);
                },
                Model\DataSourceTable::class => function ($container) {
                    $tableGateway = $container->get(Model\DataSourceTableGateway::class);
                    return new Model\DataSourceTable($tableGateway);
                },
                Model\DataSourceTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\DataSource());
                    return new TableGateway('ox_datasource', $dbAdapter, null, $resultSetPrototype);
                },
                Service\QueryService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\QueryTable::class);
                    $datasourceService = $container->get(Service\DataSourceService::class);
                    return new Service\QueryService($config, $dbAdapter, $table, $datasourceService);
                    // return new \Oxzion\ServiceLogWrapper($service);
                },
                Model\QueryTable::class => function ($container) {
                    $tableGateway = $container->get(Model\QueryTableGateway::class);
                    return new Model\QueryTable($tableGateway);
                },
                Model\QueryTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Query());
                    return new TableGateway('ox_query', $dbAdapter, null, $resultSetPrototype);
                },
                Service\VisualizationService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\VisualizationTable::class);
                    return new Service\VisualizationService($config, $dbAdapter, $table);
                    // return new \Oxzion\ServiceLogWrapper($service);
                },
                Model\VisualizationTable::class => function ($container) {
                    $tableGateway = $container->get(Model\VisualizationTableGateway::class);
                    return new Model\VisualizationTable($tableGateway);
                },
                Model\VisualizationTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Visualization());
                    return new TableGateway('ox_visualization', $dbAdapter, null, $resultSetPrototype);
                },
                Service\TargetService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\TargetTable::class);
                    $queryService = $container->get(Service\QueryService::class);
                    $widgetService = $container->get(Service\WidgetService::class);
                    return new Service\TargetService($config, $dbAdapter, $table, $queryService, $widgetService);
                },
                Model\TargetTable::class => function ($container) {
                    $tableGateway = $container->get(Model\TargetTableGateway::class);
                    return new Model\TargetTable($tableGateway);
                },
                Model\TargetTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Target());
                    return new TableGateway('ox_target', $dbAdapter, null, $resultSetPrototype);
                },
                Service\WidgetService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\WidgetTable::class);
                    $queryService = $container->get(Service\QueryService::class);
                    return new Service\WidgetService($config, $dbAdapter, $table, $queryService);
                    // return new \Oxzion\ServiceLogWrapper($service);
                },
                Model\WidgetTable::class => function ($container) {
                    $tableGateway = $container->get(Model\WidgetTableGateway::class);
                    return new Model\WidgetTable($tableGateway);
                },
                Model\WidgetTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Widget());
                    return new TableGateway('ox_widget', $dbAdapter, null, $resultSetPrototype);
                },
                Service\DashboardService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\DashboardTable::class);
                    return new Service\DashboardService($config, $dbAdapter, $table);
                    // return new \Oxzion\ServiceLogWrapper($service);
                },
                Model\DashboardTable::class => function ($container) {
                    $tableGateway = $container->get(Model\DashboardTableGateway::class);
                    return new Model\DashboardTable($tableGateway);
                },
                Model\DashboardTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Dashboard());
                    return new TableGateway('ox_dashboard', $dbAdapter, null, $resultSetPrototype);
                },
                Service\TemplateService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    return new Service\TemplateService($config, $dbAdapter);
                },
            ],
        ];
    }

    public function getControllerConfig()
    {
        return [
            'factories' => [
                Controller\DataSourceController::class => function ($container) {
                    return new Controller\DataSourceController(
                        $container->get(Service\DataSourceService::class)
                    );
                },
                Controller\QueryController::class => function ($container) {
                    return new Controller\QueryController(
                        $container->get(Service\QueryService::class)
                    );
                },
                Controller\VisualizationController::class => function ($container) {
                    return new Controller\VisualizationController(
                        $container->get(Service\VisualizationService::class)
                    );
                },
                Controller\TargetController::class => function ($container) {
                    return new Controller\TargetController(
                        $container->get(Service\TargetService::class)
                    );
                },
                Controller\WidgetController::class => function ($container) {
                    return new Controller\WidgetController(
                        $container->get(Service\WidgetService::class)
                    );
                },
                Controller\DashboardController::class => function ($container) {
                    return new Controller\DashboardController(
                        $container->get(Service\DashboardService::class)
                    );
                }, Controller\TemplateController::class => function ($container) {
                    return new Controller\TemplateController(
                        $container->get(Service\TemplateService::class)
                    );
                },
            ],
        ];
    }

    public function onDispatchError($e)
    {
        return ErrorHandler::getJsonModelError($e);
    }

    public function onRenderError($e)
    {
        return ErrorHandler::getJsonModelError($e);
    }
}
