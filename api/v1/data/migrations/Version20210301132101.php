<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210301132101 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE ox_group RENAME TO ox_team;");
        $this->addSql("ALTER TABLE ox_announcement_group_mapper RENAME TO ox_announcement_team_mapper;");
        $this->addSql("ALTER TABLE ox_user_group RENAME TO ox_user_team;");
        $this->addSql("ALTER TABLE `ox_user_team` CHANGE COLUMN `group_id` `team_id` int(64) null DEFAULT NULL;");
        $this->addSql("ALTER TABLE `ox_announcement_team_mapper` CHANGE COLUMN `group_id` `team_id` int(64) null DEFAULT NULL;");
        $this->addSql("ALTER TABLE `ox_file_assignee` 
        DROP FOREIGN KEY `ox_file_assignee_ibfk_2`;
        ALTER TABLE `ox_file_assignee` CHANGE COLUMN `group_id` `team_id` INT(32) NULL null DEFAULT NULL;
        ALTER TABLE `ox_file_assignee` 
        ADD CONSTRAINT `ox_file_assignee_ibfk_2`
        FOREIGN KEY (`team_id`)
        REFERENCES `ox_team` (`id`);");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE ox_team RENAME TO ox_group;");
        $this->addSql("ALTER TABLE ox_announcement_team_mapper RENAME TO ox_announcement_group_mapper;");
        $this->addSql("ALTER TABLE ox_user_team RENAME TO ox_user_group;");
        $this->addSql("ALTER TABLE `ox_user_group` CHANGE COLUMN `team_id` `group_id` int(64) null DEFAULT NULL;");
        $this->addSql("ALTER TABLE `ox_announcement_team_mapper` CHANGE COLUMN `team_id` `group_id` int(64) null DEFAULT NULL;");
        $this->addSql("ALTER TABLE `ox_file_assignee` 
        DROP FOREIGN KEY `ox_file_assignee_ibfk_2`;
        ALTER TABLE `ox_file_assignee` CHANGE COLUMN `team_id` `group_id` INT(32) NULL null DEFAULT NULL;
        ALTER TABLE `ox_file_assignee` 
        ADD CONSTRAINT `ox_file_assignee_ibfk_2`
        FOREIGN KEY (`group_id`)
        REFERENCES `ox_team` (`id`);");
    }
}
