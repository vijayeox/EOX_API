<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220308083012 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Rate table';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("CREATE TABLE `ox_rate_condition` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `uuid` varchar(45) DEFAULT NULL,
            `name` varchar(100) NOT NULL,
            `value` varchar(128) NOT NULL,
            `account_id` int(11) DEFAULT NULL,
            `isdeleted` tinyint(1) DEFAULT 0,
            `version` int(11) NOT NULL DEFAULT 0,
            `app_id` int(11) NOT NULL,
            `entity_id` int(11) DEFAULT NULL,
            `date_created` datetime DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            FOREIGN KEY (account_id) REFERENCES ox_account(id),
            FOREIGN KEY (app_id) REFERENCES ox_app(id),
            FOREIGN KEY (entity_id) REFERENCES ox_app_entity(id),
            FOREIGN KEY (created_by) REFERENCES ox_user(id)
            ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8");

        $this->addSql("CREATE TABLE `ox_rate` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `uuid` varchar(45) DEFAULT NULL,
            `condition_1` int(11) NOT NULL,
            `condition_2` int(11) DEFAULT NULL,
            `condition_3` int(11) DEFAULT NULL,
            `condition_4` int(11) DEFAULT NULL,
            `condition_5` int(11) DEFAULT NULL,
            `condition_6` int(11) DEFAULT NULL,
            `conditional_expression` varchar(128) DEFAULT NULL,
            `rate` varchar(128) DEFAULT NULL,
            `isdeleted` tinyint(1) DEFAULT 0,
            `version` int(11) NOT NULL DEFAULT 0,
            `account_id` int(11) DEFAULT NULL,
            `app_id` int(11) NOT NULL,
            `entity_id` int(11) DEFAULT NULL,
            `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
            `date_modified` datetime DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            `modified_by` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            FOREIGN KEY (account_id) REFERENCES ox_account(id),
            FOREIGN KEY (app_id) REFERENCES ox_app(id),
            FOREIGN KEY (entity_id) REFERENCES ox_app_entity(id),
            FOREIGN KEY (condition_1) REFERENCES ox_rate_condition(id),
            FOREIGN KEY (condition_1) REFERENCES ox_rate_condition(id),
            FOREIGN KEY (condition_1) REFERENCES ox_rate_condition(id),
            FOREIGN KEY (condition_1) REFERENCES ox_rate_condition(id),
            FOREIGN KEY (condition_1) REFERENCES ox_rate_condition(id),
            FOREIGN KEY (condition_1) REFERENCES ox_rate_condition(id),
            FOREIGN KEY (created_by) REFERENCES ox_user(id),
            FOREIGN KEY (modified_by) REFERENCES ox_user(id)
            ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8");

        $this->addSql("INSERT INTO ox_privilege (name,permission_allowed,app_id) values ('MANAGE_RATES',15,1);");

        $sql = "SELECT ox_account.id as account_id,ox_role.id as role_id from ox_account inner join ox_role on ox_account.id=ox_role.account_id where ox_role.name='Admin' and ox_role.app_id is NULL ";
        $result = $this->connection->executeQuery($sql)->fetchAll();
        if(count($result)>0){
            foreach($result as $row){
                $this->addSql("INSERT INTO `ox_role_privilege` (`role_id`,`privilege_name`,`permission`,`account_id`,`app_id`) SELECT ".$row['role_id'].",'MANAGE_RATES',15,".$row['account_id'].",id from ox_app WHERE name LIKE 'Admin';");
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("DROP TABLE ox_rate");
        $this->addSql("DROP TABLE ox_rate_condition");
        $this->addSql("DELETE FROM `ox_role_privilege` WHERE `privilege_name` = 'MANAGE_RATES'");
        $this->addSql("DELETE FROM `ox_privilege` WHERE `name` = 'MANAGE_RATES'");
    }
}
