<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210518041201 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("DELETE S1 FROM ox_account_business_role AS S1 
                       INNER JOIN ox_account_business_role AS S2
                       WHERE S1.id > S2.id AND 
                       S1.`business_role_id` = S2.`business_role_id` AND
                       S1.`account_id` = S2.`account_id`;");

         $this->addSql("ALTER TABLE `ox_account_business_role` ADD UNIQUE `accountBusinessRole` (`account_id`, `business_role_id`);");

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE `ox_account_business_role` DROP INDEX `accountBusinessRole`");

    }
}
