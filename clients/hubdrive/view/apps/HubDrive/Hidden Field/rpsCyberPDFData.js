if (data.workbooksToBeGenerated.rpsCyber == true) {
  var tempAllEmployees = 0;
  var checkofRecordsContaining = "";
  if (data.locationSchedule) {
    if (data.locationSchedule.length > 0) {
      data.locationSchedule.map((locationItem, locationIndex) => {
        locationItem.buildingDetails.map(buildingItem => {
          tempAllEmployees +=
            (buildingItem.fTEmployeesFurnishedAnAuto
              ? buildingItem.fTEmployeesFurnishedAnAuto
              : 0) +
            (buildingItem.fTEmployeesWhoAreNotFurnished
              ? buildingItem.fTEmployeesWhoAreNotFurnished
              : 0) +
            (buildingItem.fTAllOtherEmployees
              ? buildingItem.fTAllOtherEmployees
              : 0) +
            (buildingItem.pTEmployeesFurnishedAnAuto
              ? buildingItem.pTEmployeesFurnishedAnAuto
              : 0) +
            (buildingItem.pTEmployeesWhoAreNotFurnished
              ? buildingItem.pTEmployeesWhoAreNotFurnished
              : 0) +
            (buildingItem.pTAllOtherEmployees
              ? buildingItem.pTAllOtherEmployees
              : 0);
        });
      });
    }
  }

  if (data.ofRecordsContaining > 0) {
    if (data.ofRecordsContaining < 100000) {
      checkofRecordsContaining = "1-100,000";
    } else if (
      data.ofRecordsContaining >= 100000 &&
      data.ofRecordsContaining < 250000
    ) {
      checkofRecordsContaining = "100,000-250,000";
    } else if (
      data.ofRecordsContaining >= 250000 &&
      data.ofRecordsContaining < 500000
    ) {
      checkofRecordsContaining = "250,000-500,000";
    } else if (
      data.ofRecordsContaining >= 500000 &&
      data.ofRecordsContaining < 750000
    ) {
      checkofRecordsContaining = "500,000-750,000";
    } else if (
      data.ofRecordsContaining >= 750000 &&
      data.ofRecordsContaining < 1000000
    ) {
      checkofRecordsContaining = "750,000-1,000,000";
    } else {
      checkofRecordsContaining = "1,000,000+";
    }
  }

  value = {
    "# of Records": checkofRecordsContaining,
    State: data.state.abbreviation,
    " of Employees": tempAllEmployees ? tempAllEmployees : 0,
    "Firewalls?":
      data.antivirussoftware == "yes" && data.protectedfirewalls == "yes"
        ? "Yes"
        : "No",
    "Year Established":
      data.numYearsOfOwnership > 0
        ? moment()
            .subtract(data.numYearsOfOwnership, "years")
            .format("YYYY")
        : moment().format("YYYY")
  };

  console.log(value);
}