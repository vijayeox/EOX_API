<?php

namespace UserAuditLog;

use Oxzion\Error\ErrorHandler;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Service\AccountService;
use Oxzion\Service\UserService;
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
                Service\UserAuditLogService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $accoutService = $container->get(AccountService::class);
                    $userService = $container->get(UserService::class);
                    return new Service\UserAuditLogService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Model\UserAuditLogTable::class),
                        $accoutService,
                        $container->get(MessageProducer::class),
                        $userService
                    );
                },
                Model\UserAuditLogTable::class => function ($container) {
                    $tableGateway = $container->get(Model\UserAuditLogTableGateway::class);
                    return new Model\UserAuditLogTable($tableGateway);
                },
                Model\UserAuditLogTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\UserAuditLog());
                    return new TableGateway('ox_user_audit_log', $dbAdapter, null, $resultSetPrototype);
                },
            ],
        ];
    }

    public function getControllerConfig()
    {
        return [
            'factories' => [
                Controller\UserAuditLogController::class => function ($container) {
                    return new Controller\UserAuditLogController(
                        $container->get(Model\UserAuditLogTable::class),
                        $container->get(Service\UserAuditLogService::class),
                        $container->get(AdapterInterface::class),
                        $container->get(AccountService::class)
                    );
                }
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
