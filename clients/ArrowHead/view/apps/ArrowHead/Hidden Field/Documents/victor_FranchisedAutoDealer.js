if (data.workbooksToBeGenerated.victor_FranchisedAutoDealer == true) {
  var falsePretenseLimit = 0;
  var checklistCompaniesRepresented = [];
  var checkblanketcoverage = [];

  data.locations.map((i) => {
    data.locations.filter(
      (j) => j.buildingNum == 2 && i.locationNum == j.locationNum
    ).length > 0
      ? checkblanketcoverage.push({ result: "Premise Only" })
      : checkblanketcoverage.push({ result: "No" });
  });

  checklistCompaniesRepresented = data.companiesRepresentedList.map(
    (item) => item.listcompaniesrepresented
  );

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
    checktotalLocationSalesRevenue: data.financialsYTDSalesTotal.total,
    checkfalsePretenseLimit: falsePretenseLimit,
    locationSpecificData: data.buildings.filter(
      (building) => building.buildingNum == "1"
    ),
    locationBuildingIndex: data.locations
      .map((i, index) => (index == 0 ? false : { result: i.locationNum }))
      .filter((i) => i),
    checkbuildingCoinsurance: data.locations.map((item) => {
      return item.occupancyType == "vacantLand"
        ? { result: "" }
        : { result: "Agreed Amount" };
    }),
    checkpropertyCoverageDeductible: data.locations.map((item) => {
      return item.occupancyType == "vacantLand"
        ? { result: "" }
        : {
            result: data.propertyCoverageDeductible
              ? data.propertyCoverageDeductible
              : "",
          };
    }),
    checkbuildingValuation: data.locations.map((i) => {
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
    checkSecurityGuards: data.dol_Protection.some((locationItem) => {
      if (locationItem.premisesLotProtection.securityGuards == "yes") {
        return true;
      } else {
        return false;
      }
    }),

    checkdolaftrhrslighting: data.dol_Protection.some((locationItem) => {
      if (locationItem.premisesLotProtection.afterHoursLighting == "yes") {
        return true;
      } else {
        return false;
      }
    }),

    checkSurveillanceCamera: data.dol_Protection.some((locationItem) => {
      if (locationItem.entranceQuestions.camerasMonitored == "yes") {
        return true;
      } else {
        return false;
      }
    }),

    checkNotMonitoredSurveillanceCamera: data.dol_Protection.some(
      (locationItem) => {
        if (locationItem.entranceQuestions.camerasNotMonitored == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),

    checkdolpostnchain: data.dol_Protection.some((locationItem) => {
      if (locationItem.entranceQuestions.postChain == "yes") {
        return true;
      } else {
        return false;
      }
    }),

    checkdolflyfencdpremises: data.dol_Protection.some((locationItem) => {
      if (locationItem.entranceQuestions.fullyFencedPremises == "yes") {
        return true;
      } else {
        return false;
      }
    }),

    checknumberofservicebays: data.buildings.some((locationItem) => {
      if (locationItem.numberofservicebays == "yes") {
        return true;
      } else {
        return false;
      }
    }),

    garageLiabilityGrid: data.employeeList
      .filter((i) => i.lastBuilding)
      .map((i) => {
        return [
          i.total_fTEmployeesFurnishedAnAuto > 0
            ? i.total_fTEmployeesFurnishedAnAuto
            : 0,
          i.total_pTEmployeesFurnishedAnAuto > 0
            ? i.total_pTEmployeesFurnishedAnAuto
            : 0,
          i.total_fTEmployeesWhoAreNotFurnished > 0
            ? i.total_fTEmployeesWhoAreNotFurnished
            : 0,
          i.total_pTEmployeesWhoAreNotFurnished > 0
            ? i.total_pTEmployeesWhoAreNotFurnished
            : 0,
          i.total_fTAllOtherEmployees > 0 ? i.total_fTAllOtherEmployees : 0,
          i.total_pTAllOtherEmployees > 0 ? i.total_pTAllOtherEmployees : 0,

          i.total_nonEmployeesUnderTheAge > 0
            ? i.total_nonEmployeesUnderTheAge
            : 0,
          i.total_nonEmployeesYearsOldorolder > 0
            ? i.total_nonEmployeesYearsOldorolder
            : 0,
          i.total_contractDriversNonEmployees > 0
            ? i.total_contractDriversNonEmployees
            : 0,
        ];
      }),

    checkEmployeetoolsOnPremises: data.buildings.map((item) => {
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
          (item.awaningsscanopies > 0 ? item.awaningsscanopies : 0),
      };
    }),
    checkTotalbuildinglimit: data.buildings.map((item) => {
      return {
        result:
          (item.signsLessThan > 0 ? item.signsLessThan : 0) +
          (item.signsGreaterThan > 0 ? item.signsGreaterThan : 0) +
          (item.buildingLimit > 0 ? item.buildingLimit : 0),
      };
    }),
    checkOrdinanceAndUtility: data.buildings.map((item) => {
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
            : ""),
      };
    }),
    checkrepairpercentlabor:
      data.repairpercentlabor == 95 || data.repairpercentlabor == 85
        ? 90
        : data.repairpercentlabor,
    checkwhodeliversvehiclesonemployee:
      data.whodeliversvehicleson == "employee" ? "true" : "false",
    checkwhodeliversvehiclesonindependentContractor:
      data.whodeliversvehicleson == "independentContractor" ? "true" : "false",
  };
}
