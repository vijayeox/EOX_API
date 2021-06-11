<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210601205419 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Create Profile Table';
    }

    public function up(Schema $schema) : void
    {
        $sql = "CREATE TABLE `ox_profile` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `uuid` varchar(45) DEFAULT NULL,
            `name` varchar(100) NOT NULL,
            `dashboard_uuid` varchar(45) DEFAULT NULL,
            `html` text,
            `type` varchar(45) DEFAULT NULL,
            `role_id` int(11) DEFAULT NULL,
            `precedence` int(11) DEFAULT NULL,
            `date_created` datetime DEFAULT NULL,
            `date_modified` datetime DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            `modified_by` varchar(45) DEFAULT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8";
        $this->addSql($sql);  

    }

    public function down(Schema $schema) : void
    {
        $this->addSql("DROP TABLE ox_profile;");

    }
}
