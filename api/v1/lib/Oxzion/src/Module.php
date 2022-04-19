<?php

namespace Oxzion;

use Logger;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\ModuleManager\ModuleEvent;
use Zend\ModuleManager\ModuleManager;

class Module
{
    private static $logInitialized = false;
    public function init(ModuleManager $moduleManager)
    {
        ini_set('max_execution_time', 100);
        $events = $moduleManager->getEventManager();
        // Registering a listener at default priority, 1, which will trigger
        // after the ConfigListener merges config.
        $events->attach(ModuleEvent::EVENT_MERGE_CONFIG, array($this, 'onMergeConfig'));
    }

    public function onMergeConfig(ModuleEvent $e)
    {
        $configListener = $e->getConfigListener();
        $config = $configListener->getMergedConfig(false);
        if (!self::$logInitialized) {
            self::$logInitialized = true;
            Logger::configure($config['logger']);
        }
    }
    public function getServiceConfig()
    {
        return [
            'factories' => [
                Auth\AuthContext::class => function ($container) {
                    return new Auth\AuthContext();
                },
                Auth\AuthSuccessListener::class => function ($container) {
                    return new Auth\AuthSuccessListener($container->get(Service\UserService::class));
                },
                Service\AppService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\AppService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Model\AppTable::class),
                        $container->get(\Oxzion\Service\WorkflowService::class),
                        $container->get(\Oxzion\Service\FormService::class),
                        $container->get(\Oxzion\Service\FieldService::class),
                        $container->get(\Oxzion\Service\JobService::class),
                        $container->get(\Oxzion\Service\AccountService::class),
                        $container->get(\Oxzion\Service\EntityService::class),
                        $container->get(\Oxzion\Service\PrivilegeService::class),
                        $container->get(\Oxzion\Service\RoleService::class),
                        $container->get(\App\Service\MenuItemService::class),
                        $container->get(\App\Service\PageService::class),
                        $container->get(\Oxzion\Service\UserService::class),
                        $container->get(\Oxzion\Service\BusinessRoleService::class),
                        $container->get(\Oxzion\Service\AppRegistryService::class),
                        $container->get(Messaging\MessageProducer::class)
                    );
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
                Service\UserService::class => function ($container) {
                    return new Service\UserService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Model\UserTable::class),
                        $container->get(Service\AddressService::class),
                        $container->get(Service\EmailService::class),
                        $container->get(Service\TemplateService::class),
                        $container->get(Messaging\MessageProducer::class),
                        $container->get(Service\RoleService::class),
                        $container->get(Service\PersonService::class),
                        $container->get(Service\EmployeeService::class)
                    );
                },
                Model\UserTable::class => function ($container) {
                    return new Model\UserTable(
                        $container->get(Model\UserTableGateway::class)
                    );
                },
                Model\UserTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\User());
                    return new TableGateway(
                        'ox_user',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                Service\ElasticService::class => function ($container) {
                    $config = $container->get('config');
                    return new Service\ElasticService($config);
                },
                Model\FileAttachmentTable::class => function ($container) {
                    $tableGateway = $container->get(Model\FileAttachmentTableGateway::class);
                    return new Model\FileAttachmentTable($tableGateway);
                },
                Model\FileAttachmentTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\FileAttachment());
                    return new TableGateway('ox_file_attachment', $dbAdapter, null, $resultSetPrototype);
                },
                \Oxzion\Service\FileService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new \Oxzion\Service\FileService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(\Oxzion\Model\FileTable::class),
                        $container->get(\Oxzion\Service\FormService::class),
                        $container->get(Messaging\MessageProducer::class),
                        $container->get(\Oxzion\Service\FieldService::class),
                        $container->get(\Oxzion\Service\EntityService::class),
                        $container->get(\Oxzion\Model\FileAttachmentTable::class),
                        $container->get(\Oxzion\Service\SubscriberService::class),
                        $container->get(\Oxzion\Service\BusinessParticipantService::class)
                    );
                },
                Service\RoleService::class => function ($container) {
                    return new Service\RoleService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Model\RoleTable::class),
                        $container->get(Model\PrivilegeTable::class)
                    );
                },
                Integrations\DeltaService::class => function ($container) {
                    return new Integrations\DeltaService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class)
                    );
                },
                Model\RoleTable::class => function ($container) {
                    return new Model\RoleTable(
                        $container->get(Model\RoleTableGateway::class)
                    );
                },
                Model\RoleTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Role());
                    return new TableGateway(
                        'ox_role',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                Service\BusinessRoleService::class => function ($container) {
                    return new Service\BusinessRoleService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Model\BusinessRoleTable::class)
                    );
                },
                Model\BusinessRoleTable::class => function ($container) {
                    return new Model\BusinessRoleTable(
                        $container->get(Model\BusinessRoleTableGateway::class)
                    );
                },
                Model\BusinessRoleTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\BusinessRole());
                    return new TableGateway(
                        'ox_business_role',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                Service\PrivilegeService::class => function ($container) {
                    return new Service\PrivilegeService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Model\PrivilegeTable::class)
                    );
                },
                Model\PrivilegeTable::class => function ($container) {
                    return new Model\PrivilegeTable(
                        $container->get(Model\PrivilegeTableGateway::class)
                    );
                },
                Model\PrivilegeTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Privilege());
                    return new TableGateway(
                        'ox_privilege',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                Service\ProfileService::class => function ($container) {
                    return new Service\ProfileService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Model\ProfileTable::class)
                    );
                },
                Model\ProfileTable::class => function ($container) {
                    return new Model\ProfileTable(
                        $container->get(Model\ProfileTableGateway::class)
                    );
                },
                Model\ProfileTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Profile());
                    return new TableGateway(
                        'ox_profile',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                \Oxzion\Service\CommentService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new \Oxzion\Service\CommentService($container->get('config'), $dbAdapter, $container->get(\Oxzion\Model\CommentTable::class), $container->get(Messaging\MessageProducer::class));
                },
                \Oxzion\Service\SubscriberService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new \Oxzion\Service\SubscriberService($container->get('config'), $dbAdapter, $container->get(\Oxzion\Model\SubscriberTable::class));
                },
                \Oxzion\Model\FileTable::class => function ($container) {
                    $tableGateway = $container->get(\Oxzion\Model\FileTableGateway::class);
                    return new \Oxzion\Model\FileTable($tableGateway);
                },
                \Oxzion\Model\CommentTable::class => function ($container) {
                    $tableGateway = $container->get(\Oxzion\Model\CommentTableGateway::class);
                    return new \Oxzion\Model\CommentTable($tableGateway);
                },
                \Oxzion\Model\SubscriberTable::class => function ($container) {
                    $tableGateway = $container->get(\Oxzion\Model\SubscriberTableGateway::class);
                    return new \Oxzion\Model\SubscriberTable($tableGateway);
                },
                \Oxzion\Model\FileTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Oxzion\Model\File());
                    return new TableGateway('ox_file', $dbAdapter, null, $resultSetPrototype);
                },
                \Oxzion\Model\CommentTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Oxzion\Model\Comment());
                    return new TableGateway('ox_comment', $dbAdapter, null, $resultSetPrototype);
                },
                \Oxzion\Model\SubscriberTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Oxzion\Model\Subscriber());
                    return new TableGateway('ox_subscriber', $dbAdapter, null, $resultSetPrototype);
                },
                Service\FormService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\FormService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Model\FormTable::class),
                        $container->get(FormEngine\FormFactory::class),
                        $container->get(Service\FieldService::class)
                    );
                },
                Service\ActivityService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\ActivityService($container->get('config'), $dbAdapter, $container->get(Model\ActivityTable::class), $container->get(Service\FormService::class));
                },
                Service\FieldService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\FieldService($container->get('config'), $dbAdapter, $container->get(Model\FieldTable::class));
                },
                Model\FormTable::class => function ($container) {
                    $tableGateway = $container->get(Model\FormTableGateway::class);
                    return new Model\FormTable($tableGateway);
                },
                Model\FormTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Form());
                    return new TableGateway('ox_form', $dbAdapter, null, $resultSetPrototype);
                },
                Model\ActivityTable::class => function ($container) {
                    $tableGateway = $container->get(Model\ActivityTableGateway::class);
                    return new Model\ActivityTable($tableGateway);
                },
                Model\ActivityTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Activity());
                    return new TableGateway('ox_activity', $dbAdapter, null, $resultSetPrototype);
                },
                Model\FieldTable::class => function ($container) {
                    $tableGateway = $container->get(Model\FieldTableGateway::class);
                    return new Model\FieldTable($tableGateway);
                },
                Model\FieldTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Field());
                    return new TableGateway('ox_field', $dbAdapter, null, $resultSetPrototype);
                },
                Service\EntityService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\EntityService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Model\App\EntityTable::class),
                        $container->get(Service\FormService::class),
                        $container->get(\App\Service\PageContentService::class)
                    );
                },
                Model\App\EntityTable::class => function ($container) {
                    $tableGateway = $container->get(Model\App\EntityTableGateway::class);
                    return new Model\App\EntityTable($tableGateway);
                },
                Model\App\EntityTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\App\Entity());
                    return new TableGateway('ox_app_entity', $dbAdapter, null, $resultSetPrototype);
                },
                Service\AccountService::class => function ($container) {
                    return new Service\AccountService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Model\AccountTable::class),
                        $container->get(Service\UserService::class),
                        $container->get(Service\RoleService::class),
                        $container->get(Service\PrivilegeService::class),
                        $container->get(Service\OrganizationService::class),
                        $container->get(Service\EntityService::class),
                        $container->get(Service\AppRegistryService::class),
                        $container->get(Messaging\MessageProducer::class)
                    );
                },
                Model\OrganizationTable::class => function ($container) {
                    return new Model\OrganizationTable(
                        $container->get(Model\OrganizationTableGateway::class)
                    );
                },
                Model\OrganizationTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Organization());
                    return new TableGateway(
                        'ox_organization',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                Service\AddressService::class => function ($container) {
                    return new Service\AddressService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Model\AddressTable::class)
                    );
                },
                Model\AddressTable::class => function ($container) {
                    return new Model\AddressTable(
                        $container->get(Model\AddressTableGateway::class)
                    );
                },
                Model\AddressTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Address());
                    return new TableGateway(
                        'ox_address',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                Service\OrganizationService::class => function ($container) {
                    return new Service\OrganizationService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Service\AddressService::class),
                        $container->get(Model\OrganizationTable::class)
                    );
                },
                Model\AccountTable::class => function ($container) {
                    return new Model\AccountTable(
                        $container->get(Model\AccountTableGateway::class)
                    );
                },
                Model\AccountTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Account());
                    return new TableGateway(
                        'ox_account',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                Service\PersonService::class => function ($container) {
                    return new Service\PersonService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Service\AddressService::class),
                        $container->get(Model\PersonTable::class)
                    );
                },
                Model\PersonTable::class => function ($container) {
                    return new Model\PersonTable(
                        $container->get(Model\PersonTableGateway::class)
                    );
                },
                Model\PersonTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Person());
                    return new TableGateway(
                        'ox_person',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                Service\EmployeeService::class => function ($container) {
                    return new Service\EmployeeService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Model\EmployeeTable::class)
                    );
                },
                Model\EmployeeTable::class => function ($container) {
                    return new Model\EmployeeTable(
                        $container->get(Model\EmployeeTableGateway::class)
                    );
                },
                Model\EmployeeTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Employee());
                    return new TableGateway(
                        'ox_employee',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                Workflow\WorkflowFactory::class => function ($container) {
                    return Workflow\WorkflowFactory::getInstance($container->get('config'));
                },
                FormEngine\FormFactory::class => function ($container) {
                    return FormEngine\FormFactory::getInstance();
                },
                Model\WorkflowTable::class => function ($container) {
                    $tableGateway = $container->get(Model\WorkflowTableGateway::class);
                    return new Model\WorkflowTable($tableGateway);
                },
                Model\WorkflowTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Workflow());
                    return new TableGateway('ox_workflow', $dbAdapter, null, $resultSetPrototype);
                },
                Model\WorkflowDeploymentTable::class => function ($container) {
                    $tableGateway = $container->get(Model\WorkflowDeploymentTableGateway::class);
                    return new Model\WorkflowDeploymentTable($tableGateway);
                },
                Model\WorkflowDeploymentTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\WorkflowDeployment());
                    return new TableGateway('ox_workflow_deployment', $dbAdapter, null, $resultSetPrototype);
                },
                Service\WorkflowService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\WorkflowService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Model\WorkflowTable::class),
                        $container->get(Service\FormService::class),
                        $container->get(Service\FieldService::class),
                        $container->get(\Oxzion\Service\FileService::class),
                        $container->get(Workflow\WorkflowFactory::class),
                        $container->get(Service\ActivityService::class),
                        $container->get(Model\WorkflowDeploymentTable::class),
                        $container->get(Service\ActivityInstanceService::class)
                    );
                },
                Service\UserTokenService::class => function ($container) {
                    $config = $container->get('config');
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\UserTokenService($config, $dbAdapter, $container->get(Model\UserTokenTable::class));
                },
                Model\UserTokenTable::class => function ($container) {
                    $tableGateway = $container->get(Model\UserTokenTableGateway::class);
                    return new Model\UserTokenTable($tableGateway);
                },
                Model\UserTokenTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\UserToken());
                    return new TableGateway('ox_user_refresh_token', $dbAdapter, null, $resultSetPrototype);
                },
                Search\SearchEngine::class => function ($container) {
                    $config = $container->get('config');
                    return new Search\Elastic\SearchEngineImpl($config);
                },
                ProspectResearch\InfoEngine::class => function ($container) {
                    $config = $container->get('config');
                    return new ProspectResearch\Discovery\InfoEngineImpl($config);
                },
                Search\Indexer::class => function ($container) {
                    $config = $container->get('config');
                    return new Search\Elastic\IndexerImpl($config);
                },
                Service\ProfilePictureService::class => function ($container) {
                    $config = $container->get('config');
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\ProfilePictureService($config, $dbAdapter,
                        $container->get(Messaging\MessageProducer::class));
                },
                Service\UserSessionService::class => function ($container) {
                    $config = $container->get('config');
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\UserSessionService($config, $dbAdapter);
                },
                Service\TemplateService::class => function ($container) {
                    return new Service\TemplateService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class)
                    );
                },
                Service\AppRegistryService::class => function ($container) {
                    $config = $container->get('config');
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $businessParticipantService = $container->get(\Oxzion\Service\BusinessParticipantService::class);
                    return new Service\AppRegistryService($config, $dbAdapter, $businessParticipantService);
                },
                Service\BusinessParticipantService::class => function ($container) {
                    $config = $container->get('config');
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $entityService = $container->get(\Oxzion\Service\EntityService::class);
                    return new Service\BusinessParticipantService($config, $dbAdapter, $entityService);
                },
                AppDelegate\AppDelegateService::class => function ($container) {
                    return new AppDelegate\AppDelegateService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Document\DocumentBuilder::class),
                        $container->get(Service\TemplateService::class),
                        $container->get(Messaging\MessageProducer::class),
                        $container->get(Service\FileService::class),
                        $container->get(Service\WorkflowInstanceService::class),
                        $container->get(Service\ActivityInstanceService::class),
                        $container->get(Service\UserService::class),
                        $container->get(Service\CommentService::class),
                        $container->get(Service\EsignService::class),
                        $container->get(Service\FieldService::class),
                        $container->get(Service\AccountService::class),
                        $container->get(Service\BusinessParticipantService::class),
                        $container->get(\Analytics\Service\QueryService::class),
                        $container->get(Insurance\InsuranceService::class),
                        $container->get(\Team\Service\TeamService::class),
                        $container->get(Service\AppService::class),
                        $container->get(\App\Service\AppArtifactService::class),
                        $container->get(Service\OverdriveService::class),
                        $container->get(\Rate\Service\RateService::class)
                );
                },
                Document\DocumentBuilder::class => function ($container) {
                    return new Document\DocumentBuilder(
                        $container->get('config'),
                        $container->get(Service\TemplateService::class),
                        new Document\DocumentGeneratorImpl()
                    );
                },
                Service\EmailService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\EmailService($container->get('config'), $dbAdapter, $container->get(Model\EmailTable::class));
                },
                Model\EmailTable::class => function ($container) {
                    $tableGateway = $container->get(Model\EmailTableGateway::class);
                    return new Model\EmailTable($tableGateway);
                },
                Model\EmailTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Email());
                    return new TableGateway('email_setting_user', $dbAdapter, null, $resultSetPrototype);
                },
                NLP\NLPEngine::class => function ($container) {
                    return new NLP\Dialogflow\NLPDialogflowV1();
                },
                Transformer\AttributeTransformer::class => function ($container) {
                    return new Transformer\AttributeTransformer($container->get('config'));
                },
                Transformer\JsonTransformerService::class => function ($container) {
                    return new Transformer\JsonTransformerService(
                        $container->get('config'),
                        $container->get(Transformer\AttributeTransformer::class)
                    );
                },
                Service\UserCacheService::class => function ($container) {
                    return new Service\UserCacheService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Model\UserCacheTable::class)
                    );
                },
                Model\UserCacheTable::class => function ($container) {
                    return new Model\UserCacheTable(
                        $container->get(Model\UserCacheTableGateway::class)
                    );
                },
                Model\UserCacheTableGateway::class => function ($container) {
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\UserCache());
                    return new TableGateway(
                        'ox_user_cache',
                        $container->get(AdapterInterface::class),
                        null,
                        $resultSetPrototype
                    );
                },
                Model\ErrorLogTable::class => function ($container) {
                    $tableGateway = $container->get(Model\ErrorLogTableGateway::class);
                    return new Model\ErrorLogTable($tableGateway);
                },
                Model\ErrorLogTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\ErrorLog());
                    return new TableGateway('ox_error_log', $dbAdapter, null, $resultSetPrototype);
                },
                Messaging\MessageProducer::class => function ($container) {
                    $config = $container->get('config');
                    return new Messaging\MessageProducer($config, $container->get(Service\ErrorLogService::class));
                },
                Service\ErrorLogService::class => function ($container) {
                    return new Service\ErrorLogService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Model\ErrorLogTable::class),
                        $container->get(Service\UserCacheService::class),
                        $container->get(Workflow\WorkflowFactory::class)
                    );
                },
                Model\WorkflowInstanceTable::class => function ($container) {
                    $tableGateway = $container->get(Model\WorkflowInstanceTableGateway::class);
                    return new Model\WorkflowInstanceTable($tableGateway);
                },
                Model\ActivityInstanceTable::class => function ($container) {
                    $tableGateway = $container->get(Model\ActivityInstanceTableGateway::class);
                    return new Model\ActivityInstanceTable($tableGateway);
                },
                Model\WorkflowInstanceTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\WorkflowInstance());
                    return new TableGateway('ox_workflow_instance', $dbAdapter, null, $resultSetPrototype);
                },
                Model\ActivityInstanceTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\ActivityInstance());
                    return new TableGateway('ox_activity_instance', $dbAdapter, null, $resultSetPrototype);
                },
                Service\RegistrationService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\RegistrationService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Service\AccountService::class),
                        $container->get(Service\AppRegistryService::class)
                    );
                },
                Service\WorkflowInstanceService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\WorkflowInstanceService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Model\WorkflowInstanceTable::class),
                        $container->get(Service\FileService::class),
                        $container->get(\Oxzion\Service\EntityService::class),
                        $container->get(Service\WorkflowService::class),
                        $container->get(Workflow\WorkflowFactory::class),
                        $container->get(Service\ActivityInstanceService::class),
                        $container->get(Service\RegistrationService::class)
                    );
                },
                Service\ActivityInstanceService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\ActivityInstanceService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Model\ActivityInstanceTable::class),
                        $container->get(Workflow\WorkflowFactory::class),
                        $container->get(Service\FileService::class)
                    );
                },
                Model\JobTable::class => function ($container) {
                    $tableGateway = $container->get(Model\JobTableGateway::class);
                    return new Model\JobTable($tableGateway);
                },
                Model\JobTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Job());
                    return new TableGateway('ox_job', $dbAdapter, null, $resultSetPrototype);
                },
                Service\JobService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\JobService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Messaging\MessageProducer::class),
                        $container->get(Model\JobTable::class)
                    );
                },
                Model\KraTable::class => function ($container) {
                    $tableGateway = $container->get(Model\KraTableGateway::class);
                    return new Model\KraTable($tableGateway);
                },
                Model\KraTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\Kra());
                    return new TableGateway('ox_kra', $dbAdapter, null, $resultSetPrototype);
                },
                Service\KraService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $accoutService = $container->get(Service\AccountService::class);
                    $userService = $container->get(Service\UserService::class);
                    $queryService = $container->get(\Analytics\Service\QueryService::class);
                    return new Service\KraService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Model\KraTable::class),
                        $accoutService,
                        $userService,
                        $queryService
                    );
                },
                Service\CommandService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\CommandService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Service\TemplateService::class),
                        $container->get(AppDelegate\AppDelegateService::class),
                        $container->get(Service\FileService::class),
                        $container->get(Service\JobService::class),
                        $container->get(Messaging\MessageProducer::class),
                        $container->get(Service\WorkflowInstanceService::class),
                        $container->get(Service\WorkflowService::class),
                        $container->get(Service\UserService::class),
                        $container->get(Service\UserCacheService::class),
                        $container->get(Service\RegistrationService::class),
                        $container->get(Service\BusinessParticipantService::class),
                        $container->get(Transformer\JsonTransformerService::class),
                        $container->get(Service\EsignService::class)
                    );
                },
                Model\ServiceTaskInstanceTable::class => function ($container) {
                    $tableGateway = $container->get(Model\ServiceTaskInstanceTableGateway::class);
                    return new Model\ServiceTaskInstanceTable($tableGateway);
                },
                Model\ServiceTaskInstanceTableGateway::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new Model\ServiceTaskInstance());
                    return new TableGateway('ox_service_task_instance', $dbAdapter, null, $resultSetPrototype);
                },
                Service\ServiceTaskService::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Service\ServiceTaskService(
                        $container->get('config'),
                        $dbAdapter,
                        $container->get(Model\ServiceTaskInstanceTable::class),
                        $container->get(Service\CommandService::class),
                        $container->get(Service\WorkflowInstanceService::class)
                    );
                },
                Service\QuickBooksService::class => function ($container) {
                    return new Service\QuickBooksService();
                },
                Service\ElasticService::class => function ($container) {
                    return new Service\ElasticService();
                },
                Analytics\API\AnalyticsEngineQuickBooksImpl::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Analytics\API\AnalyticsEngineQuickBooksImpl(
                        $dbAdapter,
                        $container->get('config'),
                        $container->get(Service\QuickBooksService::class)
                    );
                },
                Service\AnalyticsCustomAPIService::class => function ($container) {
                    return new Service\AnalyticsCustomAPIService();
                },
                Analytics\API\AnalyticsEngineCustomAPIImpl::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Analytics\API\AnalyticsEngineCustomAPIImpl(
                        $dbAdapter,
                        $container->get('config'),
                        $container->get(Service\AnalyticsCustomAPIService::class)
                    );
                },
                Analytics\Elastic\AnalyticsEngineImpl::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Analytics\Elastic\AnalyticsEngineImpl(
                        $dbAdapter,
                        $container->get('config'),
                        $container->get(Service\ElasticService::class)
                    );
                },
                Analytics\Relational\AnalyticsEngineMySQLImpl::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Analytics\Relational\AnalyticsEngineMySQLImpl($dbAdapter, $container->get('config'));
                },
                Analytics\Relational\AnalyticsEnginePostgresImpl::class => function ($container) {
                    $dbAdapter = $container->get(AdapterInterface::class);
                    return new Analytics\Relational\AnalyticsEnginePostgresImpl($dbAdapter, $container->get('config'));
                },
                Insurance\InsuranceService::class => function ($container) {
                    return new Insurance\InsuranceService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class),
                        $container->get(Messaging\MessageProducer::class)
                    );
                },
                Service\OverdriveService::class => function ($container) {
                    return new Service\OverdriveService(
                        $container->get('config'),
                        $container->get(AdapterInterface::class)
                    );
                },
            ],
        ];
    }
    /**
     * Retrieve default zend-db configuration for zend-mvc context.
     *
     * @return array
     */
    public function getConfig()
    {
        return [
        ];
    }
}