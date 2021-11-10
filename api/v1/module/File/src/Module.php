<?php

namespace File;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Oxzion\Error\ErrorHandler;
use Oxzion\Model\FileTable;
use Oxzion\Model\CommentTable;
use Oxzion\Model\SubscriberTable;

class Module implements ConfigProviderInterface
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e)
    {
        $eventManager = $e->getApplication()->getEventManager();
        $sharedEventManager = $eventManager->getSharedManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'onDispatchError'), 0);
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'onRenderError'), 0);
    }

    public function getServiceConfig()
    {
        return [
            'factories' => [
            ],
        ];
    }

    public function getControllerConfig()
    {
        return [
            'factories' => [
                Controller\CommentController::class => function ($container) {
                    return new Controller\CommentController(
                        $container->get(CommentTable::class),
                        $container->get(\Oxzion\Service\CommentService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\SubscriberController::class => function ($container) {
                    return new Controller\SubscriberController(
                        $container->get(SubscriberTable::class),
                        $container->get(\Oxzion\Service\SubscriberService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\FileCallbackController::class => function ($container) {
                    return new Controller\FileCallbackController(
                        $container->get(\Oxzion\Service\FileService::class)
                    );
                },
                Controller\SnoozeController::class => function ($container) {
                    return new Controller\SnoozeController(
                        $container->get(\Oxzion\Service\FileService::class)
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
