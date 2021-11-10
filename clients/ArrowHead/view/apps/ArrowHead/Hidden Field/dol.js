// Calculate Button
var DOLFieldList = [
  {
    key: "dol_12MonthAvg",
    children: ["new", "used", "demosFurnishedAutos", "loanersShopService"],
  },
  {
    key: "dol_inventory",
    children: ["maxInventory", "maxUnits", "maxIndoor"],
  },
];
var accountTotal = {};
DOLFieldList.map((field) => {
  var cloneItem = [...data[field.key]];
  cloneItem = cloneItem.map((location) => {
    var lineTotal = {
      total: 0,
      floor_total: 0,
    };
    field.children.map((i) => {
      location["total_" + i] =
        (location[i] > 0 ? location[i] : 0) -
        (location["floor_" + i] > 0 ? location["floor_" + i] : 0);
      lineTotal.total += location[i] ? location[i] : 0;
      lineTotal.floor_total += location["floor_" + i]
        ? location["floor_" + i]
        : 0;
      accountTotal['acc_'+i] =
        (accountTotal['acc_'+i] ? accountTotal['acc_'+i] : 0) +
        (location["total_" + i] ? location["total_" + i] : 0);
    });
    accountTotal.acc_total =
      (accountTotal.acc_total ? accountTotal.acc_total : 0) +
      (lineTotal.total - lineTotal.floor_total);
    return {
      ...location,
      ...lineTotal,
      total_total: lineTotal.total - lineTotal.floor_total,
    };
  });
  form.getComponent(field.key + "Total").setValue(accountTotal);
  form.getComponent(field.key).setValue(cloneItem);
});
// Same As Loc 1

var cloneItem = [...data.dol_Protection];
cloneItem[rowIndex] = {
  ...row,
  premisesLotProtection: { ...cloneItem[0].premisesLotProtection },
  entranceQuestions: { ...cloneItem[0].entranceQuestions },
  keyControls: { ...cloneItem[0].keyControls },
  other: cloneItem[0].other ? cloneItem[0].other : null,
};
form.getComponent("dol_Protection").setValue(cloneItem);

// Select All in Requested Coverage

var fieldSet = [
  "new",
  "demos",
  "used",
  "service",
  "otherOwned",
  "otherNonOwned",
];
var requestedCoverage = {};
fieldSet.map((item) => {
  requestedCoverage[item] = {
    collision: "yes",
    comp: "yes",
    falsePretense: "yes",
  };
});
form.getComponent("requestedCoverage").setValue(requestedCoverage);
