if (data.workbooksToBeGenerated.dealerGuard_ApplicationOpenLot == true) {
  var checkemployees = 0;
  var checknonemployees = 0;

  data.genericData.locationScheduleGridData.map((locationItem) => {
    checkemployees +=
      (locationItem.fTEmployeesFurnishedAnAuto > 0
        ? locationItem.fTEmployeesFurnishedAnAuto
        : 0) +
      (locationItem.pTEmployeesFurnishedAnAuto > 0
        ? locationItem.pTEmployeesFurnishedAnAuto
        : 0);
  });

  data.genericData.locationScheduleGridData.map((locationItem) => {
    checknonemployees +=
      (locationItem.nonEmployeesUnderTheAge > 0
        ? locationItem.nonEmployeesUnderTheAge
        : 0) +
      (locationItem.nonEmployeesYearsOldorolder > 0
        ? locationItem.nonEmployeesYearsOldorolder
        : 0);
  });

  value = {
    ownershipDate: data.numYearsOfOwnership > 0 ? moment().subtract(data.numYearsOfOwnership,"years").format("MM-DD-YYYY") : moment().format("MM-DD-YYYY"),
    checkemployees: checkemployees,
    checknonemployees: checknonemployees,
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
    checkLockbox: data.dolalllocdoesnotincld
      ? data.dolalllocdoesnotincld.includes("lockBoxes")
        ? "true"
        : ""
      : data.genericData.locationScheduleGridData.some((locationItem) => {
          if (locationItem.locationGarageLiabilityKeyControls) {
            if (locationItem.locationGarageLiabilityKeyControls.lockbox) {
              return true;
            } else {
              return false;
            }
          } else {
            return false;
          }
        }),
    checkDailyKeyInventory: data.dolalllocdoesnotincld
      ? data.dolalllocdoesnotincld.includes("dailyKeyInventory")
        ? "Yes"
        : "No"
      : data.genericData.locationScheduleGridData.some((locationItem) => {
          if (locationItem.locationGarageLiabilityKeyControls) {
            if (
              locationItem.locationGarageLiabilityKeyControls.dailyKeyInventory
            ) {
              return true;
            } else {
              return false;
            }
          } else {
            return false;
          }
        })
      ? "Yes"
      : "No",
    checkComputerizedKeyVault: data.dolalllocdoesnotincld
      ? data.dolalllocdoesnotincld.includes("computerizedKeyVault")
        ? "true"
        : ""
      : data.genericData.locationScheduleGridData.some((locationItem) => {
          if (locationItem.locationGarageLiabilityKeyControls) {
            if (
              locationItem.locationGarageLiabilityKeyControls
                .computerizedKeyVault
            ) {
              return true;
            } else {
              return false;
            }
          } else {
            return false;
          }
        }),
    checkSecurityGuards: data.genericData.locationScheduleGridData.some(
      (locationItem) => {
        if (
          locationItem.occupancyType == "autoSalesAndService" &&
          locationItem.dolsecguards == "yes"
        ) {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkserviceRepairSales: data.locationSchedule.some((locationItem) => {
      if (locationItem.serviceRepairSales > 0) {
        return true;
      } else {
        return false;
      }
    }),
    SOVGrid: data.locationSchedule
      .map((locationItem) => {
        if (locationItem.avgLocationtotalrequested > 0) {
          var individualResult = [];

          individualResult.push(locationItem.address);
          individualResult.push(locationItem.city);
          individualResult.push(locationItem.state);
          individualResult.push(locationItem.zip);
          individualResult.push(
            (locationItem.monthAvgNew > 0 ? locationItem.monthAvgNew : 0) -
              (locationItem.insuredThroughFloorPlanNew > 0
                ? locationItem.insuredThroughFloorPlanNew
                : 0)
          );
          individualResult.push(
            (locationItem.monthAvgUsed > 0 ? locationItem.monthAvgUsed : 0) -
              (locationItem.insuredThroughFloorPlanUsed > 0
                ? locationItem.insuredThroughFloorPlanUsed
                : 0)
          );
          individualResult.push(
            (locationItem.monthAvgRoadDemosFurnishedAutos > 0
              ? locationItem.monthAvgRoadDemosFurnishedAutos
              : 0) -
              (locationItem.insuredThroughFloorPlanRoad > 0
                ? locationItem.insuredThroughFloorPlanRoad
                : 0) +
              (locationItem.monthAvgRoadLoanersShopService > 0
                ? locationItem.monthAvgRoadLoanersShopService
                : 0) -
              (locationItem.insuredThroughFloorPlan > 0
                ? locationItem.insuredThroughFloorPlan
                : 0)
          );
          individualResult.push("");
          individualResult.push(
            locationItem.maxvalueanytime > 0 ? locationItem.maxvalueanytime : ""
          );
          individualResult.push(
            locationItem.maxcountanytime > 0 ? locationItem.maxcountanytime : ""
          );
          individualResult.push(
            locationItem.maxvalueindoors > 0 ? locationItem.maxvalueindoors : ""
          );
          return individualResult;
        } else {
          return "invalid";
        }
      })
      .filter((i) => i !== "invalid"),
    SOVFranchise: data.locationSchedule
      .map((locationItem) => {
        if (locationItem.avgLocationtotalrequested > 0) {
          var individualResult = [];
          locationItem.buildingDetails.map((buildingDetails) => {
            if (
              buildingDetails.occupancyType === "autoSalesAndService" ||
              buildingDetails.occupancyType === "newCarShowroomAndSales"
            ) {
              individualResult.includes(buildingDetails.primaryOEM)
                ? null
                : individualResult.push(buildingDetails.primaryOEM);
              buildingDetails.secondaryOEM.map(item => {
                item.length > 0 ? individualResult.push(item) : null;
              });
            }
          });
          var finalFranchiseItem = "";
          individualResult.map((i, index) => {
            if (individualResult.length == index + 1) {
              finalFranchiseItem += i;
            } else {
              finalFranchiseItem += i + " , ";
            }
          });
          return { franchise: finalFranchiseItem };
        } else {
          return "invalid";
        }
      })
      .filter((i) => i !== "invalid")
  };
  console.log(value);
}