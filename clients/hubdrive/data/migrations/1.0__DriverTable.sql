CREATE TABLE IF NOT EXISTS `driver` (
    `id` int (11) NOT NULL AUTO_INCREMENT,
    `uuid` varchar(128)  NOT NULL,
    `first_name` varchar(50) DEFAULT NULL,
    `middle_name` varchar(50) DEFAULT NULL,
  	`last_name` varchar(50) DEFAULT NULL,
    `date_of_birth` date DEFAULT NULL,
    `ssn` varchar(10) DEFAULT NULL,
  	`license_num` varchar(20) DEFAULT NULL,
  	`has_experience` INT(1) DEFAULT NULL,
  	`driver_type` varchar(20) DEFAULT NULL,
    `paid_by_option` varchar(5) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    UNIQUE KEY `ssn` (`ssn`)
) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `unit` (
    `id` int (11) NOT NULL AUTO_INCREMENT,
    `uuid` varchar(128)  NOT NULL,
    `make` varchar(200) DEFAULT NULL,
  	`year` int(4) DEFAULT NULL,
    `model` varchar(200) DEFAULT NULL,
    `vin` varchar(17) DEFAULT NULL,
  	`garaging_city` varchar(50) DEFAULT NULL,
  	`garaging_address` varchar(200) DEFAULT NULL,
  	`garaging_state` varchar(20) DEFAULT NULL,
    `zip_code` int(5) DEFAULT NULL,
    `registered_owner` varchar(100) DEFAULT NULL,
    `is_leased` int(1) DEFAULT NULL,
    `leased_details` json DEFAULT NULL,
    `has_insured` int(1) DEFAULT NULL,
    `insured_details` json DEFAULT NULL,
    `has_driver` int(1) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`),
    UNIQUE KEY `vin` (`vin`)
) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `driver_unit` (
    `id` int (11) NOT NULL AUTO_INCREMENT,
    `driver_id` int (11),
    `unit_id` int(11),
    PRIMARY KEY (`id`),
    FOREIGN KEY (`driver_id`) REFERENCES `driver`(`id`),
    FOREIGN KEY (`unit_id`) REFERENCES `unit`(`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8;


