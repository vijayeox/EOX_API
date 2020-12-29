function insertAfter(referenceNode, newNode) {
  referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}

var s = document.createElement("script");
s.src = "https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js";
s.onload = function (e) {
  var appendCustomButtonTimer = setInterval(() => {
    if (
      document.getElementById(
        "formio_loader_1d9c6d64-469d-4401-9b64-d7f47316c157"
      )
    ) {
      if (
        !document.getElementById("saveDraftCustomButton") ||
        !document.getElementById("saveDraftCloseCustomButton")
      ) {
        if (!document.getElementById("saveDraftCloseCustomButton")) {
          let clone = $("ul[id*=nav]").children()[0].cloneNode(true);
          clone.children[0].textContent = "Save Draft And Close";
          clone.children[0].id = "saveDraftCloseCustomButton";
          insertAfter($("ul[id*=nav]").children()[0], clone);
          saveDraftCloseCustomButton.onclick = function () {
            let ev = new CustomEvent("customButtonAction", {
              detail: {
                timerVariable: appendCustomButtonTimer,
                formData: data,
                commands:
                  '[{ "command": "fileSave", "entity_name": "Dealer Policy" }]',
                exit: true,
                notification: "Data saved successfully",
              },
              bubbles: false,
            });
            document
              .getElementById(
                "formio_loader_1d9c6d64-469d-4401-9b64-d7f47316c157"
              )
              .dispatchEvent(ev);
          };
        }
        if (!document.getElementById("saveDraftCustomButton")) {
          let clone = $("ul[id*=nav]").children()[0].cloneNode(true);
          clone.children[0].textContent = "Save Draft";
          clone.children[0].id = "saveDraftCustomButton";
          insertAfter($("ul[id*=nav]").children()[0], clone);
          saveDraftCustomButton.onclick = function () {
            let ev = new CustomEvent("customButtonAction", {
              detail: {
                timerVariable: appendCustomButtonTimer,
                formData: data,
                commands:
                  '[{ "command": "fileSave", "entity_name": "Dealer Policy" }]',
                exit: false,
                notification: "Data saved successfully",
              },
              bubbles: false,
            });
            document
              .getElementById(
                "formio_loader_1d9c6d64-469d-4401-9b64-d7f47316c157"
              )
              .dispatchEvent(ev);
          };
        }
      } else {
        if (data.producername.length > 0 && data.namedInsured.length > 0) {
          saveDraftCustomButton.style.display == "none"
            ? (saveDraftCustomButton.style.display = "inline-block")
            : null;
          saveDraftCloseCustomButton.style.display == "none"
            ? (saveDraftCloseCustomButton.style.display = "inline-block")
            : null;
        } else {
          saveDraftCustomButton.style.display == "none"
            ? null
            : (saveDraftCustomButton.style.display = "none");
          saveDraftCloseCustomButton.style.display == "none"
            ? null
            : (saveDraftCloseCustomButton.style.display = "none");
        }
      }

      var dataGridDeleteIcons = document.getElementsByClassName(
        "fa-times-circle-o"
      );
      dataGridDeleteIcons = Array.from(dataGridDeleteIcons);
      if (dataGridDeleteIcons.length > 0) {
        dataGridDeleteIcons.map((item) => {
          item.classList.add("fa-times-circle");
          item.classList.remove("fa-times-circle-o");
        });
      }

      var locationGridDeleteIcons = document.querySelectorAll(
        "button.locationremove"
      );
      locationGridDeleteIcons = Array.from(locationGridDeleteIcons);
      if (locationGridDeleteIcons.length > 0) {
        locationGridDeleteIcons.map((item) => {
          if (item.childNodes.length == 3) {
            item.removeChild(item.childNodes[2]);
          }
        });
      }

      if (
        [...document.querySelectorAll('[ref="modalSave"]')].some(
          (i) => i.innerText == "SAVE"
        )
      ) {
        [...document.querySelectorAll('[ref="modalSave"]')].map(
          (i) => (i.innerText = "OK")
        );
      }
      var replaceModelLabel = [
        {
          key: "locations",
          text: "Edit Address",
        },
        {
          key: "buildings",
          text: "Enter Building Details",
        },
      ];
      replaceModelLabel.map((item) => {
        try {
          if (
            document
              .getElementsByClassName("formio-component-" + item.key)[0]
              .contains(
                document.getElementsByClassName(
                  "formio-component-modal-wrapper"
                )[0]
              )
          ) {
            var gridItems = Array.from(
              document.getElementsByClassName("formio-component-modal-wrapper")
            );
            gridItems.length > 0
              ? gridItems.map((row) => {
                  row.children[0].children[2].innerText == "Click to set value"
                    ? (row.children[0].children[2].innerText = item.text)
                    : null;
                })
              : null;
          }
        } catch {}
      });
    } else {
      appendCustomButtonTimer ? clearInterval(appendCustomButtonTimer) : null;
    }
  }, 1000);
};
document.head.appendChild(s);

setTimeout(function () {
  data.workbooksToBeGenerated = {
    epli: false,
    rpsCyber: false,
    harco: false,
    dealerGuard_ApplicationOpenLot: false,
    victor_FranchisedAutoDealer: false,
    victor_AutoPhysDamage: false,
  };
  data.producerConfirmation ? (data.producerConfirmation = false) : null;
  data.managementSubmitApplication
    ? (data.managementSubmitApplication = false)
    : null;

  try {
    if (data.namedInsured.length == 0) {
      if (document.getElementsByClassName("pagination").length > 0) {
        var pageList = [
          ...document.getElementsByClassName("pagination")[0].children,
        ];
        var GEActive = pageList.some(
          (i) =>
            i.children[0].innerText == "General Information" &&
            i.className.includes("active")
        );
        GEActive
          ? pageList.map((i, index) =>
              index !== 0 ? (i.style.cursor = "not-allowed") : null
            )
          : null;
        pageList.map((i, index) =>
          index !== 0
            ? i.setAttribute(
                "title",
                "Complete General Information page to proceed further"
              )
            : null
        );
      }
    }
  } catch {}
}, 1000);
