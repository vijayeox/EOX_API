<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220404115842 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Migration steps for Usage Report Metrix';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("CREATE TABLE IF NOT EXISTS  `ox_user_audit_log` (
            `id` INT(32) NOT NULL AUTO_INCREMENT,
            `user_id` varchar(100) NOT NULL,
            `account_id` INT(32) NULL,
            `activity_time` DATETIME NULL,
            `activity` varchar(100) NULL,
            PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("DROP TABLE ox_user_audit_log");
    }
}
