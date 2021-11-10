// ########################
// Add Location Event
// ########################

var getLocationNumber = (data) => {
  if (data.locations && data.locations.length > 0) {
    var lastItem = data.locations[data.locations.length - 1];
    var lastLocation = parseInt(lastItem.locationNum);
    return lastLocation ? lastLocation + 1 : 1;
  } else {
    return 1;
  }
};

var locationsClone = _.merge([], data.locations);
var newLocationNumber = getLocationNumber(data);
var rowValue = {
  ...component.defaultValue[0],
  locationNum: newLocationNumber,
  locationBuildingNum: newLocationNumber + "-1",
};
locationsClone.push(rowValue);
value = locationsClone;

data.buildingLevelFieldList.map((field) => {
  var cloneItem = _.merge([], data[field.key]);
  cloneItem.push({
    locationBuildingNum: newLocationNumber + "-1",
    locationNum: newLocationNumber,
    buildingNum: 1,
  });
  result[0].formObject
    ? result[0].formObject.getComponent(field.key).setValue(cloneItem)
    : null;
});

data.locationLevelFieldList.map((field) => {
  var cloneItem = _.merge([], data[field.key]);
  cloneItem.push({
    locationNum: newLocationNumber,
  });
  result[0].formObject
    ? result[0].formObject.getComponent(field.key).setValue(cloneItem)
    : null;
});

// ########################
// Add Building Event
// ########################

var getLocationIndex = (data, rowInfo, rowIndex) => {
  if (data.locations && data.locations.length > 0) {
    var buildingList = data.locations.filter(
      (item) => item.locationNum == rowInfo.locationNum
    );
    var lastBuilding = buildingList[buildingList.length - 1].buildingNum;
    return rowIndex + lastBuilding;
  } else {
    return 1;
  }
};

if (result.length > 0) {
  var rowInfo = result[0].row;
  var rowIndex = result[0].rowIndex;

  var locationsClone = _.merge([], data.locations);
  var locationIndex = getLocationIndex(data, rowInfo, rowIndex);

  var rowValue = {
    ...component.defaultValue[0],
    locationNum: rowInfo.locationNum,
    buildingNum: locationIndex - rowIndex + 1,
    locationBuildingNum:
      rowInfo.locationNum + "-" + (locationIndex - rowIndex + 1),
  };
  locationsClone.splice(locationIndex, 0, rowValue);
  value = locationsClone;

  data.buildingLevelFieldList.map((field) => {
    var cloneItem = _.merge([], data[field.key]);
    cloneItem.splice(locationIndex, 0, {
      locationBuildingNum: rowValue.locationBuildingNum,
      locationNum: rowValue.locationNum,
      buildingNum: rowValue.buildingNum,
    });
    result[0].formObject
      ? result[0].formObject.getComponent(field.key).setValue(cloneItem)
      : null;
  });
}

// ########################
// Delete Building Event
// ########################

var rowInfo = result[0].row;
var rowIndex = result[0].rowIndex;
var tanksTodelete = [];
var tankstoUpdate = [];
var locationsClone = _.merge([], data.locations);
var storangeClone = _.merge([], data.storageTanks);

locationsClone.splice(rowIndex, 1);
currentBuildingsSize = locationsClone.filter(
  (i) => i.locationNum == rowInfo.locationNum
).length;
var deleteTankForLocation = (tankstoUpdate) => {
  for (i = 0; i < storangeClone.length; i++) {
    if (storangeClone[i].locationNum > tankstoUpdate) {
      storangeClone[i].locationNum = storangeClone[i].locationNum - 1;
      let splitData = storangeClone[i].LocTankIndex.split("-");
      storangeClone[i].LocTankIndex =
        storangeClone[i].locationNum + "-" + splitData[1];
    }
  }
  result[0].formObject.getComponent("storageTanks").setValue(storangeClone);
};
value = locationsClone.map((loc, index) => {
  if (
    loc.locationNum == rowInfo.locationNum &&
    loc.buildingNum > rowInfo.buildingNum
  ) {
    if (rowInfo.buildingNum == 1) {
      return {
        ...loc,
        buildingNum: loc.buildingNum - 1,
        locationBuildingNum: loc.locationNum + "-" + (loc.buildingNum - 1),
        address: rowInfo.address,
        city: rowInfo.city,
        state: rowInfo.state,
        county: rowInfo.county,
        stateName: rowInfo.stateName,
        zip: rowInfo.zip,
      };
    } else {
      return {
        ...loc,
        buildingNum: loc.buildingNum - 1,
        locationBuildingNum: loc.locationNum + "-" + (loc.buildingNum - 1),
      };
    }
  } else if (
    loc.locationNum > rowInfo.locationNum &&
    currentBuildingsSize == 0
  ) {
    return {
      ...loc,
      locationNum: loc.locationNum - 1,
      locationBuildingNum: loc.locationNum - 1 + "-" + loc.buildingNum,
    };
  } else {
    return loc;
  }
});

data.buildingLevelFieldList.map((field) => {
  var cloneItem = _.merge([], data[field.key]);
  cloneItem.splice(rowIndex, 1);
  result[0].formObject
    ? result[0].formObject.getComponent(field.key).setValue(cloneItem)
    : null;
});

data.locationLevelFieldList.map((field) => {
  var cloneItem = _.merge([], data[field.key]);
  if (currentBuildingsSize == 0) {
    var locationIndex = cloneItem.findIndex(
      (item) => item.locationNum == rowInfo.locationNum
    );
    cloneItem.splice(locationIndex, 1);
    cloneItem = cloneItem.map((item, index) => {
      return locationIndex <= index
        ? { ...item, locationNum: item.locationNum - 1 }
        : item;
    });
    result[0].formObject
      ? result[0].formObject.getComponent(field.key).setValue(cloneItem)
      : null;
  }
});
result[0].formObject.getComponent("storageTankLocation").setValue("");
if (currentBuildingsSize === 0) {
  for (i = 0; i < storangeClone.length; i++) {
    if (storangeClone[i].locationNum == rowInfo.locationNum) {
      tanksTodelete.push(i);
    }
  }
  storangeClone.splice(tanksTodelete[0], tanksTodelete.length);
  deleteTankForLocation(rowInfo.locationNum);
}
