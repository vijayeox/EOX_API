<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210525070247 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE ox_business_relationship ADD COLUMN id INT NOT NULL AUTO_INCREMENT PRIMARY KEY;");
        $this->addSql("DELETE S1 FROM ox_business_relationship AS S1 
                       INNER JOIN ox_business_relationship AS S2
                       WHERE S1.id > S2.id AND 
                       S1.`seller_account_business_role_id` = S2.`seller_account_business_role_id` AND
                       S1.`buyer_account_business_role_id` = S2.`buyer_account_business_role_id`;");
        $this->addSql("ALTER TABLE ox_business_relationship DROP column id;");
        
        $this->addSql("ALTER TABLE `ox_business_relationship` ADD UNIQUE `sellerBuyerAccountBusinessRole` (`buyer_account_business_role_id`, `seller_account_business_role_id`);");

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE `ox_business_relationship` DROP INDEX `sellerBuyerAccountBusinessRole`");

    }
}
