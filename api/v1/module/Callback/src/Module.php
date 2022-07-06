<?php

namespace Callback;

use Oxzion\Error\ErrorHandler;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Oxzion\Service\UserService;
use Oxzion\Service\EmailService;
use Oxzion\Model\EmailTable;
use Oxzion\Service\TemplateService;
use Oxzion\Service\AddressService;
use Oxzion\Messaging\MessageProducer;

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
                Service\ChatService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\ChatService($container->get('config'), $dbAdapter, $container->get(\Oxzion\Service\FileService::class), $container->get(\Oxzion\Service\SubscriberService::class), $container->get(\Oxzion\Service\UserService::class));
                },
                Service\CommentsService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\CommentsService($container->get('config'), $dbAdapter, $container->get(\Oxzion\Service\FileService::class), $container->get(\Oxzion\Service\SubscriberService::class), $container->get(\Oxzion\Service\CommentService::class), $container->get(\Oxzion\Service\UserService::class), $container->get(\Callback\Service\ChatService::class));
                },
                Service\CRMService::class => function ($container) {
                    return new Service\CRMService($container->get('config'), $container->get(\Contact\Service\ContactService::class), $container->get(UserService::class));
                },
                Service\CalendarService::class => function ($container) {
                    return new Service\CalendarService($container->get('config'));
                },
                Service\TaskService::class => function ($container) {
                    return new Service\TaskService($container->get('config'));
                },
                \Contact\Service\ContactService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new \Contact\Service\ContactService($container->get('config'), $dbAdapter, $container->get(\Contact\Model\ContactTable::class), $container->get(AddressService::class));
                },
                \Contact\Model\ContactTable::class => function ($container) {
                    $tableGateway = $container->get(\Contact\Model\ContactTableGateway::class);
                    return new \Contact\Model\ContactTable($tableGateway);
                },
                \Contact\Model\ContactTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Contact\Model\Contact());
                    return new TableGateway('ox_contact', $dbAdapter, null, $resultSetPrototype);
                },
                Controller\EmailController::class => function ($container) {
                    return new Controller\EmailController(
                        $container->get(EmailTable::class),
                        $container->get(EmailService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
            ],
        ];
    }

    public function getControllerConfig()
    {
        return [
            'factories' => [
                Controller\ChatCallbackController::class => function ($container) {
                    return new Controller\ChatCallbackController($container->get(Service\ChatService::class));
                },
                Controller\CRMCallbackController::class => function ($container) {
                    return new Controller\CRMCallbackController($container->get(Service\CRMService::class));
                },
                Controller\TaskCallbackController::class => function ($container) {
                    return new Controller\TaskCallbackController($container->get(Service\TaskService::class));
                },
                Controller\OXCallbackController::class => function ($container) {
                    return new Controller\OXCallbackController($container->get(TemplateService::class), $container->get('config'), $container->get(MessageProducer::class));
                },
                Controller\CalendarCallbackController::class => function ($container) {
                    return new Controller\CalendarCallbackController($container->get(Service\CalendarService::class), $container->get(EmailService::class), $container->get('config'));
                },
                Controller\CommentController::class => function ($container) {
                    return new Controller\CommentController($container->get(Service\CommentsService::class));
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
