<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210309042818 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `ox_comment` ADD COLUMN `attachments` JSON');
        $this->addSql('ALTER TABLE `ox_app_entity` ADD COLUMN `generic_attachment_config` JSON');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE `ox_comment` DROP COLUMN `attachments`");
        $this->addSql("ALTER TABLE `ox_app_entity` DROP COLUMN `generic_attachment_config`");

    }
}
