import osjs from "osjs";
import { name as applicationName, icon_white } from "./metadata.json";
import {React,ReactDOM} from "oxziongui";
import Home from "./home";

// Our launcher
const register = (core, args, options, metadata) => {
  // Create a new Application instance
  const proc = core.make("osjs/application", { args, options, metadata });
  proc.createWindow({
      id: "Window_Finance",
      title: metadata.title.en_EN,
      // icon: proc.resource(icon_white),
      icon: metadata.fontIcon,
      dimension: {
        width: document.body.clientWidth,
        height: document.body.clientHeight
      },
      state: {
        maximized: true
      },
      position: { left: 700, top: 200 }
    })
    .on("destroy", () => proc.destroy())
    .render($content => ReactDOM.render(<Home args={core} params={args} proc={proc} />, $content));

  return proc;
};

// Creates the internal callback function when OS.js launches an application
osjs.register(applicationName, register);
