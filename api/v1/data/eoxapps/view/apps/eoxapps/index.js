import osjs from "osjs";
import { name, icon_white, appId, fontIcon } from "./metadata.json";
import { React, ReactDOM, EOXApplication } from "oxziongui";

// Our launcher
const register = (core, args, options, metadata) => {
  // Create a new Application instance
  const proc = core.make("osjs/application", { args, options, metadata });
  proc
    .createWindow({
      id: metadata.name + "_Window",
      title: metadata.title.en_EN,
      icon: metadata.fontIcon,
      attributes: {
        classNames: ["Window_" + metadata.name],
        dimension: {
          width: 900,
          height: 500
        },
        minDimension: {
          width: 900,
          height: 500
        },
        position: {
          left: 150,
          top: 50
        }
      },
      state: {
        maximized: true
      }
    })
    .on("destroy", () => proc.destroy())
    .render(($content) =>
      ReactDOM.render(
        <EOXApplication
          args={core}
          application_id={appId}
          params={args}
          proc={proc}
        />,
        $content
      )
    );
  return proc;
};

// Creates the internal callback function when OS.js launches an application
osjs.register(name, register);
