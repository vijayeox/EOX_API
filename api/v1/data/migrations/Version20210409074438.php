<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210409074438 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE `ox_file` ADD COLUMN `is_snoozed` INT(2) NULL");

    }

    public function down(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE `ox_file` DROP COLUMN `is_snoozed`");

    }
}
