import osjs from "osjs";
import { name as applicationName } from "./metadata.json";
import React from "react";
import ReactDOM from "react-dom";
import { icon } from "./metadata.json";
import Home from "./home";

// Our launcher
const register = (core, args, options, metadata) => {
  // Create a new Application instance
  const proc = core.make("osjs/application", {
    args,
    options,
    metadata
  });

  // Create  a new Window instance
  proc
    .createWindow({
      id: "OXAdminWindow",
      title: metadata.title.en_EN,
      icon: proc.resource(icon),
      dimension: { width: 650, height: 550 },
      position: {
        left: 300,
        top: 70
      }
    })
    .on("destroy", () => proc.destroy())
    .render($content => ReactDOM.render(<Home args={core} />, $content));

  return proc;
};

osjs.register(applicationName, register);
