<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220204091355 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $sql = "SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = 'oxzionapi' AND REFERENCED_TABLE_NAME = 'ox_account_business_role'";
        $result = $this->connection->executeQuery($sql)->fetchAll();
        if (count($result) > 0) {
            $this->addSql("ALTER TABLE `ox_account_offering` DROP FOREIGN KEY ox_account_offering_ibfk_1");
        }
        $this->addSql("ALTER TABLE `ox_account_offering` ADD CONSTRAINT `ox_account_offering_ibfk_1` FOREIGN KEY ( `account_business_role_id` )
        REFERENCES `ox_account_business_role`( `id` ) ON DELETE CASCADE");
    }

    public function down(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE `ox_account_offering` DROP FOREIGN KEY ox_account_offering_ibfk_1");

    }
}
