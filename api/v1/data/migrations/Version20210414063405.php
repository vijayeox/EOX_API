<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210414063405 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Update KRA missing relationships';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE `ox_kra` ADD CONSTRAINT fk_kra_target_id FOREIGN KEY (target_id) REFERENCES ox_target(id)");
        $this->addSql("ALTER TABLE `ox_kra` ADD CONSTRAINT fk_kra_query_id FOREIGN KEY (query_id) REFERENCES ox_query(id)");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE ox_kra DROP FOREIGN KEY fk_kra_target_id");
        $this->addSql("ALTER TABLE ox_kra DROP FOREIGN KEY fk_kra_query_id");
    }
}
