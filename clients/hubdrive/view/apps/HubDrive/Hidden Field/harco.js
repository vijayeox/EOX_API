if (data.workbooksToBeGenerated.harco == true) {
  var primaryOEM = "";
  var secondaryOEM = "";
  var other = "";
  var tempWindDeductible;
  var firstEarthquakeDeductible = 0;
  var FPageGridOne = [];
  var FPageGridTwo = [];
  var FPageGridThree = [];
  var checknewcarsales = 0;
  var checkusedCarSales = 0;
  var checkfISales = 0;
  var checkserviceRepairSales = 0;
  var checkautoBodySales = 0;
  var checkpartsSales = 0;
  var checkindemnitytype = "";
  var checkavgOfTimesContractDriversUsedPerMonth = "None";
  var totalbusinessincomelimit = [];

  data.locationSchedule.map(locationItem => {
    var calculatebusinessincomelimit = 0;
    if (locationItem.buildingDetails.length > 1) {
      locationItem.buildingDetails.map(item => {
        calculatebusinessincomelimit += item.businessincomelimit
          ? item.businessincomelimit
          : 0;
      });
      locationItem.buildingDetails.map(item => {
        if (item.buildingNumber == 1) {
          totalbusinessincomelimit.push({
            result: calculatebusinessincomelimit
          });
        } else {
          totalbusinessincomelimit.push({ result: "" });
        }
      });
    } else if (locationItem.buildingDetails.length == 1) {
      totalbusinessincomelimit.push({
        result: locationItem.buildingDetails[0]["businessincomelimit"]
      });
    }
  });

  if (
    data.avgOfTimesContractDriversUsedPerMonth > 0 &&
    data.avgOfTimesContractDriversUsedPerMonth <= 20
  ) {
    checkavgOfTimesContractDriversUsedPerMonth = "Lesser";
  } else if (data.avgOfTimesContractDriversUsedPerMonth > 20) {
    checkavgOfTimesContractDriversUsedPerMonth = "Greater";
  }

  data.genericData.locationScheduleGridData.some(item => {
    if (parseFloat(item.coinsuranceamount) > 0) {
      checkindemnitytype = item.coinsuranceamount;
      return true;
    } else {
      return false;
    }
  });

  if (checkindemnitytype == "") {
    data.genericData.locationScheduleGridData.some(item => {
      if (parseFloat(item.bimonthlylimitation) > 0) {
        checkindemnitytype = item.bimonthlylimitation;
        return true;
      } else {
        return false;
      }
    });
  }

  data.genericData.locationScheduleGridData.some(locationItem => {
    if (
      locationItem.occupancyType === "autoSalesAndService" ||
      locationItem.occupancyType === "newCarShowroomAndSales"
    ) {
      primaryOEM = locationItem.primaryOEM;
      secondaryOEM = locationItem.secondaryOEM[0];
      other = locationItem.secondaryOEM[1] ? locationItem.secondaryOEM[1] : "";
      return true;
    } else {
      return false;
    }
  });

  data.genericData.locationScheduleGridData.map(locationItem => {
    if (locationItem.signsGreaterThan > 0) {
      FPageGridOne.push([
        locationItem.locationBuildingNumber,
        "Billboards & Signs - Other",
        "",
        "",
        "",
        locationItem.signsGreaterThan
      ]);
    }
    if (locationItem.fences > 0) {
      FPageGridOne.push([
        locationItem.locationBuildingNumber,
        "Fences & Arbors - Wood",
        "",
        "",
        "",
        locationItem.fences
      ]);
    }
    if (locationItem.awaningsscanopies > 0) {
      FPageGridOne.push([
        locationItem.locationBuildingNumber,
        "Awnings & Canopies - Other",
        "",
        "",
        "",
        locationItem.awaningsscanopies
      ]);
    }
    if (locationItem.lightpoles > 0) {
      FPageGridOne.push([
        locationItem.locationBuildingNumber,
        "Street Lights - Metal",
        "",
        "",
        "",
        locationItem.lightpoles
      ]);
    }
  });

  data.genericData.locationScheduleGridData.map(locationItem => {
    if (locationItem.businessincomelimit > 0) {
      FPageGridTwo.push([
        locationItem.locationBuildingNumber,
        "",
        locationItem.utilityservices,
        "",
        "",
        locationItem.waterSupply == "yes" ? "Yes" : "No",
        locationItem.communicationSupply == "yes" ? "Yes" : "No",
        locationItem.communicationLines == "yes" ? "Yes" : "No",
        locationItem.powerSupply == "yes" ? "Yes" : "No",
        locationItem.overheadPowerLines == "yes" ? "Yes" : "No"
      ]);
    }
  });

  data.genericData.locationScheduleGridData.map(locationItem => {
    if (locationItem.businesselectronicequipment > 0) {
      FPageGridThree.push([
        locationItem.locationBuildingNumber,
        "",
        "",
        "",
        "",
        "",
        "",
        locationItem.businesselectronicequipment
      ]);
    }
  });

  data.locationSchedule.map(locationItem => {
    if (locationItem.newcarsales > 0) {
      checknewcarsales += locationItem.newcarsales;
    }
    if (locationItem.usedCarSales > 0) {
      checkusedCarSales += locationItem.usedCarSales;
    }
    if (locationItem.fISales > 0) {
      checkfISales += locationItem.fISales;
    }
    if (locationItem.serviceRepairSales > 0) {
      checkserviceRepairSales += locationItem.serviceRepairSales;
    }
    if (locationItem.autoBodySales > 0) {
      checkautoBodySales += locationItem.autoBodySales;
    }
    if (locationItem.partsSales > 0) {
      checkpartsSales += locationItem.partsSales;
    }
  });

  data.genericData.locationScheduleGridData.some(locationItem => {
    if (parseFloat(locationItem.earthquakedeductibles1) > 0) {
      firstEarthquakeDeductible = locationItem.earthquakedeductibles1;
      return true;
    } else {
      return false;
    }
  });

  data.genericData.locationScheduleGridData.some(locationItem => {
    if (
      locationItem.windDeductible &&
      locationItem.windDeductible != "exclude" &&
      locationItem.windDeductible != "flat"
    ) {
      tempWindDeductible = locationItem.windDeductible + " %";
      return true;
    } else if (locationItem.flatamount > 0) {
      tempWindDeductible = "$" + locationItem.flatamount;
      return true;
    } else {
      return false;
    }
  });
  var tempcrimecoverage = Math.min(
    data.theftofmoneydeductible ? data.theftofmoneydeductible : 200000,
    data.outsidethepremisesdeductible
      ? data.outsidethepremisesdeductible
      : 200000,
    data.burglaryofotherpropertydeductible
      ? data.burglaryofotherpropertydeductible
      : 200000
  );

  value = {
    totalbusinessincomelimit: totalbusinessincomelimit
      ? totalbusinessincomelimit
      : [],
    checksoftwareprotectionNone:
      data.virusscans == "yes" ||
      data.antivirussoftware == "yes" ||
      data.protectedfirewalls == "yes"
        ? "no"
        : "yes",
    checkcrimecoverage: tempcrimecoverage == 200000 ? "" : tempcrimecoverage,
    checkmedicalexpensedeductible:
      data.agentseo == "yes" &&
      (data.medicalexpensedeductible > 0 ||
        data.medicalexpensedeductible.length > 0)
        ? data.medicalexpensedeductible
        : "",
    checkgrosssales:
      data.agentseo == "yes" && data.grosssales > 0 ? data.grosssales : "",
    primarySecondaryOEM: data.genericData.locationScheduleGridData.map(item => {
      if (item.primaryOEM) {
        tempSecondaryOEMStore = item.secondaryOEM
          ? item.secondaryOEM.length > 0
            ? item.secondaryOEM
            : []
          : [];
        return { result: [item.primaryOEM, ...tempSecondaryOEMStore] + "" };
      } else {
        return "";
      }
    }),
    checkeplicoverage: data.state
      ? data.state.name != "California"
        ? "X"
        : ""
      : "",
    checkdiscriminationdeductible: data.state
      ? data.state.name != "California"
        ? "25000"
        : ""
      : "",
    checkthirdpartylimit: data.state
      ? data.state.name != "California"
        ? "100000"
        : ""
      : "",
    checkacterroromissions: data.state
      ? data.state.abbreviation != "CA" &&
        data.state.abbreviation != "FL" &&
        data.state.abbreviation != "MA" &&
        data.state.abbreviation != "VA"
        ? "X"
        : ""
      : "",
    checkthirdpartydeduc: data.state
      ? data.state.name != "California"
        ? "15000"
        : ""
      : "",
    checkactserrorsCAOnly: data.state
      ? data.state.name == "California"
        ? "X"
        : ""
      : "",
    checkactserrorsCAOnlyLimit: data.state
      ? data.state.name == "California"
        ? "50000"
        : ""
      : "",
    checkactserrorsCAOnlyDeduc: data.state
      ? data.state.name == "California"
        ? "2500"
        : ""
      : "",
    checkValuation: data.locationSchedule[0].state
      ? data.locationSchedule[0].state == "CA" ||
        data.locationSchedule[0].state == "FL"
        ? "Replacement"
        : "Reconstruction Cost"
      : "Reconstruction Cost",
    checkthirdparty: data.state
      ? data.state.name != "California"
        ? "X"
        : ""
      : "",
    checkbuildingCoinsurance: data.locationSchedule[0].state
      ? data.locationSchedule[0].state == "CA" ||
        data.locationSchedule[0].state == "FL"
        ? data.buildingCoinsurance
        : 1
      : 1,
    checkbppcoinsurance: data.locationSchedule[0].state
      ? data.locationSchedule[0].state == "CA" ||
        data.locationSchedule[0].state == "FL"
        ? data.bppcoinsurance
        : 1
      : 1,
    checkearthquakecoverage: data.genericData.locationScheduleGridData.some(
      i => i.earthquakecoverage == "yes"
    ),
    checkReplacement: data.state.abbreviation
      ? data.state.abbreviation == "FL" || data.state.abbreviation == "CA"
        ? "Replacement"
        : ""
      : "",
    CPAYes: data.auditperformed.cpa == "yes" ? "yes" : "no",
    CrimeDeductible: Math.min(
      data.theftofmoneydeductible,
      data.outsidethepremisesdeductible,
      data.burglaryofotherpropertydeductible
    )
      ? Math.min(
          data.theftofmoneydeductible,
          data.outsidethepremisesdeductible,
          data.burglaryofotherpropertydeductible
        )
      : "",
    checktestdriveprocedures:
      data.testdriveprocedures.includes("copyOfDriversLicense") &&
      data.testdriveprocedures.includes("copyOfInsuranceCard")
        ? "Yes"
        : "No",
    CrimeLimit: Math.max(data.computerlimit, data.fundslimit)
      ? Math.max(data.computerlimit, data.fundslimit)
      : "",
    checkWindDeductible: tempWindDeductible
      ? "Wind Hail Deductible " + tempWindDeductible
      : "",

    checklockbox:
      data.dolkeycntrlsameallloc == "no"
        ? data.locationSchedule.some(locationItem => {
            if (locationItem.locationGarageLiabilityKeyControls) {
              if (
                locationItem.locationGarageLiabilityKeyControls.lockbox == true
              ) {
                return true;
              }
            } else {
              return false;
            }
          })
        : "",
    checkcomputerizedKeyVault:
      data.dolkeycntrlsameallloc == "no"
        ? data.locationSchedule.some(locationItem => {
            if (locationItem.locationGarageLiabilityKeyControls) {
              if (
                locationItem.locationGarageLiabilityKeyControls
                  .computerizedKeyVault == true
              ) {
                return true;
              }
            } else {
              return false;
            }
          })
        : "",

    checkDolFullyFencedPremises: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolEntranceQuestions.fullyFencedPremises == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkDolBlockedEntrances: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolEntranceQuestions.blockedEntrances == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkAfterHoursLighting: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolaftrhrslighting == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkSecurityGuards: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolsecguards == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkdolalrmsys: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolalrmsys == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkdolsurcamnotmoniotrd: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolsurcamnotmoniotrd == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkdolsurvcammoniintrunotifi: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolsurvcammoniintrunotifi == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkdolguarddogs: data.genericData.locationScheduleGridData.some(
      locationItem => {
        if (locationItem.dolguarddogs == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),
    checklockingKeyCabinet:
      data.dolkeycntrlsameallloc == "no"
        ? data.locationSchedule.some(locationItem => {
            if (locationItem.locationGarageLiabilityKeyControls) {
              if (
                locationItem.locationGarageLiabilityKeyControls
                  .lockingKeyCabinet == true
              ) {
                return true;
              }
            } else {
              return false;
            }
          })
        : "",
    checkdailyKeyInventory:
      data.dolkeycntrlsameallloc == "no"
        ? data.locationSchedule.some(locationItem => {
            if (locationItem.locationGarageLiabilityKeyControls) {
              if (
                locationItem.locationGarageLiabilityKeyControls
                  .dailyKeyInventory == true
              ) {
                return true;
              }
            } else {
              return false;
            }
          })
        : "",
    checkkeysInCars:
      data.dolkeycntrlsameallloc == "no"
        ? data.locationSchedule.some(locationItem => {
            if (locationItem.locationGarageLiabilityKeyControls) {
              if (
                locationItem.locationGarageLiabilityKeyControls.keysInCars ==
                true
              ) {
                return true;
              }
            } else {
              return false;
            }
          })
        : "",
    checklockedInManagersOffice:
      data.dolkeycntrlsameallloc == "no"
        ? data.locationSchedule.some(locationItem => {
            if (locationItem.locationGarageLiabilityKeyControls) {
              if (
                locationItem.locationGarageLiabilityKeyControls
                  .lockedInManagersOffice == true
              ) {
                return true;
              }
            } else {
              return false;
            }
          })
        : "",
    FPageGridOne: FPageGridOne.length > 0 ? FPageGridOne : "",
    FPageGridTwo: FPageGridTwo.length > 0 ? FPageGridTwo : "",
    FPageGridThree: FPageGridThree.length > 0 ? FPageGridThree : "",
    GIMGridOne: data.locationSchedule.map(locationItem => {
      var employeetoolsItem = 0;
      var accountsreceivableItem = 0;
      var valulablePapersItem = 0;
      locationItem.buildingDetails.map(buildingItem => {
        if (buildingItem.employeetools > 0) {
          employeetoolsItem += buildingItem.employeetools;
        }
        if (buildingItem.accountsreceivable > 0) {
          accountsreceivableItem += buildingItem.accountsreceivable;
        }
        if (buildingItem.valulablePapers > 0) {
          valulablePapersItem += buildingItem.valulablePapers;
        }
      });
      return [
        employeetoolsItem > 0 ? employeetoolsItem : "",
        "",
        "",
        accountsreceivableItem > 0 ? accountsreceivableItem : "",
        "",
        valulablePapersItem > 0 ? valulablePapersItem : ""
      ];
    }),
    GIMGridTwo: data.locationSchedule
      .map(locationItem => {
        var signsnotattachedItem = 0;
        locationItem.buildingDetails.map(buildingItem => {
          if (buildingItem.signsnotattached > 0) {
            signsnotattachedItem += buildingItem.signsnotattached;
          }
        });
        return signsnotattachedItem > 0
          ? [
              locationItem.locationNum + "-1",
              "",
              "",
              "",
              "",
              signsnotattachedItem > 0 ? signsnotattachedItem : ""
            ]
          : "empty";
      })
      .filter(item => item != "empty"),
    JGridOne: data.locationSchedule.map(locationItem => {
      var oneItem = 0;
      var twoItem = 0;
      var threeItem = 0;
      var fourItem = 0;
      var fiveItem = 0;
      var sixItem = 0;
      locationItem.buildingDetails.map(buildingItem => {
        if (
          buildingItem.fTEmployeesFurnishedAnAuto +
            buildingItem.fTEmployeesWhoAreNotFurnished >
          0
        ) {
          oneItem +=
            buildingItem.fTEmployeesFurnishedAnAuto +
            buildingItem.fTEmployeesWhoAreNotFurnished;
        }
        if (
          buildingItem.pTEmployeesFurnishedAnAuto +
            buildingItem.pTEmployeesWhoAreNotFurnished +
            buildingItem.contractDriversNonEmployees >
          0
        ) {
          twoItem +=
            buildingItem.pTEmployeesFurnishedAnAuto +
            buildingItem.pTEmployeesWhoAreNotFurnished +
            buildingItem.contractDriversNonEmployees;
        }
        if (buildingItem.fTAllOtherEmployees > 0) {
          threeItem += buildingItem.fTAllOtherEmployees;
        }
        if (buildingItem.pTAllOtherEmployees > 0) {
          fourItem += buildingItem.pTAllOtherEmployees;
        }
        if (buildingItem.nonEmployeesUnderTheAge > 0) {
          fiveItem += buildingItem.nonEmployeesUnderTheAge;
        }
        if (buildingItem.nonEmployeesYearsOldorolder > 0) {
          sixItem += buildingItem.nonEmployeesYearsOldorolder;
        }
      });
      return [oneItem, twoItem, threeItem, fourItem, fiveItem, sixItem];
    }),
    firstEarthquakeDeductible:
      firstEarthquakeDeductible == 0 ? "" : firstEarthquakeDeductible,
    AGENPrimaryOEM: primaryOEM,
    AGENSecondaryOEM: secondaryOEM,
    AGENOtherOEM: other,
    locationBuildingHarcoGrid: data.genericData.locationScheduleGridData.map(
      item => {
        var individualResult = [];
        var requiredFields = [
          "locationNum",
          "buildingNumber",
          "address",
          "invalid",
          "city",
          "state",
          "zip",
          "county"
        ];
        requiredFields.map(field => {
          if (item[field]) {
            if (field == "secondaryOEM") {
              try {
                item[field][0].length > 1
                  ? individualResult.push(item[field][0])
                  : individualResult.push(" ");
              } catch {
                individualResult.push(" ");
              }
            } else {
              if (item[field].length > 0 || item[field] > 0) {
                individualResult.push(item[field]);
              } else {
                individualResult.push(" ");
              }
            }
          } else {
            individualResult.push(" ");
          }
        });
        return individualResult;
      }
    ),
    checkequipmentBreakdown: data.genericData.locationScheduleGridData.some(
      item => {
        if (item.equipmentBreakdown == "yes") {
          return true;
        } else {
          return false;
        }
      }
    ),

    checknewcarsales: checknewcarsales ? checknewcarsales : "",
    checkusedCarSales: checkusedCarSales ? checkusedCarSales : "",
    checkfISales: checkfISales ? checkfISales : "",
    checkserviceRepairSales: checkserviceRepairSales
      ? checkserviceRepairSales
      : "",
    checkautoBodySales: checkautoBodySales ? checkautoBodySales : "",
    checkpartsSales: checkpartsSales ? checkpartsSales : "",
    OthersFISalesDescription: checkfISales > 0 ? "Finance/Insurance" : "",
    OthersAutoBodyDescription: checkautoBodySales > 0 ? "Auto Body" : "",
    MGLGrid: data.genericData.locationScheduleGridData
      .map(item => {
        var individualResult = [];
        if (
          item.occupancyType == "vacantLand" ||
          item.occupancyType == "bldgLeasedToOthers"
        ) {
          individualResult.push(item.locationBuildingNumber);
          individualResult.push(
            item.occupancyType == "vacantLand"
              ? "VACANT LAND (FOR-PROFIT)"
              : "BUILDINGS OR PREM - LESSOR'S RISK ONLY, FOR PROFIT"
          );
          [...Array(10)].map(i => individualResult.push(""));
          individualResult.push(
            item.exposureAmount > 0 ? item.exposureAmount : 0
          );
        }
        return individualResult.length > 0 ? individualResult : "empty";
      })
      .filter(item => item != "empty"),
    checkwhatistheaveragepercentage:
      data.whatistheaveragepercentage > 0
        ? data.whatistheaveragepercentage / 100
        : "",
    checkothertankcontentsExists: data.genericData.storageTankGridData.some(
      tankItem => {
        if (tankItem.othertankcontents.length > 0) {
          return true;
        } else {
          return false;
        }
      }
    ),
    checkothertankcontents:
      [
        ...new Set(
          data.genericData.storageTankGridData
            .map(tankItem => {
              if (tankItem.othertankcontents.length > 0) {
                return tankItem.othertankcontents;
              } else {
                return "invalid";
              }
            })
            .filter(i => i != "invalid")
        )
      ] + "",
    checkindemnitytype: checkindemnitytype == "" ? "" : checkindemnitytype,
    checkleasedtoOthers: data.genericData.locationScheduleGridData.some(
      i => i.occupancyType == "bldgLeasedToOthers"
    ),
    checklessThan3years: data.numYearsOfOwnership < 3 ? "Yes" : "No",
    checkroofingyear: data.genericData.locationScheduleGridData.some(
      i => i.roofingyear < moment().format("YYYY") - 20
    ),
    checkbuildingLimit: data.genericData.locationScheduleGridData.map(i => {
      return {
        result:
          (i.signsLessThan > 0 ? i.signsLessThan : 0) +
          (i.buildingLimit > 0 ? i.buildingLimit : 0)
      };
    }),
    checkUmbrellaNameExists: data.UmbrellaCoverageAdditional.some(
      umbrellaItem =>
        umbrellaItem.umbrellaCoverageAdditionalName &&
        umbrellaItem.ownershipPercentageUmbrella > 0
    ),
    checkdriveumuim: data.driverOtherCargrid
      ? data.driverOtherCargrid[0].driveumuim > 500000
        ? 500000
        : data.driverOtherCargrid[0].driveumuim
      : "",
    checkrepairpercentparts:
      parseFloat(data.repairpercentparts) > 0 ? data.repairpercentparts : "",
    checkrepairpercentlabor:
      parseFloat(data.repairpercentlabor) > 0 ? data.repairpercentlabor : "",
    checkrepairpercentlaborexists:
      parseFloat(data.repairpercentlabor) > 0 ? "Yes" : "No",
    checkavgOfTimesContractDriversUsedPerMonth: checkavgOfTimesContractDriversUsedPerMonth,
    checkCustomerInsuranceValidity:
      data.customersinsuranceverified && data.regularlythroughouttermofloan
        ? "Yes"
        : "No"
  };
  console.log(value);
}