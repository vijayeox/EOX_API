// ########################
// Calculate Button
// ########################

console.log("click2");

var childFields = [
  "fTEmployeesFurnishedAnAuto",
  "pTEmployeesFurnishedAnAuto",
  "fTEmployeesWhoAreNotFurnished",
  "pTEmployeesWhoAreNotFurnished",
  "fTAllOtherEmployees",
  "pTAllOtherEmployees",
  "nonEmployeesUnderTheAge",
  "nonEmployeesYearsOldorolder",
  "contractDriversNonEmployees",
];

var sumObjectsByKey = (...objs) => {
  return objs.reduce((a, b) => {
    for (let k in b) {
      if (b.hasOwnProperty(k)) a[k] = (a[k] || 0) + b[k];
    }
    return a;
  }, {});
};

var cloneItem = _.merge([], data.employeeList);
var accountTotal = {};
var locationTotal = 0;
var fieldTotal = {};

cloneItem = cloneItem.map((building) => {
  building.total = childFields
    .map((i) => (building[i] > 0 ? building[i] : 0))
    .reduce((a, b) => a + b, 0);
  locationTotal += building.total;

  fieldTotal = sumObjectsByKey(fieldTotal, building);

  if (building.lastBuilding) {
    var cleanfieldTotal = {};
    childFields.map((i) => {
      cleanfieldTotal["total_" + i] = fieldTotal[i] ? fieldTotal[i] : 0;
      accountTotal[i] =
        (accountTotal[i] ? accountTotal[i] : 0) +
        (fieldTotal[i] ? fieldTotal[i] : 0);
    });
    building.total_total = locationTotal;
    building = { ...building, ...cleanfieldTotal };
    accountTotal.total =
      (accountTotal.total ? accountTotal.total : 0) + locationTotal;
    locationTotal = 0;
    fieldTotal = {};
  }

  return building;
});

accountTotal.total =
  accountTotal.total -
  (accountTotal.nonEmployeesUnderTheAge +
    accountTotal.nonEmployeesYearsOldorolder +
    accountTotal.contractDriversNonEmployees);

form.getComponent("employeeList").setValue(cloneItem);
form.getComponent("employeeList" + "Total").setValue(accountTotal);
