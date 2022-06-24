<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220412043700 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Migration steps for Usage Report Metrix';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE ox_user_audit_log ADD jwtToken TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL;");

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE ox_user_audit_log DROP column jwtToken;");

    }
}
