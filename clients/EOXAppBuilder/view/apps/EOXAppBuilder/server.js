// Methods OS.js server requires
const path = require('path')
require('@babel/register')({
    ignore: [ /(node_modules)/ ],
    presets: ['@babel/env','@babel/react']
});
module.exports = (core, proc) => {
  const { routeAuthenticated } = core.make('osjs/express');
  return {

    // When server initializes
    init: async () => {
      // HTTP Route example (see index.js)
      core.app.post(proc.resource('/test'), (req, res) => {
        res.json({hello: 'World'});
      });

      routeAuthenticated('GET', proc.resource('/appstudio/samplecomponent'), async (req, res) => {
        res.sendFile(path.join(__dirname + '/custom-components/samplecustom-component.js'))
      });
    },
  
    // When server starts
    start: () => {},
  
    // When server goes down
    destroy: () => {},
  
    // When using an internally bound websocket, messages comes here
    onmessage: (ws, respond, args) => {
      respond('Pong');
    }
  }
}
