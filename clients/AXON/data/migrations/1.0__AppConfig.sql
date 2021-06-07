CREATE TABLE IF NOT EXISTS `applicationConfig` (
    `id` int (11) NOT NULL AUTO_INCREMENT,
    `type` varchar (50) NOT NULL,
    `value` varchar (100) NOT NULL, 
    PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8;


INSERT INTO
    `applicationConfig` (`type`, `value`)
VALUES
    (
        'excelMapperURL',
        'http://54.161.224.59:5000/api/FileUpload'
    );


INSERT INTO
    `applicationConfig` (`type`, `value`)
VALUES
    (
        'complianceGroupMail',
        'compliancegroup@axon'
    );