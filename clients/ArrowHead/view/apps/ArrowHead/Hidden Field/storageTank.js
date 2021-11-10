// ########################
// Add Tank Event
// ########################

var getTankIndex = (data) => {
  if (data.storageTankLocation > 0 && data.storageTanks) {
    let tankListLocations = [];
    var tankList = data.storageTanks.filter((item) => {
      tankListLocations.push(item.locationNum);
      return item.locationNum == data.storageTankLocation;
    });
    let currentFinalLocation = 0;
    if(tankListLocations.length > 0) {
      for (var value of tankListLocations) {
        if(data.storageTankLocation > value) {
          currentFinalLocation++;
        }
      }
    }
    return tankList.length == 0
      ? {
          tankIndex: 1,
          tankInsertPosition: currentFinalLocation,
        }
      : {
          tankIndex: tankList[tankList.length - 1].tankIndex + 1,
          tankInsertPosition:
            data.storageTanks.findIndex(
              (i) =>
                i.LocTankIndex == tankList[tankList.length - 1].LocTankIndex
            ) + 1,
        };
  } else {
    return 1;
  }
};

var storageTankClone = _.merge(
    [],
    data.storageTanks
    ? [...data.storageTanks].filter((i) => i.locationNum > 0)
    : []
  );

var { tankIndex, tankInsertPosition } = getTankIndex(data);

var rowValue = {
  LocTankIndex: data.storageTankLocation + "-" + tankIndex,
  d1: false,
  installationDate: "",
  locationNum: data.storageTankLocation,
  otherTankContents: "",
  tankContents: "",
  tankIndex: tankIndex,
};

storageTankClone.splice(tankInsertPosition, 0, rowValue);
window.setTimeout(() => {
  result[0].formObject.getComponent(component.key).setValue(storageTankClone);
},100);
// ########################
// Delete Tank Event
// ########################

rowIndex = result[0].rowIndex;
row = result[0].row;
storageTankClone = [...data.storageTanks];
immediateTankSize = storageTankClone.filter(
  (i) => i.locationNum == row.locationNum
).length;
console.log(immediateTankSize);
storageTankClone.splice(rowIndex,1);
value = storageTankClone.map((tank) => {
  if (
    tank.locationNum == row.locationNum &&
    tank.tankIndex > row.tankIndex
  ) {
    return {
      ...tank,
      tankIndex: tank.tankIndex - 1,
      LocTankIndex: tank.locationNum + "-" + (tank.tankIndex - 1),
    };
  } else if (
    tank.locationNum > row.locationNum &&
    immediateTankSize == 0
  ) {
    return {
      ...tank,
      locationNum: tank.locationNum - 1,
      LocTankIndex: tank.locationNum - 1 + "-" + tank.tankIndex,
    };
  } else {
    return tank;
  }
});
console.log(storageTankClone);