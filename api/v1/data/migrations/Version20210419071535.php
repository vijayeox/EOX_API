<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210419071535 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $sql = "SELECT ox_account.id as account_id,ox_role.id as role_id from ox_account inner join ox_role on ox_account.id=ox_role.account_id where ox_role.name='Admin' and ox_role.app_id is NULL ";
        $result = $this->connection->executeQuery($sql)->fetchAll();
        $this->addSql("DELETE FROM `ox_role_privilege` WHERE `privilege_name` = 'MANAGE_ORGCHART'");
        $this->addSql("DELETE FROM `ox_role_privilege` WHERE `privilege_name` = 'MANAGE_DOCUMENTS'");
        $this->addSql("DELETE FROM `ox_app_registry` where app_id in (select id from ox_app where `name` LIKE 'OrgChart')");
        $this->addSql("DELETE FROM `ox_app_registry` where  app_id in (select id from ox_app where `name` LIKE 'Documents')");
        $jsonObject = `{"autostart":"false","hidden":"false"}`;
        if(count($result)>0){
            foreach($result as $row){
                $this->addSql("INSERT INTO `ox_app_registry` (account_id,app_id,start_options) SELECT ".$row['account_id'].",id ,'".$jsonObject."' from ox_app where `name` LIKE 'OrgChart';");
                $this->addSql("INSERT INTO `ox_app_registry` (account_id,app_id,start_options) SELECT ".$row['account_id'].",id ,'".$jsonObject."' from ox_app where `name` LIKE 'Documents';");
                $this->addSql("INSERT INTO `ox_role_privilege` (`role_id`,`privilege_name`,`account_id`,`permission`,`app_id`) SELECT ".$row['role_id'].",'MANAGE_ORGCHART',".$row['account_id'].",1,id from ox_app where name LIKE 'OrgChart';");
                $this->addSql("INSERT INTO `ox_role_privilege` (`role_id`,`privilege_name`,`account_id`,`permission`,`app_id`) SELECT ".$row['role_id'].",'MANAGE_DOCUMENTS',".$row['account_id'].",1,id from ox_app where name LIKE 'Documents';");
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
