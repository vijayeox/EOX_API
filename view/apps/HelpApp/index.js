import './index.scss';
import osjs from 'osjs';
import { name as applicationName, title } from './metadata.json';
import React from 'react';
import ReactDOM from 'react-dom';
//import Hello from './Hello.js';
//import landingPage from './landingPage';
import HelpPage from './helpPage';

const baseUrl = process.env.SERVER;
  // Our launcher
  const register = (core, args, options, metadata) => {
    // Create a new Application instance
    const proc = core.make('osjs/application', { args, options, metadata });

    // Create  a new Window instance
    proc.createWindow({
      id: 'HelpWindow',
      title: title.en_EN,
      dimension: { width: 400, height: 400 },
      position: { left: 300, top: 0 }
    })
      .on('destroy', () => proc.destroy())
      .render($content => {
        const args = {proc };
        ReactDOM.render(<HelpPage args={args} />, $content);
      });

    //to run hello world on a window
    //$content => ReactDOM.render(<Hello args={core}/>, $content)
    // Creates a new WebSocket connection (see server.js)
    //const sock = proc.socket('/socket');
    //sock.on('message', (...args) => console.log(args))
    //sock.on('open', () => sock.send('Ping'));

    // Use the internally core bound websocket
    //proc.on('ws:message', (...args) => console.log(args))
    //proc.send('Ping')

    // Creates a HTTP call (see server.js)
    //proc.request('/test', {method: 'post'})
    //.then(response => console.log(response));

    return proc;
  };

  // Creates the internal callback function when OS.js launches an application
  osjs.register(applicationName, register);

