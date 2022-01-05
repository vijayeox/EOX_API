<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211223113500 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // Changing the Data type of Start options to Json
        $this->addSql("ALTER TABLE ox_app MODIFY `start_options` JSON;");

    }

    public function down(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE ox_app MODIFY `start_options` VARCHAR(255);");

    }
}
