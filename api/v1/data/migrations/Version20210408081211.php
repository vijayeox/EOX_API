<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210408081211 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql("ALTER TABLE `ox_kra` ADD COLUMN `name` VARCHAR(100) NOT NULL");
        $this->addSql("ALTER TABLE `ox_kra` ADD COLUMN `account_id` INT(11) NULL");
        $this->addSql("ALTER TABLE `ox_kra` ADD COLUMN `business_role_id` INT(11) NULL");
        $this->addSql("ALTER TABLE `ox_kra` ADD COLUMN `user_id` INT(11) NULL");
        $this->addSql("ALTER TABLE `ox_kra` ADD COLUMN `team_id` INT(11) NULL");
        $this->addSql('ALTER TABLE `ox_kra` ADD COLUMN `date_modified`  DATETIME;');
        $this->addSql('ALTER TABLE `ox_kra` ADD COLUMN `date_created`  DATETIME;');
        $this->addSql('ALTER TABLE `ox_kra` ADD COLUMN `modified_by` INT(32);');
        $this->addSql('ALTER TABLE `ox_kra` ADD COLUMN `created_by` INT(32);');
        $this->addSql("ALTER TABLE `ox_kra` ADD COLUMN `status` varchar(255) NULL");
        $this->addSql("ALTER TABLE `ox_kra` ADD CONSTRAINT fk_kra_modified_by FOREIGN KEY (modified_by) REFERENCES ox_user(id)");
        $this->addSql("ALTER TABLE `ox_kra` ADD CONSTRAINT fk_kra_created_by FOREIGN KEY (created_by) REFERENCES ox_user(id)");
        $this->addSql("ALTER TABLE `ox_kra` ADD CONSTRAINT fk_kra_account_id FOREIGN KEY (account_id) REFERENCES ox_account(id)");
        $this->addSql("ALTER TABLE `ox_kra` ADD CONSTRAINT fk_kra_business_role_id FOREIGN KEY (business_role_id) REFERENCES ox_business_role(id)");
        $this->addSql("ALTER TABLE `ox_kra` ADD CONSTRAINT fk_kra_user_id FOREIGN KEY (user_id) REFERENCES ox_user(id)");
        $this->addSql("ALTER TABLE `ox_kra` ADD CONSTRAINT fk_kra_team_id FOREIGN KEY (team_id) REFERENCES ox_team(id)");
        $this->addSql("INSERT INTO ox_privilege (name,permission_allowed,app_id) values ('MANAGE_KRA',15,1);");
        $database = $this->connection->getDatabase();
        $sql = "SELECT ox_account.id as account_id,ox_role.id as role_id from ox_account inner join ox_role on ox_account.id=ox_role.account_id where ox_role.name='Admin' and ox_role.app_id is NULL ";
        $result = $this->connection->executeQuery($sql)->fetchAll();
        if(count($result)>0){
            foreach($result as $row){
                $this->addSql("INSERT INTO `ox_role_privilege` (`role_id`,`privilege_name`,`permission`,`account_id`,`app_id`) SELECT ".$row['role_id'].",'MANAGE_KRA',15,".$row['account_id'].",id from ox_app WHERE name LIKE 'Admin';");
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE ox_kra DROP FOREIGN KEY fk_kra_team_id");
        $this->addSql("ALTER TABLE `ox_kra` DROP COLUMN `team_id`");
        $this->addSql("ALTER TABLE `ox_kra` DROP COLUMN `name`");
        $this->addSql("ALTER TABLE ox_kra DROP FOREIGN KEY fk_kra_account_id");
        $this->addSql("ALTER TABLE `ox_kra` DROP COLUMN `account_id`");
        $this->addSql("ALTER TABLE ox_kra DROP FOREIGN KEY fk_kra_business_role_id");
        $this->addSql("ALTER TABLE `ox_kra` DROP COLUMN `business_role_id`");
        $this->addSql("ALTER TABLE ox_kra DROP FOREIGN KEY fk_kra_user_id");
        $this->addSql("ALTER TABLE `ox_kra` DROP COLUMN `user_id`");
        $this->addSql("ALTER TABLE ox_kra DROP FOREIGN KEY fk_kra_modified_by");
        $this->addSql("ALTER TABLE `ox_kra` DROP COLUMN `modified_by`");
        $this->addSql("ALTER TABLE `ox_kra` DROP COLUMN `created_by`");
        $this->addSql("ALTER TABLE `ox_kra` DROP COLUMN `date_modified`");
        $this->addSql("ALTER TABLE `ox_kra` DROP COLUMN `date_created`");
        $this->addSql("ALTER TABLE `ox_kra` DROP COLUMN `status`");
        $this->addSql("DELETE FROM `ox_privilege` WHERE `name` = 'MANAGE_KRA'");
        $this->addSql("DELETE FROM `ox_role_privilege` WHERE `privilege_name` = 'MANAGE_KRA'");
    }
}
