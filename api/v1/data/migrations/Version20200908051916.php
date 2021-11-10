<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200908051916 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Rename Organization Table to Account and related changes';
    }

    public function up(Schema $schema) : void
    {   
        $database = $this->connection->getDatabase();
        $sql = "SELECT count(CONSTRAINT_NAME) as count
                    from information_schema.TABLE_CONSTRAINTS 
                    where table_schema =  '$database'
                      and table_name = 'ox_user_role'
                      and CONSTRAINT_TYPE = 'FOREIGN KEY'
                      and CONSTRAINT_NAME = 'app_user'";
        $result = $this->connection->fetchArray($sql);

        $this->addSql("RENAME TABLE ox_organization TO ox_account,
                                    ox_organization_profile TO ox_organization,
                                    ox_user_org TO ox_account_user,
                                    ox_user_profile TO ox_person,
                                    ox_org_business_role TO ox_account_business_role,
                                    ox_org_offering TO ox_account_offering,
                                    ox_user_manager TO ox_employee_manager");
        //Organization -> Account
        $this->addSql("ALTER TABLE ox_account CHANGE `org_profile_id` `organization_id` INT(32)");

        //user_org -> account_user
        $this->addSql("ALTER TABLE ox_account_user ADD `id` int(32) NOT NULL 
                        AUTO_INCREMENT primary key FIRST");
        $this->addSql("ALTER TABLE ox_account_user CHANGE `org_id` `account_id` INT(32)");
        //role
        $this->addSql("ALTER TABLE ox_role CHANGE `org_id` `account_id` INT(32)");
        $this->addSql("ALTER TABLE ox_role_privilege CHANGE `org_id` `account_id` INT(32)");
        //user
        $this->addSql("ALTER TABLE `ox_user` 
        DROP FOREIGN KEY `ox_user_ibfk_2`;
        ALTER TABLE ox_user CHANGE `user_profile_id` `person_id` INT(32);
        ALTER TABLE `ox_user` 
        ADD CONSTRAINT `ox_user_ibfk_2`
        FOREIGN KEY (`person_id`)
        REFERENCES `ox_person` (`id`);");
        $this->addSql("ALTER TABLE `ox_user` 
        DROP FOREIGN KEY `ox_user_ibfk_1`;
        ALTER TABLE ox_user CHANGE `orgid` `account_id` INT(32);
        ALTER TABLE `ox_user` 
        ADD CONSTRAINT `ox_user_ibfk_1`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        $this->addSql("DROP TRIGGER IF EXISTS `before_insert_ox_user`");
        $this->addSql("CREATE TRIGGER before_insert_ox_user BEFORE INSERT ON ox_user FOR EACH ROW SET NEW.name = (SELECT CONCAT(firstname, ' ', lastname)from ox_person where id=NEW.person_id); IF(NEW.uuid IS NULL OR NEW.uuid = '') THEN SET NEW.uuid = uuid(); END IF;");

        //user_role
        $this->addSql("ALTER TABLE ox_user_role ADD COLUMN `account_user_id` INT(32),
                        ADD CONSTRAINT FOREIGN KEY (`account_user_id`) REFERENCES ox_account_user(`id`)");
        $this->addSql("UPDATE ox_user_role 
                        inner join ox_user u on ox_user_role.user_id = u.id
                        inner join ox_account_user au on u.id=au.user_id and 
                                                         u.account_id = au.account_id
                        SET ox_user_role.account_user_id = au.id");
        
        if ($result && $result[0]) {
            $this->addSql("ALTER TABLE ox_user_role DROP FOREIGN KEY app_user");
        }
        $this->addSql("ALTER TABLE ox_user_role DROP INDEX uniq_id");
        $this->addSql("ALTER TABLE ox_user_role DROP COLUMN `user_id`");
        $this->addSql("ALTER TABLE ox_user_role ADD UNIQUE INDEX uniq_account_user_role_idx (role_id, account_user_id)");
        //user_profile -> person

        $this->addSql("ALTER TABLE ox_person DROP FOREIGN KEY ox_person_ibfk_2,
                                    DROP COLUMN org_id");
        //user_session
        $this->addSql("ALTER TABLE ox_user_session CHANGE `org_id` `account_id` INT(32)");
        //activity_instance
        $this->addSql("ALTER TABLE `ox_activity_instance` 
        DROP FOREIGN KEY `activity_instance_references_org`;
        ALTER TABLE ox_activity_instance CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_activity_instance` 
        ADD CONSTRAINT `activity_instance_references_org`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);
        ");
        //workflow_instance
        $this->addSql("ALTER TABLE `ox_workflow_instance` 
        DROP FOREIGN KEY `ox_workflow_instance_ibfk_2`,
        DROP FOREIGN KEY `workflow_instance_references_org`;
        ALTER TABLE ox_workflow_instance CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_workflow_instance` 
        ADD CONSTRAINT `ox_workflow_instance_ibfk_2`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`),
        ADD CONSTRAINT `workflow_instance_references_org`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //alert
        $this->addSql("ALTER TABLE ox_alert CHANGE `org_id` `account_id` INT(32)");
        //announcement
        $this->addSql("ALTER TABLE ox_announcement CHANGE `org_id` `account_id` INT(32)");
        //app_category
        $this->addSql("ALTER TABLE ox_app_category CHANGE `org_id` `account_id` INT(32)");
        //app_registry
        $this->addSql("ALTER TABLE ox_app_registry CHANGE `org_id` `account_id` INT(32)");
        //attachment
        $this->addSql("ALTER TABLE ox_attachment CHANGE `org_id` `account_id` INT(32)");
        //comment
        $this->addSql("ALTER TABLE `ox_comment` 
        DROP FOREIGN KEY `fk_comment_organization_id`;
        ALTER TABLE ox_comment CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_comment` 
        ADD CONSTRAINT `fk_comment_organization_id`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);
        ");
        //employee
        $this->addSql("ALTER TABLE ox_employee DROP FOREIGN KEY ox_employee_ibfk_2, 
                                                DROP COLUMN `org_id`");
        $this->addSql("ALTER TABLE `ox_employee` 
        DROP FOREIGN KEY `ox_employee_ibfk_3`;
        ALTER TABLE ox_employee CHANGE `org_profile_id` `org_id` INT(32);
        ALTER TABLE `ox_employee` 
        ADD CONSTRAINT `ox_employee_ibfk_3`
        FOREIGN KEY (`org_id`)
        REFERENCES `ox_organization` (`id`);");
        $this->addSql("ALTER TABLE `ox_employee` 
        DROP FOREIGN KEY `ox_employee_ibfk_1`;
        ALTER TABLE ox_employee CHANGE `user_profile_id` `person_id` INT(32);
        ALTER TABLE `ox_employee` 
        ADD CONSTRAINT `ox_employee_ibfk_1`
        FOREIGN KEY (`person_id`)
        REFERENCES `ox_person` (`id`);");
        $this->addSql("ALTER TABLE ox_employee ADD COLUMN `manager_id` INT(32)");
        $this->addSql("ALTER TABLE ox_employee ADD CONSTRAINT FOREIGN KEY (`manager_id`) REFERENCES ox_employee(`id`)");
        $this->addSql("UPDATE ox_employee em 
                        INNER JOIN ox_user u on em.managerid = u.id
                        INNER JOIN ox_employee e on e.person_id = u.person_id
                        SET em.manager_id = e.id");
        $this->addSql("ALTER TABLE ox_employee DROP FOREIGN KEY ox_employee_ibfk_4, 
                                                DROP COLUMN `managerid`");
        //employee_manager
        $this->addSql("ALTER TABLE ox_employee_manager ADD COLUMN employee_id INT(32)");
        $this->addSql("ALTER TABLE ox_employee_manager ADD CONSTRAINT FOREIGN KEY (`employee_id`) REFERENCES ox_employee(`id`)");
        $this->addSql("ALTER TABLE `ox_employee_manager` 
        DROP FOREIGN KEY `ox_employee_manager_ibfk_2`;
        ALTER TABLE ox_employee_manager CHANGE `manager_id` `old_manager_id` INT(32);
        ALTER TABLE `ox_employee_manager` 
        ADD CONSTRAINT `ox_employee_manager_ibfk_2`
        FOREIGN KEY (`old_manager_id`)
        REFERENCES `ox_user` (`id`);");
        $this->addSql("ALTER TABLE ox_employee_manager ADD COLUMN manager_id INT(32)");
        $this->addSql("ALTER TABLE ox_employee_manager ADD CONSTRAINT FOREIGN KEY (`manager_id`) REFERENCES ox_employee(`id`)");
        
        $this->addSql("UPDATE ox_employee_manager em 
                        INNER JOIN ox_user u on em.user_id = u.id
                        INNER JOIN ox_employee e on e.person_id = u.person_id
                        SET em.employee_id = e.id");
        $this->addSql("UPDATE ox_employee_manager em 
                        INNER JOIN ox_user u on em.old_manager_id = u.id
                        INNER JOIN ox_employee e on e.person_id = u.person_id
                        SET em.manager_id = e.id");
        $this->addSql("ALTER TABLE ox_employee_manager DROP FOREIGN KEY ox_employee_manager_ibfk_1, 
                                                DROP COLUMN `user_id`");
        $this->addSql("ALTER TABLE ox_employee_manager DROP FOREIGN KEY ox_employee_manager_ibfk_2, 
                                                DROP COLUMN `old_manager_id`");
        //file
        $this->addSql("DROP TRIGGER IF EXISTS `ox_file_insert`");
        $this->addSql("DROP TRIGGER IF EXISTS `ox_file_update`");
        $this->addSql("DROP TRIGGER IF EXISTS `ox_file_delete`");
        $this->addSql("ALTER TABLE `ox_file` 
        DROP FOREIGN KEY `FK_orgid`;
        ALTER TABLE ox_file CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_file` 
        ADD CONSTRAINT `FK_orgid`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //file_audit_log
        $this->addSql("ALTER TABLE ox_file_audit_log CHANGE `org_id` `account_id` INT(32)");
        $this->setupFileAuditLogTriggers();
        //file_document
        $this->addSql("ALTER TABLE `ox_file_document` 
        DROP FOREIGN KEY `ox_file_document_ibfk_3`;
        ALTER TABLE ox_file_document CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_file_document` 
        ADD CONSTRAINT `ox_file_document_ibfk_3`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //file_participant
        $this->addSql("ALTER TABLE `ox_file_participant` 
        DROP FOREIGN KEY `ox_file_participant_ibfk_2`;
        ALTER TABLE ox_file_participant CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_file_participant` 
        ADD CONSTRAINT `ox_file_participant_ibfk_2`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //file_attachment
        $this->addSql("ALTER TABLE ox_file_attachment CHANGE `org_id` `account_id` INT(32)");
        //file_attribute
        $this->addSql("ALTER TABLE `ox_file_attribute` 
        DROP FOREIGN KEY `file_attribute_references_org`;
        ALTER TABLE ox_file_attribute CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_file_attribute` 
        ADD CONSTRAINT `file_attribute_references_org`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);
        ");
        //indexed_file_attribute
        $this->addSql("ALTER TABLE `ox_indexed_file_attribute` 
        DROP FOREIGN KEY `indexed_file_attribute_references_org`;
        ALTER TABLE ox_indexed_file_attribute CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_indexed_file_attribute` 
        ADD CONSTRAINT `indexed_file_attribute_references_org`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //group
        $this->addSql("ALTER TABLE ox_group CHANGE `org_id` `account_id` INT(32)");
        //job
        $this->addSql("ALTER TABLE `ox_job` 
        DROP FOREIGN KEY `FK_job_orgid`;
        ALTER TABLE ox_job CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_job` 
        ADD CONSTRAINT `FK_job_orgid`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //metafield
        $this->addSql("DROP TABLE ox_metafield");
        //mlet
        $this->addSql("DROP TABLE ox_mlet");
        //splashpage
        $this->addSql("DROP TABLE ox_splashpage");
        //org_business_role -> account_business_role
        $this->addSql("ALTER TABLE `ox_account_business_role` 
        DROP FOREIGN KEY `ox_account_business_role_ibfk_2`;
        ALTER TABLE ox_account_business_role CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_account_business_role` 
        ADD CONSTRAINT `ox_account_business_role_ibfk_2`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //org_offering -> account_offering
        $this->addSql("ALTER TABLE `ox_account_offering` 
        DROP FOREIGN KEY `ox_account_offering_ibfk_1`;
        ALTER TABLE ox_account_offering CHANGE `org_business_role_id` `account_business_role_id` INT(32);
        ALTER TABLE `ox_account_offering` 
        ADD CONSTRAINT `ox_account_offering_ibfk_1`
        FOREIGN KEY (`account_business_role_id`)
        REFERENCES `ox_account_business_role` (`id`);");
        //payment
        $this->addSql("ALTER TABLE ox_payment CHANGE `org_id` `account_id` INT(32)");
        //payment
        $this->addSql("ALTER TABLE ox_project CHANGE `org_id` `account_id` INT(32)");
        //dashboard
        $this->addSql("ALTER TABLE `ox_dashboard` 
        DROP FOREIGN KEY `org_reference_dashboard`;
        ALTER TABLE ox_dashboard CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_dashboard` 
        ADD CONSTRAINT `org_reference_dashboard`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //datasource
        $this->addSql("ALTER TABLE `ox_datasource` 
        DROP FOREIGN KEY `org_reference_datasource`;
        ALTER TABLE ox_datasource CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_datasource` 
        ADD CONSTRAINT `org_reference_datasource`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //query
        $this->addSql("ALTER TABLE `ox_query` 
        DROP FOREIGN KEY `org_reference_query`;
        ALTER TABLE ox_query CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_query` 
        ADD CONSTRAINT `org_reference_query`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //target
        $this->addSql("ALTER TABLE ox_target CHANGE `org_id` `account_id` INT(32)");
        //visualization
        $this->addSql("ALTER TABLE `ox_visualization` 
        DROP FOREIGN KEY `org_reference_visualization`;
        ALTER TABLE ox_visualization CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_visualization` 
        ADD CONSTRAINT `org_reference_visualization`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //subscriber
        $this->addSql("ALTER TABLE `ox_subscriber` 
        DROP FOREIGN KEY `fk_subscriber_organization_id`;
        ALTER TABLE ox_subscriber CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_subscriber` 
        ADD CONSTRAINT `fk_subscriber_organization_id`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //widget
        $this->addSql("ALTER TABLE `ox_widget` 
        DROP FOREIGN KEY `org_reference_widget`;
        ALTER TABLE ox_widget CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_widget` 
        ADD CONSTRAINT `org_reference_widget`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //wf_user_identifier
        $this->addSql("ALTER TABLE `ox_wf_user_identifier` 
        DROP FOREIGN KEY `fk_user_identifier_org`;
        ALTER TABLE ox_wf_user_identifier CHANGE `org_id` `account_id` INT(32);
        ALTER TABLE `ox_wf_user_identifier` 
        ADD CONSTRAINT `fk_user_identifier_org`
        FOREIGN KEY (`account_id`)
        REFERENCES `ox_account` (`id`);");
        //update privilege names
        $this->addSql("UPDATE ox_privilege set name = 'MANAGE_ACCOUNT' where name = 'MANAGE_ORGANIZATION'");
        $this->addSql("UPDATE ox_role_privilege set privilege_name = 'MANAGE_ACCOUNT' where privilege_name = 'MANAGE_ORGANIZATION'");
        $this->addSql("UPDATE ox_privilege set name = 'MANAGE_MYACCOUNT' where name = 'MANAGE_MYORG'");
        $this->addSql("UPDATE ox_role_privilege set privilege_name = 'MANAGE_MYACCOUNT' where privilege_name = 'MANAGE_MYORG'");
        $this->addSql("UPDATE ox_role set default_role = 1 where name = 'EMPLOYEE'");
        $this->addSql("UPDATE ox_address set city = ' ', state = ' ', zip = ' ' where city is null");
    }

    private function setupFileAuditLogTriggers(){
        $this->addSql("CREATE TRIGGER `ox_file_insert` AFTER INSERT ON `ox_file` FOR EACH ROW INSERT INTO `ox_file_audit_log` (`id`, `action`, `uuid`, `account_id`, `form_id`, `data`, `created_by`,`modified_by`, `date_created`, `date_modified`,`assoc_id`,`is_active`,`entity_id`,`last_workflow_instance_id`,`start_date`,`end_date`,`status`,`version`) VALUES (new.`id`, 'create', new.`uuid`, new.`account_id`, new.`form_id`, new.`data`, new.`created_by`, new.`modified_by`, new.`date_created`, new.`date_modified`,new.`assoc_id`,new.`is_active`,new.`entity_id`,new.`last_workflow_instance_id`,new.`start_date`,new.`end_date`,new.`status`,new.`version`);");
        $this->addSql("CREATE TRIGGER `ox_file_update` AFTER UPDATE on `ox_file` FOR EACH ROW INSERT INTO `ox_file_audit_log` (`id`, `action`, `uuid`, `account_id`, `form_id`, `data`, `created_by`,`modified_by`, `date_created`, `date_modified`,`assoc_id`,`is_active`,`entity_id`,`last_workflow_instance_id`,`start_date`,`end_date`,`status`,`version`) VALUES (new.`id`, 'update', new.`uuid`, new.`account_id`, new.`form_id`, new.`data`, new.`created_by`, new.`modified_by`, new.`date_created`, new.`date_modified`,new.`assoc_id`,new.`is_active`,new.`entity_id`,new.`last_workflow_instance_id`,new.`start_date`,new.`end_date`,new.`status`,new.`version`);");
        $this->addSql("CREATE TRIGGER `ox_file_delete` AFTER DELETE ON ox_file FOR EACH ROW INSERT INTO `ox_file_audit_log` (`id`, `action`, `uuid`, `account_id`, `form_id`, `data`, `created_by`,`modified_by`, `date_created`, `date_modified`,`assoc_id`,`is_active`,`entity_id`,`last_workflow_instance_id`,`start_date`,`end_date`,`status`,`version`) VALUES (old.`id`, 'delete', old.`uuid`, old.`account_id`, old.`form_id`, old.`data`, old.`created_by`, old.`modified_by`, old.`date_created`, old.`date_modified`,old.`assoc_id`,old.`is_active`,old.`entity_id`,old.`last_workflow_instance_id`,old.`start_date`,old.`end_date`,old.`status`,old.`version`);");
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
