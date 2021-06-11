if (data.workbooksToBeGenerated.victor_FranchisedAutoDealer == true) {
  var falsePretenseLimit = 0;
  var checktotalLocationSalesRevenue = 0;
  var checklistCompaniesRepresented = [];
  var checkblanketcoverage = [];

  data.locationSchedule.map(locationItem => {
    if (locationItem.buildingDetails.length > 1) {
      locationItem.buildingDetails.map(item => {
        checkblanketcoverage.push({ result: "Premise Only" });
      });
    } else if (locationItem.buildingDetails.length == 1) {
      checkblanketcoverage.push({ result: "No" });
    }
  });

  checklistCompaniesRepresented = data.companiesRepresentedList.map(
    item => item.listcompaniesrepresented
  );

  data.locationSchedule.map(item => {
    checktotalLocationSalesRevenue += item.totalLocationSalesRevenue
      ? item.totalLocationSalesRevenue
      : 0;
  });

  if (data.falsePretenseNumber <= 0) {
    falsePretenseLimit = "None";
  } else if (data.falsePretenseNumber <= 25000) {
    falsePretenseLimit = 25000;
  } else if (data.falsePretenseNumber <= 50000) {
    falsePretenseLimit = 50000;
  } else if (data.falsePretenseNumber <= 75000) {
    falsePretenseLimit = 75000;
  } else if (data.falsePretenseNumber <= 100000) {
    falsePretenseLimit = 100000;
  } else if (data.falsePretenseNumber <= 150000) {
    falsePretenseLimit = 150000;
  } else if (data.falsePretenseNumber <= 200000) {
    falsePretenseLimit = 200000;
  } else {
    falsePretenseLimit = 250000;
  }

  value = {
    checkblanketcoverage: checkblanketcoverage ? checkblanketcoverage : [],
    checkcompaniesrepresented: checklistCompaniesRepresented + "",
    checktotalLocationSalesRevenue: checktotalLocationSalesRevenue,
    checkfalsePretenseLimit: falsePretenseLimit,
    locationBuildingIndex: data.genericData.locationScheduleGridData
      .map((i, index) => (index == 0 ? "invalid" : { result: i.locationNum }))
      .filter(i => i !== "invalid"),
    checkbuildingCoinsurance: data.genericData.locationScheduleGridData.map(
      item => {
        return item.occupancyType == "vacantLand"
          ? { result: "" }
          : { result: "Agreed Amount" };
      }
    ),
    checkpropertyCoverageDeductible: data.genericData.locationScheduleGridData.map(
      item => {
        return item.occupancyType == "vacantLand"
          ? { result: "" }
          : {
              result: data.propertyCoverageDeductible
                ? data.propertyCoverageDeductible
                : ""
            };
      }
    ),
    checkbuildingValuation: data.genericData.locationScheduleGridData.map(i => {
      return { result: "Replacement Cost" };
    }),
    CityStateZip: data.city + "," + data.state.name + "," + data.zip,
    InsuranceContactNameandPhone: data.insurancecontactdataGrid
      ? data.insurancecontactdataGrid[0].genfirstname +
        " " +
        data.insurancecontactdataGrid[0].genlastname +
        "," +
        data.insurancecontactdataGrid[0].genphone
      : "",
    checkSecurityGuards: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolsecguards == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkdolaftrhrslighting: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolaftrhrslighting == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkSurveillanceCamera: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolsurvcammoniintrunotifi == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkNotMonitoredSurveillanceCamera: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolsurcamnotmoniotrd == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkdolpostnchain: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolEntranceQuestions.postChain == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkdolflyfencdpremises: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolEntranceQuestions.fullyFencedPremises == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checknumberofservicebays: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.numberofservicebays == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    garageLiabilityGrid: data.locationSchedule.map(locationItem => {
      var oneItem = 0;
      var twoItem = 0;
      var threeItem = 0;
      var fourItem = 0;
      var fiveItem = 0;
      var sixItem = 0;
      var sevenItem = 0;
      var eightItem = 0;
      var nineItem = 0;
      locationItem.buildingDetails.map(item => {
        oneItem +=
          item.fTEmployeesFurnishedAnAuto > 0
            ? item.fTEmployeesFurnishedAnAuto
            : 0;
        twoItem +=
          item.pTEmployeesFurnishedAnAuto > 0
            ? item.pTEmployeesFurnishedAnAuto
            : 0;
        threeItem +=
          item.fTEmployeesWhoAreNotFurnished > 0
            ? item.fTEmployeesWhoAreNotFurnished
            : 0;
        fourItem +=
          item.pTEmployeesWhoAreNotFurnished > 0
            ? item.pTEmployeesWhoAreNotFurnished
            : 0;
        fiveItem += item.fTAllOtherEmployees > 0 ? item.fTAllOtherEmployees : 0;
        sixItem += item.pTAllOtherEmployees > 0 ? item.pTAllOtherEmployees : 0;
        sevenItem +=
          item.nonEmployeesUnderTheAge > 0 ? item.nonEmployeesUnderTheAge : 0;
        eightItem +=
          item.nonEmployeesYearsOldorolder > 0
            ? item.nonEmployeesYearsOldorolder
            : 0;
        nineItem +=
          item.contractDriversNonEmployees > 0
            ? item.contractDriversNonEmployees
            : 0;
      });
      return [
        oneItem,
        twoItem,
        threeItem,
        fourItem,
        fiveItem,
        sixItem,
        sevenItem,
        eightItem,
        nineItem
      ];
    }),
    checkEmployeetoolsOnPremises: data.genericData.locationScheduleGridData.map(
      item => {
        return {
          result:
            (item.businesselectronicequipment > 0
              ? item.businesselectronicequipment
              : 0) +
            (item.employeetools > 0 ? item.employeetools : 0) +
            (item.lightpoles > 0 ? item.lightpoles : 0) +
            (item.signsLessThan > 0 ? item.signsLessThan : 0) +
            (item.signsGreaterThan > 0 ? item.signsGreaterThan : 0) +
            (item.valulablePapers > 0 ? item.valulablePapers : 0) +
            (item.accountsreceivable > 0 ? item.accountsreceivable : 0) +
            (item.awaningsscanopies > 0 ? item.awaningsscanopies : 0)
        };
      }
    ),
    checkTotalbuildinglimit: data.genericData.locationScheduleGridData.map(
      item => {
        return {
          result:
            (item.signsLessThan > 0 ? item.signsLessThan : 0) +
            (item.signsGreaterThan > 0 ? item.signsGreaterThan : 0) +
            (item.buildingLimit > 0 ? item.buildingLimit : 0)
        };
      }
    ),
    checkOrdinanceAndUtility: data.genericData.locationScheduleGridData.map(
      item => {
        return {
          result:
            (item.utilityservices > 0
              ? "Utility Services Time Element: " + item.utilityservices
              : "") +
            (item.utilityservices > 0 && item.ordinancelawcoverage > 0
              ? ", "
              : "") +
            (item.ordinancelawcoverage > 0
              ? "Ordinance Law: " + item.ordinancelawcoverage
              : "")
        };
      }
    ),
    checkrepairpercentlabor:
      data.repairpercentlabor == 95 || data.repairpercentlabor == 85
        ? 90
        : data.repairpercentlabor,
    checkwhodeliversvehiclesonemployee:
      data.whodeliversvehicleson == "employee" ? "true" : "false",
    checkwhodeliversvehiclesonindependentContractor:
      data.whodeliversvehicleson == "independentContractor" ? "true" : "false"
  };
}
