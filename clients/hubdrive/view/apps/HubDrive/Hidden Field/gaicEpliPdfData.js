if (data.workbooksToBeGenerated.epli == true) {
  var tempFullTimeEmployees = 0;
  var tempPartTimeEmployees = 0;
  var tempContractDrivers = 0;
  var Phone = "";
  var Email = "";
  if (data.locationSchedule) {
    if (data.locationSchedule.length > 0) {
      data.locationSchedule.map((locationItem) => {
        locationItem.buildingDetails.map((buildingItem) => {
          tempFullTimeEmployees +=
            (buildingItem.fTEmployeesFurnishedAnAuto
              ? buildingItem.fTEmployeesFurnishedAnAuto
              : 0) +
            (buildingItem.fTEmployeesWhoAreNotFurnished
              ? buildingItem.fTEmployeesWhoAreNotFurnished
              : 0) +
            (buildingItem.fTAllOtherEmployees
              ? buildingItem.fTAllOtherEmployees
              : 0);
          tempPartTimeEmployees +=
            (buildingItem.pTEmployeesFurnishedAnAuto
              ? buildingItem.pTEmployeesFurnishedAnAuto
              : 0) +
            (buildingItem.pTEmployeesWhoAreNotFurnished
              ? buildingItem.pTEmployeesWhoAreNotFurnished
              : 0) +
            (buildingItem.pTAllOtherEmployees
              ? buildingItem.pTAllOtherEmployees
              : 0);
          tempContractDrivers += buildingItem.contractDriversNonEmployees
            ? buildingItem.contractDriversNonEmployees
            : 0;
        });
      });
    }
  }
  data.insurancecontactdataGrid.some((item) => {
    if (item.genfullName == data.hrRepresentative) {
      Phone = item.genphone;
      Email = item.genemail;
      return true;
    } else {
      return false;
    }
  });

  value = {
    "City, State, Zip":
      data.city + "," + data.state.abbreviation + "," + data.zip,
    "a  full time employees": tempFullTimeEmployees,
    "b  part time employees": tempPartTimeEmployees,
    "Employees  Within the US": tempFullTimeEmployees + tempPartTimeEmployees,
    Name: data.hrRepresentative,
    Phone: Phone,
    Email: Email,
    "Within the US": data.locationSchedule.length,
    "c leasedcontract employees": tempContractDrivers,
    Date: moment().format("YYYY-MM-DD"),
    Group25:
      data.noticetoinsurer == "notApplicable"
        ? "Choice1"
        : data.noticetoinsurer == "yes"
        ? "2"
        : "3",
    Group26:
      data.nonrenewedcoverage == "notApplicable"
        ? "Choice1"
        : data.nonrenewedcoverage == "yes"
        ? "2"
        : "Choice3"
  };
  console.log(value);
}
