if (
  data.workbooksToBeGenerated.harco == true ||
  data.workbooksToBeGenerated.dealerGuard_ApplicationOpenLot == true ||
  data.workbooksToBeGenerated.victor_FranchisedAutoDealer == true ||
  data.workbooksToBeGenerated.victor_AutoPhysDamage == true
) {
  var tempLocationArray = [];
  var tempStorageTankArray = [];
  if (data.locationSchedule) {
    if (data.locationSchedule.length > 0) {
      data.locationSchedule.map((locationItem, locationIndex) => {
        let temp = { ...locationItem };
        delete temp.buildingDetails;
        delete temp.storageTankGrid;
        locationItem.buildingDetails.map((buildingItem) => {
          tempLocationArray.push({
            locationBuildingNumber:
              locationItem.locationNum + "-" + buildingItem.buildingNumber,
            ...temp,
            ...buildingItem
          });
        });
        locationItem.storageTankGrid.map((storageTankItem, tankIndex) => {
          tempStorageTankArray.push({
            ...storageTankItem,
            locationNum: locationIndex + 1,
            locationTankNumber: locationIndex + 1 + "-" + (tankIndex + 1)
          });
        });
      });
    }
  }

  value = {
    locationScheduleGridData: tempLocationArray,
    storageTankGridData: tempStorageTankArray
  };

  console.log(value);
}
