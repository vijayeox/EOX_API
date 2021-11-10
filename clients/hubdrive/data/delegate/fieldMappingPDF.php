
<?php
return array(
    "driverEmploymentApplication" => [
        "text" =>
        [
            //Driver Information
            'firstName' => 'firstName',
            'middleName' => 'middleName',
            'lastName' => 'lastName',
            'ssn' => 'ssn',
            'phoneNumber' => 'phoneNumber',
            'cellNumber' => 'cellNumber',
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'zipCode' => 'zipCode',
            'numberOfYearsAtPresentAddress' => 'numberOfYearsAtPresentAddress',
            
            //Employment Desired
            'positionDesired' => 'positionDesired',
            'salaryDesired' => 'salaryDesired',

            'otherQualifications' => 'otherQualifications',

            'applicantsName' => 'applicantsName'

        ],
        "addAnother" =>
        [
            
            [
                'companyName' => 'companyName',
                'phoneNumber2' => 'phoneNumber2',
                'address1' => 'address1',
                'city1' => 'city1',
                'state1' => 'state1',
                'jobTitleStart' => 'jobTitleStart',
                'jobTitleTitle' => 'jobTitleTitle',
                'supervisorName' => 'supervisorName',
                'supervisorTitle' => 'supervisorTitle',
                'descriptionOfJobDuties' => 'descriptionOfJobDuties',
                'reasonForLeaving' => 'reasonForLeaving',
                'zipCode1' => 'zipCode1',
                'basePay' => 'basePay',
                'baseRateOfPayFinal' => 'baseRateOfPayFinal',
                "radioYN" =>
                [
                    'radio1' => [
                        "fieldname" => 'curEmp1',
                        "options" => ["yes" => "Yes", "no" => "No"]
                    ],
        
                    'radio2' => [
                        "fieldname" => 'curEmp2',
                        "options" => ["yes" => "Yes", "no" => "No"]
                    ],
                ]
       
            ],

            [
                'companyName' => 'companyName2',
                'phoneNumber2' => 'phoneNumber3',
                'address1' => 'address2',
                'city1' => 'city2',
                'state1' => 'state2',
                'jobTitleStart' => 'jobTitleStart1',
                'jobTitleTitle' => 'jobTitleTitle1',
                'supervisorName' => 'supervisorName1',
                'supervisorTitle' => 'supervisorTitle1',
                'descriptionOfJobDuties' => 'descriptionOfJobDuties1',
                'reasonForLeaving' => 'reasonForLeaving1',
                'zipCode1' => 'zipCode2',
                'basePay' => 'basePay1',
                'baseRateOfPayFinal' => 'baseRateOfPayFinal1',
                "radioYN" =>
                [
                    'radio1' => [
                        "fieldname" => 'secEmp1',
                        "options" => ["yes" => "Yes", "no" => "No"]
                    ],
        
                    'radio2' => [
                        "fieldname" => 'secEmp2',
                        "options" => ["yes" => "Yes", "no" => "No"]
                    ],
                ]
            ],

            [
                'companyName' => 'companyName3',
                'phoneNumber2' => 'phoneNumber4',
                'address1' => 'address3',
                'city1' => 'city3',
                'state1' => 'state3',
                'jobTitleStart' => 'jobTitleStart2',
                'jobTitleTitle' => 'jobTitleTitle2',
                'supervisorName' => 'supervisorName2',
                'supervisorTitle' => 'supervisorTitle2',
                'descriptionOfJobDuties' => 'descriptionOfJobDuties2',
                'reasonForLeaving' => 'reasonForLeaving2',
                'zipCode1' => 'zipCode3',
                'basePay' => 'basePay2',
                'baseRateOfPayFinal' => 'baseRateOfPayFinal2',
                "radioYN" =>
                [
                    'radio1' => [
                        "fieldname" => 'thiEmp1',
                        "options" => ["yes" => "Yes", "no" => "No"]
                    ],
        
                    'radio2' => [
                        "fieldname" => 'thiEmp2',
                        "options" => ["yes" => "Yes", "no" => "No"]
                    ],
        
                ]
            ],

            
           
        ],
        "dataGrid1" =>
        [
            [
                'date' => 'date',
                'natureOfAccident' => 'natureOfAccident',
                'fatalities' => 'fatalities',
                'injuries' => 'injuries'
            ],

			[
                'date' => 'date1',
                'natureOfAccident' => 'natureOfAccident1',
                'fatalities' => 'fatalities1',
                'injuries' => 'injuries1'
            ],
			
			[
                'date' => 'date2',
                'natureOfAccident' => 'natureOfAccident2',
                'fatalities' => 'fatalities2',
                'injuries' => 'injuries2'
            ]
        ],
        "dataGrid2" =>
        [
            [
                'location' => 'location',
                'date1' => 'date3',
                'charge' => 'charge',
                'penalty' => 'penalty'
            ],

            [
                'location' => 'location1',
                'date1' => 'date4',
                'charge' => 'charge1',
                'penalty' => 'penalty1'
            ],

            [			
                'location' => 'location2',
                'date1' => 'date5',
                'charge' => 'charge2',
                'penalty' => 'penalty2'
            ],
			
			[
                'location' => 'location3',
                'date1' => 'date6',
                'charge' => 'charge3',
                'penalty' => 'penalty3'
            ]

        ],
        "dataGridDrivingExperience"=>
        [
			
            [
                "dateFrom" => "dateFrom",
                "dateTo" => "dateTo", 
                'classOfEquipment' => 'classOfEquipment',
                'typeOfEquipment' => 'typeOfEquipment',
                'approximateNumberOfMilesTotal' => 'approximateNumberOfMilesTotal'
            ],

            [
                "dateFrom" => "dateFrom1",
                "dateTo" => "dateTo1",
                'classOfEquipment' => 'classOfEquipment1',
                'typeOfEquipment' => 'typeOfEquipment1',
                'approximateNumberOfMilesTotal' => 'approximateNumberOfMilesTotal1'
            ],
			
			[
                "dateFrom" => "dateFrom2",
                "dateTo" => "dateTo2",
                'classOfEquipment' => 'classOfEquipment2',
                'typeOfEquipment' => 'typeOfEquipment2',
                'approximateNumberOfMilesTotal' => 'approximateNumberOfMilesTotal2'
            ],
			
			[
                "dateFrom" => "dateFrom3",
                "dateTo" => "dateTo3",
                'classOfEquipment' => 'classOfEquipment3',
                'typeOfEquipment' => 'typeOfEquipment3',
                'approximateNumberOfMilesTotal' => 'approximateNumberOfMilesTotal3'
            ],
			
			[
                "dateFrom" => "dateFrom4",
                "dateTo" => "dateTo4",
                'classOfEquipment' => 'classOfEquipment4',
                'typeOfEquipment' => 'typeOfEquipment4',
                'approximateNumberOfMilesTotal' => 'approximateNumberOfMilesTotal4'
            ]
			
        ],
        
        "dataGridLicenseInformation" =>
        [
            [
                'state2' => 'state4',
                'type' => 'type',
                'expirationDate' => 'expirationDate',
                'listAnyEndorsementsOrRestrictions' => 'listAnyEndorsementsOrRestrictions',
                'license' => 'license'
            ],

            [
                'state2' => 'state5',
                'type' => 'type1',
                'expirationDate' => 'expirationDate1',
                'listAnyEndorsementsOrRestrictions' => 'listAnyEndorsementsOrRestrictions1',
                'license' => 'license1'
            ],

            [
                'state2' => 'state6',
                'type' => 'type2',
                'expirationDate' => 'expirationDate2',
                'listAnyEndorsementsOrRestrictions' => 'listAnyEndorsementsOrRestrictions2',
                'license' => 'license2'
            ]

        ],

        "previousThreeYearsResidencyGrid" =>
        [
            [
                'streetAddress' => 'streetAddress',
                'city2' => 'city4',
                'state3' => 'state7',
                'zipCode' => 'zipCode4',
                'ofYearsAtAddress' => 'ofYearsAtAddress'
            ],
            [
                'streetAddress' => 'streetAddress2',
                'city2' => 'city5',
                'state3' => 'state8',
                'zipCode' => 'zipCode5',
                'ofYearsAtAddress' => 'ofYearsAtAddress1'
            ],
            [
                'streetAddress' => 'streetAddress3',
                'city2' => 'city6',
                'state3' => 'state9',
                'zipCode' => 'zipCode6',
                'ofYearsAtAddress' => 'ofYearsAtAddress2'
            ],
            [
                'streetAddress' => 'streetAddress4',
                'city2' => 'city7',
                'state3' => 'state10',
                'zipCode' => 'zipCode7',
                'ofYearsAtAddress' => 'ofYearsAtAddress3'
            ],
            [
                'streetAddress' => 'streetAddress5',
                'city2' => 'city8',
                'state3' => 'state11',
                'zipCode' => 'zipCode8',
                'ofYearsAtAddress' => 'ofYearsAtAddress4'
            ]
        ],
        
        "educationQualificationGrid" =>
         [
            [
                'nameLocation' => 'nameLocation',
                'courseOfStudy' => 'courseOfStudy',
                'yearsCompleted' => 'yearsCompleted',
                'details' => 'details'
            ],

            [
                'nameLocation' => 'nameLocation2',
                'courseOfStudy' => 'courseOfStudy2',
                'yearsCompleted' => 'yearsCompleted2',
                'details' => 'details2'
            ],
        
            [
                'nameLocation' => 'nameLocation3',
                'courseOfStudy' => 'courseOfStudy3',
                'yearsCompleted' => 'yearsCompleted3',
                'details' => 'details3'
            ]            
        ],

        "radioYN" =>
        [

            //employment desired
            'fullTime' => [
                "fieldname" => 'fullTime',
                "options" => ["yes" => "Yes", "no" => "No"]
            ],
            'partTime' => [
                "fieldname" => 'partTime',
                "options" => ["yes" => "Yes", "no" => "No"]
            ],
            'weekends1' => [
                "fieldname" => 'weekends1',
                "options" => ["yes" => "Yes", "no" => "No"]
            ],
            'overtime1' => [
                "fieldname" => 'overtime1',
                "options" => ["yes" => "Yes", "no" => "No"]
            ],
                      
        ],
        "date" =>
        [
            //driver information
            "dateOfBirth" =>  "dateOfBirth",
            
            //employment desired
            "dateAvailable" =>  "dateAvailable",
     
            //past 3 years accident records
            "date" => "date",
 
            //education & qualification
            // "expirationDate" => "expirationDate",
			// "expirationDate1" => "expirationDate1",
			// "expirationDate2" => "expirationDate2",
            
        ],

    ]
);