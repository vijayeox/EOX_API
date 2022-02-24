<?php

namespace Announcement;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Oxzion\Error\ErrorHandler;
use Oxzion\Service\AccountService;
use Attachment\Service\AttachmentService;

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
                Service\AnnouncementService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\AnnouncementService($container->get('config'), $dbAdapter, $container->get(Model\AnnouncementTable::class), $container->get(AccountService::class));
                },
                Model\AnnouncementTable::class => function ($container) {
                    $tableGateway = $container->get(Model\AnnouncementTableGateway::class);
                    return new Model\AnnouncementTable($tableGateway);
                },
                Model\AnnouncementTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Announcement());
                    return new TableGateway('ox_announcement', $dbAdapter, null, $resultSetPrototype);
                },
            ],
        ];
    }

    public function getControllerConfig()
    {
        return [
            'factories' => [
                Controller\AnnouncementController::class => function ($container) {
                    return new Controller\AnnouncementController(
                        $container->get(Model\AnnouncementTable::class),
                        $container->get(Service\AnnouncementService::class),
                        $container->get(AdapterInterface::class),
                        $container->get(AttachmentService::class)
                    );
                },
                Controller\HomescreenAnnouncementController::class => function ($container) {
                    return new Controller\HomescreenAnnouncementController(
                        $container->get(Service\AnnouncementService::class)
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
