import { ServiceProvider } from '@osjs/common';
import LocalStorageAdapter from './localStorageAdapter.js';

export class BosAdapter extends ServiceProvider {

	constructor(core, options = {}) {
		super(core, options || {});
		this.core = core;
        this.metadata = [];
    }


    providers() {
      return [
      'oxzion/core'
      ];
  }

  async init() {
      this.core.on('osjs/core:started', () => {
        var queryString = window.location.search.substr(1);
        console.log(queryString);
        if (queryString) {

            var queryObj = queryString.split("&").reduce(function(prev, curr, i, arr) {
                var p = curr.split("=");
                prev[decodeURIComponent(p[0])] = decodeURIComponent(p[1]);
                return prev;
            }, {});

            var appName = queryObj.app;

            var userDetails = this.core.make('oxzion/profile').get();
            var appList = userDetails.key.blackListedApps;

            if (!(appName in userDetails.key.blackListedApps)){

                this.core.request(this.core.config('packages.manifest'), {}, 'json')
                .then(metadata => {
                    this.addPackages(metadata);
                    this.launch(queryObj);
                })
                .catch(error => console.error(error));
            }
        }
    });

      this.core.on('osjs/core:started', () => {
        var myDate = new Date();
        var hrs = myDate.getHours();
        var greet;
        if (hrs < 12)
            greet = 'Good Morning';
        else if (hrs >= 12 && hrs <= 17)
            greet = 'Good Afternoon';
        else if (hrs >= 17 && hrs <= 24)
            greet = 'Good Evening';
            //console.log(this.core.make('oxzion/profile').get().UserInfo();
            var userDetails = this.core.make('oxzion/profile').get();
            this.core.make('osjs/notification', {  timeout:10000,icon:userDetails['key']['icon'],title:"Welcome to 3.0!",message: 'Hello and '+greet+" "+userDetails['key']['firstname']+'!'})});

      this.core.on('oxzion/application:launch', (params) =>{
        if(params.app){
            this.core.make('osjs/packages').launch(params.app, ((params.args) ? params.args : {}), ((params.options) ? params.options : {}));
        }
    });
  }

  addPackages(list) {
    if (list instanceof Array) {
        const append = list
        .map(iter => Object.assign({
            type: 'application'
        }, iter));
        this.metadata = [...this.metadata, ...append];
    }
}

launch(app) {
    var found = this.metadata.find(pkg => pkg.name === app.app);
    if (found != undefined)
        this.core.make('osjs/packages').launch(app.app, ((app.args) ? app.args : {}), ((app.options) ? app.options : {}));
}

}