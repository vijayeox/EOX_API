import { OX_Grid, React, ReactDOM, EOXApplication, ReactBootstrap, FormRender, KendoReactButtons, KendoReactWindow} from "oxziongui";
import * as OxzionGUIComponents from 'oxziongui'
import UploadArtifact from "../../../../gui/UploadArtifact";
import './install-manager.scss'
import form from './metadata-form.json'
import accountForm from './account-form.json'
export default  function InstallManager(props){
    return <AppInstaller core={props.core}  appId={props.appId} parentPageData={props.parentPageData}/>
}
class AppInstaller extends React.Component{
    constructor(props){
        super(props);
        this.core = props.core;
        this.loader = this.core.make("oxzion/splash");
        this.helper = this.core.make("oxzion/restClient");
        this.appId = props.appId;
        this.state = {
            orgInstallSelected : null,
            orgTemplateSelected : null
        }
        this.onOrgAction = this.onOrgAction.bind(this)
        this.uninstall = this.uninstall.bind(this)
        this.install = this.install.bind(this)
        this.parentPageData = props.parentPageData
    }
    componentDidMount(){
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
            resolve(status === 'success' && data || null)
        })
    }
    onOrgAction(type, org){
        if(type === 'INSTALL'){
            this.setState({orgInstallSelected : org})
            return;
        }
        this.setState({orgTemplateSelected : org})
    }
    uninstall(){

    }
    install(){
        this.setState({orgInstallSelected : null})
    }
    closeTemplate(){
        this.setState({orgTemplateSelected : null})
    }
    render(){
        return <div className='install-manager'>
            <div className="install-manager_header">
                <div>Organization</div>
                <div className="install-manager_header--active">Metadata</div>
                <div>Template Manager</div>
                {/* {this.state.orgInstallSelected ||  this.state.orgTemplateSelected ?
                    <KendoReactButtons.Button className={"btn btn-primary install-manager-btn"} 
                        onClick={() => {
                            this.setState({orgInstallSelected : null, orgTemplateSelected : null})
                        }}>
                        <i className="fas fa-arrow-left"></i>
                    </KendoReactButtons.Button> : null
                } */}
            </div>
            {this.state.orgInstallSelected && 
                <Metadata 
                    core={this.core} 
                    org={this.state.orgInstallSelected} 
                    install={this.install.bind(this)}
                /> || 
                this.state.orgTemplateSelected && 
                <Template 
                    org={this.state.orgTemplateSelected} 
                    closeTemplate={this.closeTemplate.bind(this)}
                    core={this.props.core}
                    appId={this.appId}
                    parentPageData={this.parentPageData}
                /> || 
                <Organization 
                organization={this.state.organization} 
                install={this.onOrgAction} 
                uninstall={this.uninstall}
                core={this.props.core}
            />
            }
        </div>
    }
}
class Installer extends React.Component{
    constructor(prosp){
        super(props);
    }
}

class Template extends React.Component{
    constructor(props){
        super(props);
        this.core = props.core;
        this.templates = props.templates;
        this.closeTemplate = props.closeTemplate;
        this.app = this.props.parentPageData;
    }
    componentDidMount(){
    }
    render(){
        return <>
            <UploadArtifact 
                components={OxzionGUIComponents} 
                entity='template' 
                refresh={() =>{}} 
                core={this.core} 
                appId={this.props.appId} 
                params={{app_uuid: this.app?.uuid}}
            />
            <OX_Grid
                osjsCore={this.core}
                data={`app/${this.app?.uuid}/artifact/list/template`}
                columnConfig={[{title : 'Template', field : 'name'}]}
                actions={[
                    {icon : 'far fa-trash', name : 'Delete', callback : ()=>{}, rule: "true"},
                ]}
            />
        </>
    }
}
class Account extends React.Component{
    constructor(props){
        super(props);
        this.core = props.core;
    }
    render(){
        return   <FormRender 
        content = {accountForm}
        core ={this.core}
        postSubmitCallback = {(...args) => {
            console.log(args)
        }}
        updateFormData={true}
    />
    }
}
class Metadata extends React.Component{
    constructor(props){
        super(props);
        this.core = props.core;
        this.install = this.props.install;
    }
    componentDidMount(){
    }
    render(){
        return   <FormRender 
        content = {form}
        core ={this.core}
        postSubmitCallback = {(...args) => {
            console.log(args)
        }}
        updateFormData={true}
    />
    }
}
class Organization extends React.Component{
    constructor(props){
        super(props);
        this.core = props.core;
        this.install = this.props.install;
        this.uninstall = this.props.uninstall;
    }
    render(){
        return <OX_Grid
                    osjsCore={this.core}
                    data={'account?filter=[{%22take%22:1000,%22skip%22:0}]'}
                    columnConfig={[{title : 'Organization', field : 'name'}]}
                    actions={[
                        {icon : 'far fa-download', name : 'Install', 
                        callback : (data) => { 
                            this.install('INSTALL', data)
                        }, 
                        rule: "true"},
                        {icon : 'far fa-trash', name : 'Uninstall', callback : this.uninstall.bind(this), rule: "true"},
                        {icon : 'far fa-upload', name : 'Template Manager', callback : (data) => {
                            this.install('TEMPLATE', data)
                         }, rule: "true"},
                    ]}
                />
    }
}