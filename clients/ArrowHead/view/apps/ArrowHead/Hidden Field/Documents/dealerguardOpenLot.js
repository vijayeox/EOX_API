if (data.workbooksToBeGenerated.dealerGuard_ApplicationOpenLot == true) {
  value = {
    checkfalsePretenseNumber:
      data.falsePretenseNumber > 100000
        ? "Increased above the current maximum per vehicle value"
        : data.falsePretenseNumber,
    ownershipDate:
      data.numYearsOfOwnership > 0
        ? moment()
            .subtract(data.numYearsOfOwnership, "years")
            .format("MM-DD-YYYY")
        : moment().format("MM-DD-YYYY"),
    checkemployees:
      (data.employeeListTotal.fTEmployeesFurnishedAnAuto > 0
        ? data.employeeListTotal.fTEmployeesFurnishedAnAuto
        : 0) +
      (data.employeeListTotal.pTEmployeesFurnishedAnAuto > 0
        ? data.employeeListTotal.pTEmployeesFurnishedAnAuto
        : 0),
    checknonemployees:
      (data.employeeListTotal.nonEmployeesUnderTheAge > 0
        ? data.employeeListTotal.nonEmployeesUnderTheAge
        : 0) +
      (data.employeeListTotal.nonEmployeesYearsOldorolder > 0
        ? data.employeeListTotal.nonEmployeesYearsOldorolder
        : 0),
    checkVehicleInventoryTaken:
      data.dolvehicleinevtorytaken == "weekly" ||
      data.dolvehicleinevtorytaken == "biWeekly" ||
      data.dolvehicleinevtorytaken == "monthly" ||
      data.dolvehicleinevtorytaken == "daily" ||
      data.dolvehicleinevtorytaken == "quarterly"
        ? "true"
        : "false",
    checkVehicleInventoryTakenWeekly:
      data.dolvehicleinevtorytaken == "weekly" ||
      data.dolvehicleinevtorytaken == "daily"
        ? "true"
        : "false",
    checkVehicleInventoryTakenBiweekly:
      data.dolvehicleinevtorytaken == "biWeekly" ? "true" : "false",
    checkVehicleInventoryTakenMonthly:
      data.dolvehicleinevtorytaken == "monthly" ||
      data.dolvehicleinevtorytaken == "quarterly"
        ? "true"
        : "false",
    checkcustomerserviceloanerrentalprogram:
      data.customerserviceloanerrentalprogram == "no"
        ? "false"
        : data.loanerrentalcontractused == "yes"
        ? "true"
        : "false",
    checkDOLTotalNoOfDemos: data.doltotalnoofdemos > 0 ? "true" : "false",
    checkGenFullName: data.insurancecontactdataGrid[0].genfullName,
    checkGenEmail: data.insurancecontactdataGrid[0].genemail,
    checkLockbox: data.dol_Protection.some(
      (item) => item.keyControls.lockbox == true
    ),
    checkDailyKeyInventory: data.dol_Protection.some(
      (item) => item.keyControls.dailyKeyInventory == true
    )
      ? "Yes"
      : "No",
    checkComputerizedKeyVault: data.dol_Protection.some(
      (item) => item.keyControls.computerizedKeyVault == true
    )
      ? "yes"
      : "no",
    //checkSecurityGuards: false,
    // Need to check condition. Occupancy type is at building Level but
    // dol_Protection[0]premisesLotProtection are at loc level
    checkSecurityGuards: data.locations.some((locationItem) => {
      if (
        locationItem.occupancyType == "autoSalesAndService" &&
        data.dol_Protection[locationItem.locationNum - 1].premisesLotProtection
          .securityGuards == "yes"
      ) {
        return true;
      } else {
        return false;
      }
    }),
    checkfalsePretenseNumber:
      data.falsePretenseNumber > 100000
        ? "Increased above the current maximum per vehicle value"
        : data.falsePretenseNumber,
    checkserviceRepairSales: data.financialsYTDSalesTotal.service > 0,
    SOVGrid: data.locations
      .filter((i) => i.buildingNum == 1)
      .map((locationItem) => {
        var DOL12MonthItem = data.dol_12MonthAvg[locationItem.locationNum - 1];
        var DOLInventoryItem = data.dol_inventory[locationItem.locationNum - 1];

        if (DOL12MonthItem.total_total > 0) {
          var individualResult = [];

          var buildingList = data.buildings.filter(
            (i) => i.locationNum == locationItem.locationNum
          );
          var franchiseResult = [];
          if (
            buildingList.some(
              (i) =>
                i.occupancyType === "autoSalesAndService" ||
                i.occupancyType === "newCarShowroomAndSales"
            )
          ) {
            buildingList.map((i) => {
              franchiseResult.includes(i.primaryOEM)
                ? null
                : franchiseResult.push(i.primaryOEM);
              i.secondaryOEM
                ? i.secondaryOEM.map((item) => {
                    item.length > 0 && !franchiseResult.includes(item)
                      ? franchiseResult.push(item)
                      : null;
                  })
                : null;
            });

            var finalFranchiseItem = "";
            franchiseResult.length > 0
              ? franchiseResult.map((i, index) => {
                  if (franchiseResult.length == index + 1) {
                    finalFranchiseItem += i;
                  } else {
                    finalFranchiseItem += i + " , ";
                  }
                })
              : null;

            individualResult.push(finalFranchiseItem);
          } else {
            individualResult.push("");
          }

          individualResult.push(locationItem.address);
          individualResult.push(locationItem.city);
          individualResult.push(locationItem.state);
          individualResult.push(locationItem.zip);
          individualResult.push(
            DOL12MonthItem.total_new > 0 ? DOL12MonthItem.total_new : 0
          );
          individualResult.push(
            DOL12MonthItem.total_used > 0 ? DOL12MonthItem.total_used : 0
          );
          individualResult.push(
            (DOL12MonthItem.total_loanersShopService > 0
              ? DOL12MonthItem.total_loanersShopService
              : 0) +
              (DOL12MonthItem.total_demosFurnishedAutos > 0
                ? DOL12MonthItem.total_demosFurnishedAutos
                : 0)
          );
          individualResult.push("");
          individualResult.push(
            DOLInventoryItem.total_maxInventory > 0
              ? DOLInventoryItem.total_maxInventory
              : 0
          );
          individualResult.push(
            DOLInventoryItem.total_maxUnits > 0
              ? DOLInventoryItem.total_maxUnits
              : 0
          );
          individualResult.push(
            DOLInventoryItem.total_maxIndoor > 0
              ? DOLInventoryItem.total_maxIndoor
              : 0
          );

          return individualResult;
        } else {
          return "invalid";
        }
      })
      .filter((i) => i !== "invalid"),
  };
  console.log(value);
}
