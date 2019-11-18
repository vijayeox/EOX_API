<?php

namespace Analytics;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\JsonModel;
use Oxzion\Error\ErrorHandler;

class Module implements ConfigProviderInterface {

    public function getConfig() {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e) {
        $eventManager = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'onDispatchError'), 0);
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'onRenderError'), 0);
    }

    public function getServiceConfig() {
        return [
            'factories' => [
                Service\DataSourceService::class => function($container){
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\DataSourceTable::class);
                    $logger = $container->get('AnalyticsLogger');
                    $service = new Service\DataSourceService($config, $dbAdapter, $table, $logger);
                    return new \Oxzion\ServiceLogWrapper($service, $logger);
                },
                Model\DataSourceTable::class => function($container) {
                    $tableGateway = $container->get(Model\DataSourceTableGateway::class);
                    return new Model\DataSourceTable($tableGateway);
                },
                Model\DataSourceTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\DataSource());
                    return new TableGateway('ox_datasource', $dbAdapter, null, $resultSetPrototype);
                },
                Service\QueryService::class => function($container){
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\QueryTable::class);
                    $datasourceService = $container->get(Service\DataSourceService::class);
                    $logger = $container->get('AnalyticsLogger');
                    $service = new Service\QueryService($config, $dbAdapter, $table, $logger,$datasourceService);
                    return new \Oxzion\ServiceLogWrapper($service, $logger);
                },
                Model\QueryTable::class => function($container) {
                    $tableGateway = $container->get(Model\QueryTableGateway::class);
                    return new Model\QueryTable($tableGateway);
                },
                Model\QueryTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Query());
                    return new TableGateway('ox_query', $dbAdapter, null, $resultSetPrototype);
                },
                Service\VisualizationService::class => function($container){
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\VisualizationTable::class);
                    $logger = $container->get('AnalyticsLogger');
                    $service = new Service\VisualizationService($config, $dbAdapter, $table, $logger);
                    return new \Oxzion\ServiceLogWrapper($service, $logger);
                },
                Model\VisualizationTable::class => function($container) {
                    $tableGateway = $container->get(Model\VisualizationTableGateway::class);
                    return new Model\VisualizationTable($tableGateway);
                },
                Model\VisualizationTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Visualization());
                    return new TableGateway('ox_visualization', $dbAdapter, null, $resultSetPrototype);
                },
                Service\WidgetService::class => function($container){
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\WidgetTable::class);
                    $logger = $container->get('AnalyticsLogger');
                    $queryService = $container->get(Service\QueryService::class);
                    $service = new Service\WidgetService($config, $dbAdapter, $table, $logger, $queryService);
                    return new \Oxzion\ServiceLogWrapper($service, $logger);
                },
                Model\WidgetTable::class => function($container) {
                    $tableGateway = $container->get(Model\WidgetTableGateway::class);
                    return new Model\WidgetTable($tableGateway);
                },
                Model\WidgetTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Widget());
                    return new TableGateway('ox_widget', $dbAdapter, null, $resultSetPrototype);
                },
                Service\DashboardService::class => function($container){
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\DashboardTable::class);
                    $logger = $container->get('AnalyticsLogger');
                    $service = new Service\DashboardService($config, $dbAdapter, $table, $logger);
                    return new \Oxzion\ServiceLogWrapper($service, $logger);
                },
                Model\DashboardTable::class => function($container) {
                    $tableGateway = $container->get(Model\DashboardTableGateway::class);
                    return new Model\DashboardTable($tableGateway);
                },
                Model\DashboardTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Dashboard());
                    return new TableGateway('ox_dashboard', $dbAdapter, null, $resultSetPrototype);
                },
            ],
        ];
    }

    public function getControllerConfig() {
        return [
            'factories' => [
                Controller\DataSourceController::class => function($container) {
                    return new Controller\DataSourceController(
                        $container->get(Service\DataSourceService::class), $container->get('AnalyticsLogger'));
                },
                Controller\QueryController::class => function($container) {
                    return new Controller\QueryController(
                        $container->get(Service\QueryService::class), $container->get('AnalyticsLogger'));
                },
                Controller\VisualizationController::class => function($container) {
                    return new Controller\VisualizationController(
                        $container->get(Service\VisualizationService::class), $container->get('AnalyticsLogger'));
                },
                Controller\WidgetController::class => function($container) {
                    return new Controller\WidgetController(
                        $container->get(Service\WidgetService::class), $container->get('AnalyticsLogger'));
                },
                Controller\DashboardController::class => function($container) {
                    return new Controller\DashboardController(
                        $container->get(Service\DashboardService::class), $container->get('AnalyticsLogger'));
                },
            ],
        ];
    }

    public function onDispatchError($e) {
        return ErrorHandler::getJsonModelError($e);
    }

    public function onRenderError($e) {
        return ErrorHandler::getJsonModelError($e);
    }

}
