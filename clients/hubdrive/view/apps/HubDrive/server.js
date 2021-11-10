const fs = require('fs');
const express = require('express');
const path = require('path');
// Methods OS.js server requires

require('@babel/register')({
  ignore: [/(node_modules)/],
  presets: ['@babel/env', '@babel/react']
});
module.exports = (core, proc) => {
  const { routeAuthenticated } = core.make('osjs/express');
  return {
    // When server initializes
    init: async () => {
      // HTTP Route example (see index.js)
      core.app.post(proc.resource('/test'), (req, res) => {
        res.json({ hello: 'World' });
      });
      const app = express()
      core.app.use('/public', express.static(path.resolve(__dirname, 'public')));
      let reactRender = require('./reactRender');
      reactRender(core, proc);
      // WebSocket Route example (see index.js)
      core.app.ws(proc.resource('/socket'), (ws, req) => {
        ws.send('Hello World');
      });
      
      routeAuthenticated('GET', proc.resource('/register'), async (req, res) => {
        res.sendFile(path.join(__dirname + '/dist/index.html'))
      });
    },

    // When server starts
    start: () => { },

    // When server goes down
    destroy: () => { },

    // When using an internally bound websocket, messages comes here
    onmessage: (ws, respond, args) => {
      respond('Pong');
    }
  }
};
