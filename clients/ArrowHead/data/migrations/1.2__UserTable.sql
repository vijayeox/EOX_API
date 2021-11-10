CREATE TABLE IF NOT EXISTS `user` (
    `id` int (11) NOT NULL AUTO_INCREMENT,
    `uuid` varchar(128) NOT NULL,
    `username` varchar(100) NOT NULL,
  	`firstname` varchar(50) DEFAULT NULL,
  	`lastname` varchar(50) DEFAULT NULL,
  	`email` varchar(200) NOT NULL,
  	`role` varchar(200) NOT NULL,
  	`producer_code` varchar(128) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid` (`uuid`)
) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8;