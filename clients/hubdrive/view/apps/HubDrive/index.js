import osjs from "osjs";
import { name as applicationName, icon_white } from "./metadata.json";
import { React, ReactDOM } from "oxziongui";
import Home from "./home";

// Our launcher
const register = (core, args, options, metadata) => {
  // Create a new Application instance
  const proc = core.make("osjs/application", { args, options, metadata });
  proc
    .createWindow({
      id: metadata.name + "_Window",
      title: metadata.title.en_EN,
      icon: proc.resource(icon_white),
      state: {
        maximized: true 
      },
      attributes: {
        classNames: ["Window_" + metadata.name],
        dimension: {
          width: 900,
          height: 500,
        },
        minDimension: {
          width: 900,
          height: 500,
        },
        position: {
          left: 150,
          top: 50,
        },
      },
    })
    .on("destroy", () => proc.destroy())
    .render(($content) =>
      ReactDOM.render(<Home args={core} params={args} proc={proc} />, $content)
    );
  return proc;
};

// Creates the internal callback function when OS.js launches an application
osjs.register(applicationName, register);
