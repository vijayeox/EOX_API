function insertAfter(referenceNode, newNode) {
  referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}

var s = document.createElement("script");
s.src = "https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js";
s.onload = function (e) {
  var appendCustomButtonTimer = setInterval(() => {
    if (
      document.getElementById(
        "formio_loader_280dbc6d-638e-4fba-9095-8430c226ec7b"
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
                notification: "Data saved successfully"
              },
              bubbles: false
            });
            document
              .getElementById(
                "formio_loader_280dbc6d-638e-4fba-9095-8430c226ec7b"
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
                notification: "Data saved successfully"
              },
              bubbles: false
            });
            document
              .getElementById(
                "formio_loader_280dbc6d-638e-4fba-9095-8430c226ec7b"
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

      if (
        [...document.querySelectorAll('[ref="modalSave"]')].some(
          (i) => i.innerText == "SAVE"
        )
      ) {
        [...document.querySelectorAll('[ref="modalSave"]')].map(
          (i) => (i.innerText = "OK")
        );
      }
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
    victor_AutoPhysDamage: false
  };
  data.producerConfirmation ? (data.producerConfirmation = false) : null;

  try {
    if (data.namedInsured.length == 0) {
      if (document.getElementsByClassName("pagination").length > 0) {
        var pageList = [
          ...document.getElementsByClassName("pagination")[0].children
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
