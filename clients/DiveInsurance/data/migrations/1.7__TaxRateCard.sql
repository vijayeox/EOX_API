CREATE TABLE `state_tax` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product` varchar(30) NOT NULL,
  `state` varchar(10) NOT NULL,
  `coverage` varchar(30) NOT NULL,
  `percentage` decimal(10,0) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Alabama', 'liability', '6.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Alaska', 'liability', '3.700', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'American Samoa', 'liability', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Arizona', 'liability', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Arkansas', 'liability', '4.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'California', 'liability', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Colorado', 'liability', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Connecticut', 'liability', '4.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Delaware', 'liability', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'District of Columbia', 'liability', '2.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Federated States of Micronesia', 'liability', '0.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Florida', 'liability', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Georgia', 'liability', '4.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'GUAM', 'liability', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Hawaii', 'liability', '4.680', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Idaho', 'liability', '2.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Illinois', 'liability', '3.625', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Indiana', 'liability', '2.500', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Iowa', 'liability', '1.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Kansas', 'liability', '6.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Kentucky', 'liability', '4.800', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Louisiana', 'liability', '4.850', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Maine', 'liability', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Marshall Islands', 'liability', '0.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Maryland', 'liability', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Massachusetts', 'liability', '4.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Michigan', 'liability', '2.500', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Minnesota', 'liability', '3.040', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Mississippi', 'liability', '7.250', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Missouri', 'liability', '5.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Montana', 'liability', '2.750', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Nebraska', 'liability', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Nevada', 'liability', '3.900', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'New Hampshire', 'liability', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'New Jersey', 'liability', '5.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'New Mexico', 'liability', '3.003', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'New York', 'liability', '3.770', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'North Carolina', 'liability', '5.400', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'North Dakota', 'liability', '1.750', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Northern Mariana Islands', 'liability', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Ohio', 'liability', '5.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Oklahoma', 'liability', '6.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Oregon', 'liability', '2.300', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Palau', 'liability', '0.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Pennsylvania', 'liability', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Puerto Rico', 'liability', '3.700', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Rhode Island', 'liability', '4.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'South Carolina', 'liability', '6.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'South Dakota', 'liability', '2.675', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Tennessee', 'liability', '5.175', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Texas', 'liability', '4.850', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Utah', 'liability', '4.430', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Vermont', 'liability', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Virgin Islands', 'liability', '5.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Virginia', 'liability', '2.250', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Washington', 'liability', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'West Virginia', 'liability', '4.550', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Wisconsin', 'liability', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Wyoming', 'liability', '3.175', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', "Armed Forces - America's", 'liability', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Armed Forces - Other', 'liability', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Armed Forces - Pacific', 'liability', '3.200', '2020-01-01', '2020-12-31');



INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Alabama', 'property', '6.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Alaska', 'property', '3.700', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'American Samoa', 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Arizona', 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Arkansas', 'property', '4.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'California', 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Colorado', 'property', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Connecticut', 'property', '4.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Delaware', 'property', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'District of Columbia', 'property', '2.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Federated States of Micronesia', 'property', '0.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Florida', 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Georgia', 'property', '4.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'GUAM', 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Hawaii', 'property', '4.680', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Idaho', 'property', '2.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Illinois', 'property', '4.625', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Indiana', 'property', '2.500', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Iowa', 'property', '1.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Kansas', 'property', '6.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Kentucky', 'property', '4.800', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Louisiana', 'property', '4.850', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Maine', 'property', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Marshall Islands', 'property', '0.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Maryland', 'property', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Massachusetts', 'property', '4.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Michigan', 'property', '2.500', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Minnesota', 'property', '3.040', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Mississippi', 'property', '7.250', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Missouri', 'property', '5.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Montana', 'property', '5.250', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Nebraska', 'property', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Nevada', 'property', '3.900', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'New Hampshire', 'property', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'New Jersey', 'property', '5.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'New Mexico', 'property', '3.003', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'New York', 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'North Carolina', 'property', '5.400', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'North Dakota', 'property', '1.750', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Northern Mariana Islands', 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Ohio', 'property', '5.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Oklahoma', 'property', '6.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Oregon', 'property', '2.300', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Palau', 'property', '0.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Pennsylvania', 'property', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Puerto Rico', 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Rhode Island', 'property', '4.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'South Carolina', 'property', '6.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'South Dakota', 'property', '3.175', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Tennessee', 'property', '5.175', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Texas', 'property', '4.850', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Utah', 'property', '4.430', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Vermont', 'property', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Virgin Islands', 'property', '5.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Virginia', 'property', '2.250', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Washington', 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'West Virginia', 'property', '4.550', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Wisconsin', 'property', '3.000', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Wyoming', 'property', '3.175', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', "Armed Forces - America's", 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Armed Forces - Other', 'property', '3.200', '2020-01-01', '2020-12-31');
INSERT INTO `state_tax` (`product`, `state`, `coverage`, `percentage`, `start_date`, `end_date`) VALUES ('Dive Store', 'Armed Forces - Pacific', 'property', '3.200', '2020-01-01', '2020-12-31');