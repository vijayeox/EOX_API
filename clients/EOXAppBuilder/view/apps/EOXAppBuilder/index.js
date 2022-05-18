import osjs from "osjs";
import { EOXApplication, React, ReactDOM } from "oxziongui";
import SampleComponentDownload from "./custom-components/download-sample-component";
import HeaderActions from "./custom-components/header-actions/header-actions";
import InputFocuserComp from "./custom-components/input-focuser";
import InstallManager from "./custom-components/install-manager/install-manager";
import {
  appId,
  maximizeOnStart,
  name as applicationName,
} from "./metadata.json";
// Our launcher
const register = (core, args, options, metadata) => {
  // Create a new Application instance
  const proc = core.make("osjs/application", { args, options, metadata });
  let session = core.make("osjs/settings").get("osjs/session");
  let sessions = Object.entries(session);
  var i, finalposition, finalDimension, finalMaximised, finalMinimised;
  for (i = 0; i < sessions.length; i++) {
    if (
      Object.keys(session[i].windows).length &&
      session[i].name == metadata.name
    ) {
      finalposition = session[i].windows[0].position;
      finalDimension = session[i].windows[0].dimension;
      finalMaximised = session[i].windows[0].maximized;
      finalMinimised = session[i].windows[0].minimized;
    }
  }
  var win = proc
    .createWindow({
      id: metadata.name + "_Window",
      title: metadata.title.en_EN,
      // icon: proc.resource(icon_white),
      icon: metadata.fontIcon,
      attributes: {
        classNames: ["Window_" + metadata.name],
        dimension: finalDimension
          ? finalDimension
          : {
              width: 900,
              height: 570,
            },
        minDimension: {
          width: 900,
          height: 570,
        },
        position: finalposition
          ? finalposition
          : {
              left: 150,
              top: 50,
            },
      },
    })
    .on("destroy", () => proc.destroy())
    .render(($content) =>
      ReactDOM.render(
        <EOXApplication
          args={core}
          application_id={appId}
          params={args}
          proc={proc}
          childrenComponents={{
            installManager: InstallManager,
            headerActions: HeaderActions,
            sampleComponentDownload: SampleComponentDownload,
            inputFocuserComp : InputFocuserComp
          }}
        />,
        $content
      )
    );
  if (finalMinimised) {
    win.minimize();
  }
  if (finalMaximised) {
    win.maximize();
  }
  if (maximizeOnStart) {
    win.maximize();
  }
  return proc;
};

// Creates the internal callback function when OS.js launches an application
osjs.register(applicationName, register);
