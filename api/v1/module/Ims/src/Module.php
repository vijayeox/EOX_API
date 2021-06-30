<?php

namespace Ims;

use Oxzion\Error\ErrorHandler;
use Oxzion\Insurance\InsuranceService;
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
            ],
        ];
    }

    public function getControllerConfig()
    {
        return [
            'factories' => [
                Controller\ProducerController::class => function ($container) {
                    return new Controller\ProducerController(
                        $container->get(InsuranceService::class)
                    );
                },
                Controller\InsuredController::class => function ($container) {
                    return new Controller\InsuredController(
                        $container->get(InsuranceService::class)
                    );
                },
                Controller\QuoteController::class => function ($container) {
                    return new Controller\QuoteController(
                        $container->get(InsuranceService::class)
                    );
                },
                Controller\DocumentController::class => function ($container) {
                    return new Controller\DocumentController(
                        $container->get(InsuranceService::class)
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
