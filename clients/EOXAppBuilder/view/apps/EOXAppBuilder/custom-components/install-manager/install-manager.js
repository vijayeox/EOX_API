import { React, ReactDOM, EOXApplication } from "oxziongui";
import './install-manager.scss'
export default  function InstallManager(props){
    return <AppInstaller core={props.core}  appId={props.appId}/>
}
/**
 * 
            appId={this.appId}
            fileId={fileId}
 */
class AppInstaller extends React.Component{
    constructor(props){
        super(props);
        this.core = props.core;
        this.loader = this.core.make("oxzion/splash");
        this.helper = this.core.make("oxzion/restClient");
        this.appId = props.appId;
        this.state = {
            tab : 0,
            organization : null,
            templates : null
        }
    }
    componentDidMount(){
        Promise.all([
            this.api('account?filter=[{%22take%22:1000,%22skip%22:0}]'), 
            this.api(`app/${this.appId}/artifact/list/template`)
        ]).then(([orgsList, templates]) => {
            this.setState({organization : orgsList || [], templates : templates || [] })
        })
    }
    getActive(n){
        return this.state.tab === n &&'install-manager-tabs_active'
    }
    api(api, payload = {}, rest = 'get'){
        return new Promise(async (resolve) => {
            const {status, data} = await this.helper.request(
                "v1",
                api,
                payload,
                rest
            )
            resolve(status === 'status' && data || null)
        })
    }
    render(){
        return <div className='install-manager'>
                    <div className='install-manager-tabs width-100'>
                        <p className={this.getActive(0)} onClick={() => this.setState({tab : 0})}>Select Organization</p>
                        <p className={this.getActive(1)} onClick={() => this.setState({tab : 1})}>Auto-start</p>
                        <p className={this.getActive(2)} onClick={() => this.setState({tab : 2})}>Email Template</p>
                    </div>
                    {this.state.tab === 0 && <Organization organization={this.state.organization}/>}
                    {this.state.tab === 1 && <Metadata core={this.core}/>}
                    {this.state.tab === 2 && <Template templates={this.state.templates}/>}
                    <div className='install-manager-submission'>
                        <button>Next</button>
                    </div>
        </div>
    }
}

class Template extends React.Component{
    constructor(props){
        super(props);
        this.core = props.core;
        this.templates = props.templates;
    }
    componentDidMount(){
    }
    render(){
        return <div>{JSON.stringify(this.templates)}</div>
    }
}

class Metadata extends React.Component{
    constructor(props){
        super(props);
        this.core = props.core;
    }
    componentDidMount(){
    }
    render(){
        return <div>AUTO START</div>
    }
}

class Organization extends React.Component{
    constructor(props){
        super(props);
        this.core = props.core;
    }
    render(){
        const data = (props.organization || []).map((org) => {
            return <p key={org.uuid}>{org.name}</p>
        })
        return <div className="organizations">{data}</div>
    }
}