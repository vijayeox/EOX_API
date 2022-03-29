<?php

namespace Rate;

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
                Service\RateService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\RateTable::class);
                    $rateConditionService = $container->get(Service\RateConditionService::class);
                    return new Service\RateService($config, $dbAdapter, $table, $rateConditionService);
                },
                Model\RateTable::class => function ($container) {
                    $tableGateway = $container->get(Model\RateTableGateway::class);
                    return new Model\RateTable($tableGateway);
                },
                Model\RateTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Rate());
                    return new TableGateway('ox_rate', $dbAdapter, null, $resultSetPrototype);
                },
                Service\RateConditionService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $config = $container->get('config');
                    $table = $container->get(Model\RateConditionTable::class);
                    return new Service\RateConditionService($config, $dbAdapter, $table);
                },
                Model\RateConditionTable::class => function ($container) {
                    $tableGateway = $container->get(Model\RateConditionTableGateway::class);
                    return new Model\RateConditionTable($tableGateway);
                },
                Model\RateConditionTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\RateCondition());
                    return new TableGateway('ox_rate_condition', $dbAdapter, null, $resultSetPrototype);
                },
            ],
        ];
    }

    public function getControllerConfig()
    {
        return [
            'factories' => [
                Controller\RateController::class => function ($container) {
                    return new Controller\RateController(
                        $container->get(Service\RateService::class)
                    );
                },
                Controller\RateConditionController::class => function ($container) {
                    return new Controller\RateConditionController(
                        $container->get(Service\RateConditionService::class)
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
