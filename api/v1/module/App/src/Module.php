<?php

namespace App;

use App\Service\MenuItemService;
use App\Service\PageService;
use Group\Service\GroupService;
use Oxzion\Error\ErrorHandler;
use Oxzion\Model\FieldTable;
use Oxzion\Model\FileTable;
use Oxzion\Model\FormTable;
use Oxzion\Model\WorkflowTable;
use Oxzion\Service\CommandService;
use Oxzion\Service\ErrorLogService;
use Oxzion\Service\FieldService;
use Oxzion\Service\FileService;
use Oxzion\Service\FormService;
use Oxzion\Service\RoleService;
use Oxzion\Service\UserCacheService;
use Oxzion\Service\UserService;
use Oxzion\Service\WorkflowService;
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
                Service\AppService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\AppService($container->get('config'), $dbAdapter, $container->get(Model\AppTable::class), $container->get(\Oxzion\Service\WorkflowService::class), $container->get(\Oxzion\Service\FormService::class), $container->get(\Oxzion\Service\FieldService::class), $container->get(\Oxzion\Service\OrganizationService::class), $container->get(Service\EntityService::class), $container->get(\Oxzion\Service\PrivilegeService::class), $container->get(\Oxzion\Service\RoleService::class), $container->get(\App\Service\MenuItemService::class), $container->get(\App\Service\PageService::class));
                },
                Model\AppTable::class => function ($container) {
                    $tableGateway = $container->get(Model\AppTableGateway::class);
                    return new Model\AppTable($tableGateway);
                },
                Model\AppTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\App());
                    return new TableGateway('ox_app', $dbAdapter, null, $resultSetPrototype);
                },
                Service\MenuItemService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\MenuItemService($container->get('config'), $container->get(GroupService::class), $dbAdapter, $container->get(Model\MenuItemTable::class));
                },
                Model\MenuItemTable::class => function ($container) {
                    $tableGateway = $container->get(Model\MenuItemTableGateway::class);
                    return new Model\MenuItemTable($tableGateway);
                },
                Model\MenuItemTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\MenuItem());
                    return new TableGateway('ox_app_menu', $dbAdapter, null, $resultSetPrototype);
                },
                Service\PageService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\PageService($container->get('config'), $container->get(Service\PageContentService::class), $dbAdapter, $container->get(Model\PageTable::class));
                },
                Model\PageTable::class => function ($container) {
                    $tableGateway = $container->get(Model\PageTableGateway::class);
                    return new Model\PageTable($tableGateway);
                },
                Model\PageTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Page());
                    return new TableGateway('ox_app_page', $dbAdapter, null, $resultSetPrototype);
                },
                Service\EntityService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\EntityService($container->get('config'), $container->get(\Oxzion\Service\WorkflowService::class), $dbAdapter, $container->get(Model\EntityTable::class));
                },
                Model\EntityTable::class => function ($container) {
                    $tableGateway = $container->get(Model\EntityTableGateway::class);
                    return new Model\EntityTable($tableGateway);
                },
                Model\EntityTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Entity());
                    return new TableGateway('ox_app_entity', $dbAdapter, null, $resultSetPrototype);
                },
                Service\PageContentService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\PageContentService($container->get('config'),
                        $dbAdapter, $container->get(Model\PageContentTable::class));
                },
                Model\PageContentTable::class => function ($container) {
                    $tableGateway = $container->get(Model\PageContentTableGateway::class);
                    return new Model\PageContentTable($tableGateway);
                },
                Model\PageContentTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\PageContent());
                    return new TableGateway('ox_page_content', $dbAdapter, null, $resultSetPrototype);
                },
            ],
        ];
    }

    public function getControllerConfig()
    {
        return [
            'factories' => [
                Controller\AppController::class => function ($container) {
                    return new Controller\AppController(
                        $container->get(Model\AppTable::class),
                        $container->get(Service\AppService::class),
                        $container->get(AdapterInterface::class),
                        $container->get(WorkflowService::class)
                    );
                },
                Controller\AppRegisterController::class => function ($container) {
                    return new Controller\AppRegisterController(
                        $container->get(Model\AppTable::class),
                        $container->get(Service\AppService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\AppDelegateController::class => function ($container) {
                    return new Controller\AppDelegateController(
                        $container->get(\Oxzion\AppDelegate\AppDelegateService::class),
                        $container->get(UserService::class)
                    );
                },
                Controller\MenuItemController::class => function ($container) {
                    return new Controller\MenuItemController(
                        $container->get(Model\MenuItemTable::class),
                        $container->get(Service\MenuItemService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\PageController::class => function ($container) {
                    return new Controller\PageController(
                        $container->get(Model\PageTable::class),
                        $container->get(Service\PageService::class),
                        $container->get(Service\PageContentService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\PageContentController::class => function ($container) {
                    return new Controller\PageContentController(
                        $container->get(Model\PageContentTable::class),
                        $container->get(Service\PageContentService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\FormController::class => function ($container) {
                    return new Controller\FormController(
                        $container->get(FormTable::class),
                        $container->get(FormService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\FileController::class => function ($container) {
                    return new Controller\FileController(
                        $container->get(FileTable::class),
                        $container->get(FileService::class),
                        $container->get(AdapterInterface::class),
                        $container->get(WorkflowService::class)
                    );
                },
                Controller\FieldController::class => function ($container) {
                    return new Controller\FieldController(
                        $container->get(FieldTable::class),
                        $container->get(FieldService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\WorkflowController::class => function ($container) {
                    return new Controller\WorkflowController(
                        $container->get(WorkflowTable::class),
                        $container->get(WorkflowService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\ImportController::class => function ($container) {
                    return new Controller\ImportController(
                        $container->get(Service\ImportService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\CacheController::class => function ($container) {
                    return new Controller\CacheController(
                        $container->get(\Oxzion\Service\UserCacheService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\PaymentController::class => function ($container) {
                    return new Controller\PaymentController(
                        $container->get(Model\PaymentTable::class),
                        $container->get(Service\PaymentService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\EntityController::class => function ($container) {
                    return new Controller\EntityController(
                        $container->get(Model\EntityTable::class),
                        $container->get(Service\EntityService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\ErrorLogController::class => function ($container) {
                    return new Controller\ErrorLogController(
                        $container->get(ErrorLogService::class),
                        $container->get(AdapterInterface::class)
                    );
                },
                Controller\DocumentController::class => function ($container) {
                    return new Controller\DocumentController(
                        $container->get('config')
                    );
                },
                Controller\PipelineController::class => function ($container) {
                    return new Controller\PipelineController(
                        $container->get(CommandService::class)
                    );
                },
                Controller\CommandController::class => function ($container) {
                    return new Controller\CommandController(
                        $container->get(CommandService::class)
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
