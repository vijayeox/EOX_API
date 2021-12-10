<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211122043205 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE ox_file_audit_log MODIFY `data` LONGTEXT;");

    }

    public function down(Schema $schema) : void
    {
       $this->addSql("ALTER TABLE ox_file_audit_log MODIFY `data` TEXT;");

    }
}
