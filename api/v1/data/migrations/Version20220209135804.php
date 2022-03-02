<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220209135804 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE ox_app_registry MODIFY `start_options` JSON;");
        $this->addSql("UPDATE ox_app_registry SET start_options = NULL WHERE start_options = 'json_object';");
        $this->addSql("UPDATE ox_app_registry SET start_options = NULL WHERE start_options = '';");

    }

    public function down(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE ox_app_registry MODIFY `start_options` JSON;");

    }
}
