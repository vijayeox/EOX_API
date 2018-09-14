<?php

namespace Screen;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\JsonModel;
use Oxzion\Error\ErrorHandler;

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
                UserService::class => function($container) {
                    $config = $container->get('config');
                    return new UserService($config);
                },
                Model\ScreenTable::class => function($container) {
                    $tableGateway = $container->get(Model\ScreenTableGateway::class);
                    return new Model\ScreenTable($tableGateway);
                },
                Model\ScreenTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Screen());
                    return new TableGateway('ox_screen', $dbAdapter, null, $resultSetPrototype);
                },
                Model\ScreenwidgetTable::class => function($container) {
                    $tableGateway = $container->get(Model\ScreenwidgetTableGateway::class);
                    return new Model\ScreenwidgetTable($tableGateway);
                },
                Model\ScreenwidgetTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Screenwidget());
                    return new TableGateway('ox_screen_widget', $dbAdapter, null, $resultSetPrototype);
                },
            ],
        ];
    }
    public function getControllerConfig()
    {
        return [
            'factories' => [
                Controller\ScreenController::class => function($container) {
                    return new Controller\ScreenController(
                        $container->get(Model\ScreenTable::class),$container->get('ScreenLogger'));
                },
                Controller\ScreenwidgetController::class => function($container) {
                    return new Controller\ScreenwidgetController(
                        $container->get(Model\ScreenwidgetTable::class),$container->get('ScreenLogger'));
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
