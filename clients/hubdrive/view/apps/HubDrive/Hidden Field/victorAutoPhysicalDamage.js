if (data.workbooksToBeGenerated.victor_AutoPhysDamage == true) {
  var sumNonEmployeesfurnished = 0;
  var indexValue = 0;
  var primarySecondaryOEM = [];
  var primarySecondaryOEM2 = [];
  var uniqueKeyControls = "";
  var uniqueLotProtection = "";
  var MixofInventoryNew = 0;
  var NewFloorPlan = 0;
  var checkMixofInventoryNew = 0;
  var checklotprotectiondolsecguards = "";
  var checklotprotectiondolaftrhrslighting = "";
  var checklotprotectionpostChain = "";
  var checklotprotectionsurveillance = "";
  var checklotprotectionfullyFencedPremises = "";
  var checklotprotectionnone = "";
  var checkkeycontrolsnone = "";
  var checkkeycontrolslockbox = "";
  var checkkeycontrolscomputerizedKeyVault = "";
  var checkkeycontrolslockingKeyCabinet = "";
  var checkkeycontrolsdailyKeyInventory = "";
  var checkkeycontrolslockedInManagersOffice = "";
  var checkkeycontrolskeysInCars = "";
  var nonEmployeesfurnishedAuto = 0;
  var furnishedAutoEmployee = 0;
  var sumfurnishedAutoEmployee = 0;
  var checkBodyShop = "";

  data.locationSchedule.map(row => {
    MixofInventoryNew +=
      (row.monthAvgNew ? row.monthAvgNew : 0) -
      (row.insuredThroughFloorPlanNew ? row.insuredThroughFloorPlanNew : 0) +
      ((row.monthAvgUsed ? row.monthAvgUsed : 0) -
        (row.insuredThroughFloorPlanUsed
          ? row.insuredThroughFloorPlanUsed
          : 0));

    NewFloorPlan +=
      (row.monthAvgNew ? row.monthAvgNew : 0) -
      (row.insuredThroughFloorPlanNew ? row.insuredThroughFloorPlanNew : 0);
  });

  data.genericData.locationScheduleGridData.map(item => {
    furnishedAutoEmployee +=
      (item.fTEmployeesFurnishedAnAuto ? item.fTEmployeesFurnishedAnAuto : 0) +
      (item.pTEmployeesFurnishedAnAuto ? item.pTEmployeesFurnishedAnAuto : 0) +
      (item.nonEmployeesUnderTheAge ? item.nonEmployeesUnderTheAge : 0) +
      (item.nonEmployeesYearsOldorolder ? item.nonEmployeesYearsOldorolder : 0);
    nonEmployeesfurnishedAuto +=
      (item.nonEmployeesYearsOldorolder
        ? item.nonEmployeesYearsOldorolder
        : 0) +
      (item.nonEmployeesUnderTheAge ? item.nonEmployeesUnderTheAge : 0);
  });

  if (NewFloorPlan > 0 && MixofInventoryNew > 0) {
    var row = NewFloorPlan / MixofInventoryNew;
    if (row <= 0.05) {
      checkMixofInventoryNew = 0;
    } else if (row > 0.05 && row <= 0.15) {
      checkMixofInventoryNew = 0.1;
    } else if (row > 0.15 && row <= 0.25) {
      checkMixofInventoryNew = 0.2;
    } else if (row > 0.25 && row <= 0.35) {
      checkMixofInventoryNew = 0.3;
    } else if (row > 0.35 && row <= 0.45) {
      checkMixofInventoryNew = 0.4;
    } else if (row > 0.45 && row <= 0.55) {
      checkMixofInventoryNew = 0.5;
    } else if (row > 0.55 && row <= 0.65) {
      checkMixofInventoryNew = 0.6;
    } else if (row > 0.65 && row <= 0.75) {
      checkMixofInventoryNew = 0.7;
    } else if (row > 0.75 && row <= 0.85) {
      checkMixofInventoryNew = 0.8;
    } else if (row > 0.85 && row <= 0.95) {
      checkMixofInventoryNew = 0.9;
    } else {
      checkMixofInventoryNew = 1;
    }
  }

  data.genericData.locationScheduleGridData.map(item => {
    if (
      item.occupancyType == "autoSalesAndService" ||
      item.occupancyType == "newCarShowroomAndSales" ||
      item.occupancyType == "vehicleStorageParkingGarage" ||
      item.occupancyType == "autoStorageLot"
    ) {
      tempSecondaryOEMStore = item.secondaryOEM
        ? item.secondaryOEM.length > 0
          ? item.secondaryOEM
          : []
        : [];
      primarySecondaryOEM2 = [
        ...primarySecondaryOEM2,
        item.primaryOEM,
        ...tempSecondaryOEMStore
      ];
    }
  });

  var DealershipStorageLocationsArray = data.locationSchedule
    .map(locationItem => {
      var resultOccupancyType = "";
      var resultPrimaryOEM = "";
      locationItem.buildingDetails.some(buildingItem => {
        if (
          buildingItem.occupancyType == "autoSalesAndService" ||
          buildingItem.occupancyType == "newCarShowroomAndSales"
        ) {
          resultOccupancyType = "Dealership";
          return true;
        } else if (
          buildingItem.occupancyType == "vehicleStorageParkingGarage" ||
          buildingItem.occupancyType == "autoStorageLot"
        ) {
          resultOccupancyType = "Storage";
        } else {
          resultOccupancyType =
            resultOccupancyType == "" ? undefined : resultOccupancyType;
        }
      });
      tempSecondaryOEM = [];
      resultPrimaryOEM =
        [
          ...new Set(
            locationItem.buildingDetails
              .map(i => (i.primaryOEM ? " " + i.primaryOEM : false))
              .filter(i => i)
          )
        ] + "";
      var copylocationItem = { ...locationItem };
      copylocationItem.buildingDetails = "";

      return resultOccupancyType
        ? {
            ...copylocationItem,
            checkAvgExposure:
              (locationItem.monthAvgNew > 0 ? locationItem.monthAvgNew : 0) +
              (locationItem.monthAvgUsed > 0 ? locationItem.monthAvgUsed : 0) +
              (locationItem.monthAvgRoadDemosFurnishedAutos > 0
                ? locationItem.monthAvgRoadDemosFurnishedAutos
                : 0) +
              (locationItem.monthAvgRoadLoanersShopService > 0
                ? locationItem.monthAvgRoadLoanersShopService
                : 0),
            indexValue: (indexValue += 1),
            PrimaryOEMList: resultPrimaryOEM,
            DOLOccupancy: resultOccupancyType
          }
        : false;
    })
    .filter(filterItem => (filterItem ? filterItem : false));

  data.dolkeycntrlsameallloc == "no"
    ? data.locationSchedule.map(item => {
        if (item.locationNum > 1) {
          if (item.locationGarageLiabilityKeyControls) {
            uniqueKeyControls += " Loc-" + item.locationNum + ": ";
            item.locationGarageLiabilityKeyControls.none == true
              ? (uniqueKeyControls += "None,")
              : "";
            item.locationGarageLiabilityKeyControls.lockbox == true
              ? (uniqueKeyControls += "Lock Boxes,")
              : "";
            item.locationGarageLiabilityKeyControls.computerizedKeyVault == true
              ? (uniqueKeyControls += "Key Machine,")
              : "";
            item.locationGarageLiabilityKeyControls.lockingKeyCabinet == true
              ? (uniqueKeyControls += "Key Board,")
              : "";
            item.locationGarageLiabilityKeyControls.dailyKeyInventory == true
              ? (uniqueKeyControls += "Daily Key Inventory,")
              : "";
            item.locationGarageLiabilityKeyControls.lockedInManagersOffice ==
            true
              ? (uniqueKeyControls += "Key Board,")
              : "";
            item.locationGarageLiabilityKeyControls.keysInCars == true
              ? (uniqueKeyControls += "Keys in Cars,")
              : "";
            uniqueKeyControls = uniqueKeyControls.slice(0, -1);
          }
        }
        if (item.locationNum == 1) {
          if (item.locationGarageLiabilityKeyControls) {
            item.locationGarageLiabilityKeyControls.none == true
              ? (checkkeycontrolsnone = "yes")
              : "";
            item.locationGarageLiabilityKeyControls.lockbox == true
              ? (checkkeycontrolslockbox = "yes")
              : "";
            item.locationGarageLiabilityKeyControls.computerizedKeyVault == true
              ? (checkkeycontrolscomputerizedKeyVault = "yes")
              : "";
            item.locationGarageLiabilityKeyControls.lockingKeyCabinet == true
              ? (checkkeycontrolslockingKeyCabinet = "yes")
              : "";
            item.locationGarageLiabilityKeyControls.dailyKeyInventory == true
              ? (checkkeycontrolsdailyKeyInventory = "yes")
              : "";
            item.locationGarageLiabilityKeyControls.lockedInManagersOffice ==
            true
              ? (checkkeycontrolslockedInManagersOffice = "yes")
              : "";
            item.locationGarageLiabilityKeyControls.keysInCars == true
              ? (checkkeycontrolskeysInCars = "yes")
              : "";
          }
        }
      })
    : "";

  data.dollotprotection == "no"
    ? data.locationSchedule.map(item => {
        if (item.locationNum > 1) {
          uniqueLotProtection += " Loc-" + item.locationNum + ": ";
          if (item.dolsecguards == "yes") {
            uniqueLotProtection += "Night Watchman,";
          }
          if (item.dolaftrhrslighting == "yes") {
            uniqueLotProtection += "Security Lighting,";
          }
          if (item.dolEntranceQuestions.postChain == "yes") {
            uniqueLotProtection += "Post and Chains,";
          }
          if (
            item.dolsurvcammoniintrunotifi == "yes" ||
            item.dolsurcamnotmoniotrd == "yes"
          ) {
            uniqueLotProtection += "Video Surveillance,";
          }
          if (item.dolEntranceQuestions.fullyFencedPremises == "yes") {
            uniqueLotProtection += "Fenced,";
          }
          if (
            item.dolsecguards != "yes" &&
            item.dolaftrhrslighting != "yes" &&
            item.dolEntranceQuestions.postChain != "yes" &&
            item.dolsurvcammoniintrunotifi != "yes" &&
            item.dolsurcamnotmoniotrd != "yes" &&
            item.dolEntranceQuestions.fullyFencedPremises != "yes"
          ) {
            uniqueLotProtection += "None,";
          }
          uniqueLotProtection = uniqueLotProtection.slice(0, -1);
        }
        if (item.locationNum == 1) {
          if (item.dolsecguards == "yes") {
            checklotprotectiondolsecguards = "yes";
          }
          if (item.dolaftrhrslighting == "yes") {
            checklotprotectiondolaftrhrslighting = "yes";
          }
          if (item.dolEntranceQuestions.postChain == "yes") {
            checklotprotectionpostChain = "yes";
          }
          if (
            item.dolsurvcammoniintrunotifi == "yes" ||
            item.dolsurcamnotmoniotrd == "yes"
          ) {
            checklotprotectionsurveillance = "yes";
          }
          if (item.dolEntranceQuestions.fullyFencedPremises == "yes") {
            checklotprotectionfullyFencedPremises = "yes";
          }
          if (
            item.dolsecguards != "yes" &&
            item.dolaftrhrslighting != "yes" &&
            item.dolEntranceQuestions.postChain != "yes" &&
            item.dolsurvcammoniintrunotifi != "yes" &&
            item.dolsurcamnotmoniotrd != "yes" &&
            item.dolEntranceQuestions.fullyFencedPremises != "yes"
          ) {
            checklotprotectionnone = "yes";
          }
        }
      })
    : "";

  data.genericData.locationScheduleGridData.some(locationItem => {
    if (locationItem.occupancyType == "bodyShop") {
      checkBodyShop = "Yes";
      return true;
    } else {
      checkBodyShop = "No";
      return false;
    }
  });
  value = {
    checkAutobody: checkBodyShop ? checkBodyShop : "",
    checkfloodloss: data.dolflodlossdescib
      ? data.dolflodlossdescib.length > 0
        ? "true"
        : "false"
      : "",
    DealershipStorageLocationsArray: DealershipStorageLocationsArray,
    sumNonEmployeesfurnished: nonEmployeesfurnishedAuto,
    sumfurnishedAutoEmployee: furnishedAutoEmployee,
    primarySecondaryOEM: [...new Set(primarySecondaryOEM2)] + "",
    checkMixofInventoryNew: checkMixofInventoryNew,
    uniqueLotProtection:
      uniqueLotProtection.length > 0 ? uniqueLotProtection : "",
    uniqueKeyControls: uniqueKeyControls.length > 0 ? uniqueKeyControls : "",
    checklotprotectiondolsecguards: checklotprotectiondolsecguards
      ? checklotprotectiondolsecguards
      : "",
    checklotprotectiondolaftrhrslighting: checklotprotectiondolaftrhrslighting
      ? checklotprotectiondolaftrhrslighting
      : "",
    checklotprotectionpostChain: checklotprotectionpostChain
      ? checklotprotectionpostChain
      : "",
    checklotprotectionsurveillance: checklotprotectionsurveillance
      ? checklotprotectionsurveillance
      : "",
    checklotprotectionfullyFencedPremises: checklotprotectionfullyFencedPremises
      ? checklotprotectionfullyFencedPremises
      : "",
    checklotprotectionnone: checklotprotectionnone
      ? checklotprotectionnone
      : "",
    checkkeycontrolsnone: checkkeycontrolsnone ? checkkeycontrolsnone : "",
    checkkeycontrolslockbox: checkkeycontrolslockbox
      ? checkkeycontrolslockbox
      : "",
    checkkeycontrolscomputerizedKeyVault: checkkeycontrolscomputerizedKeyVault
      ? checkkeycontrolscomputerizedKeyVault
      : "",
    checkkeycontrolslockingKeyCabinet: checkkeycontrolslockingKeyCabinet
      ? checkkeycontrolslockingKeyCabinet
      : "",
    checkkeycontrolsdailyKeyInventory: checkkeycontrolsdailyKeyInventory
      ? checkkeycontrolsdailyKeyInventory
      : "",
    checkkeycontrolslockedInManagersOffice: checkkeycontrolslockedInManagersOffice
      ? checkkeycontrolslockedInManagersOffice
      : "",
    checkkeycontrolskeysInCars: checkkeycontrolskeysInCars
      ? checkkeycontrolskeysInCars
      : ""
  };
  console.log(value);
}
