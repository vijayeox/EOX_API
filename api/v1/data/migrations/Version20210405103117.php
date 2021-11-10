<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210405103117 extends AbstractMigration
{

  public function getDescription() : string
  {
    return '';
  }

  public function up(Schema $schema) : void
  {
    $this->addSql("CREATE TABLE `ox_user_announcement_mapper` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `user_id` INT NOT NULL,
      `announcement_id` INT NOT NULL,
      `view` TINYINT NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      INDEX `user_id_idx` (`user_id` ASC),
      INDEX `announcement_id_idx` (`announcement_id` ASC),
      CONSTRAINT `user_id`
      FOREIGN KEY (`user_id`)
      REFERENCES `ox_user` (`id`)
      ON DELETE CASCADE
      ON UPDATE NO ACTION,
      CONSTRAINT `announcement_id`
      FOREIGN KEY (`announcement_id`)
      REFERENCES `ox_announcement` (`id`)
      ON DELETE CASCADE
      ON UPDATE NO ACTION)
      ENGINE = InnoDB
      DEFAULT CHARACTER SET = latin1;");
  }

  public function down(Schema $schema) : void
  {
    $this->addSql("DROP TABLE ox_user_announcement_mapper");

  }

}
