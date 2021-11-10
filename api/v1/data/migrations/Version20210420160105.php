<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210420160105 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("DELETE FROM `ox_privilege` WHERE `name` = 'MANAGE_ORGCHART'");
        $this->addSql("DELETE FROM `ox_privilege` WHERE `name` = 'MANAGE_DOCUMENTS'");
        $this->addSql("DELETE FROM `ox_role_privilege` WHERE app_id in(select id from ox_app where `name` = 'OrgChart') and account_id=2");
        $this->addSql("DELETE FROM `ox_role_privilege` WHERE app_id in(select id from ox_app where `name` = 'Documents') and account_id=2");
        $this->addSql("DELETE FROM `ox_app_registry` WHERE app_id in(select id from ox_app where `name` = 'OrgChart') and account_id=2");
        $this->addSql("DELETE FROM `ox_app_registry` WHERE app_id in(select id from ox_app where `name` = 'Documents') and account_id=2");
        $this->addSql("INSERT INTO `ox_privilege` (`name`,`permission_allowed`,`app_id`) SELECT 'MANAGE_ORGCHART',1,id from ox_app where name LIKE 'OrgChart';");
        $this->addSql("INSERT INTO `ox_privilege` (`name`,`permission_allowed`,`app_id`) SELECT 'MANAGE_DOCUMENTS',1,id from ox_app where name LIKE 'Documents';");
        $this->addSql("INSERT INTO `ox_role_privilege` (`role_id`,`privilege_name`,`permission`,`account_id`,`app_id`) SELECT 1,'MANAGE_DOCUMENTS',1,1,id from ox_app where name LIKE 'Documents';");
        $this->addSql("INSERT INTO `ox_role_privilege` (`role_id`,`privilege_name`,`permission`,`account_id`,`app_id`) SELECT 1,'MANAGE_ORGCHART',1,1,id from ox_app where name LIKE 'OrgChart';");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
