<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211221104437 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE ox_user ADD cleared_browser_cache int(11) NOT NULL;");

    }

    public function down(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE ox_user DROP COLUMN `cleared_browser_cache`;");

    }
}
