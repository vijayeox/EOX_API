<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220404071359 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Enhancements to the rate tables';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE `ox_rate_condition`
        ADD COLUMN `sequence_id` INT(11) NOT NULL AFTER `value`");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE `ox_rate_condition` DROP COLUMN `sequence_id`");
    }
}
