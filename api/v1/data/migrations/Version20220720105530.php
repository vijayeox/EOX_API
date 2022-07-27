<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220720105530 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Add timesheet manager role and privilege to access manager report in 2.x';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO `ox_privilege` (`name`,`permission_allowed`,`app_id`) SELECT 'MANAGE_TIMESHEET_MANAGER',1,id from ox_app where name LIKE 'Timesheet';");

        // $database = $this->connection->getDatabase();
        $sql = "SELECT ox_account.id as account_id,ox_role.id as role_id from ox_account inner join ox_role on ox_account.id=ox_role.account_id where (ox_role.name='ADMIN' or ox_role.name='MANAGER') and ox_role.app_id is NULL;";
        $result = $this->connection->executeQuery($sql)->fetchAll();
        if (count($result) > 0){
            foreach($result as $row){
                $this->addSql("INSERT INTO `ox_role_privilege` (`role_id`,`privilege_name`,`permission`,`account_id`,`app_id`) SELECT ".$row['role_id'].",'MANAGE_TIMESHEET_MANAGER',1,".$row['account_id'].",id from ox_app WHERE name LIKE 'Timesheet';");
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("DELETE FROM `ox_privilege` WHERE `name` = 'MANAGE_TIMESHEET_MANAGER'");
        $this->addSql("DELETE FROM `ox_role_privilege` WHERE `privilege_name` = 'MANAGE_TIMESHEET_MANAGER'");

    }
}
