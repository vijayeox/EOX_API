import {
  OX_Grid,
  React,
  ReactDOM,
  EOXApplication,
  ReactBootstrap,
  FormRender,
  KendoReactButtons,
  KendoReactWindow,
} from "oxziongui";
import * as OxzionGUIComponents from "oxziongui";
import UploadArtifact from "../../../../gui/UploadArtifact";
import "./install-manager.scss";
import form from "./metadata-form.json";
import accountForm from "./account-form.json";
import { InstallManagerService } from "./install-manager.service";
export default function InstallManager(props) {
  return (
    <AppInstaller
      core={props.core}
      appId={props.appId}
      parentPageData={props.parentPageData}
      data={props.data}
    />
  );
}
class AppInstaller extends React.Component {
  constructor(props) {
    super(props);
    this.core = props.core;
    this.loader = this.core.make("oxzion/splash");
    this.helper = this.core.make("oxzion/restClient");
    this.appId = props.appId;
    this.state = {
      service: new InstallManagerService(
        props.parentPageData,
        this.props.data?.params?.type || 'forInstall',
        this.core,
        this.appId
      ),
    };
  }
  render() {
    return (
      <div className="install-manager">
        <div className="install-manager_header">
          {this.state.service.selectedOrganization ||
          this.state.service.updateOrganization ? (
            <KendoReactButtons.Button
              className={"btn btn-primary install-manager-btn"}
              onClick={() => {
                this.setState({ service: this.state.service.goBack() });
              }}
            >
              <i className="fas fa-arrow-left"></i>
            </KendoReactButtons.Button>
          ) : null}
        </div>
        <div className="install-manager_content">
            {this.state.service.updateOrganization ? (
            <Account
                service={this.state.service}
                setService={this.setState.bind(this)}
            />
            ) : (
            <>
                {(!this.state.service.selectedOrganization && (
                <Organization
                    service={this.state.service}
                    setService={this.setState.bind(this)}
                />
                )) ||
                (!this.state.service.metaData && (
                    <Metadata
                    service={this.state.service}
                    setService={this.setState.bind(this)}
                    />
                )) || (
                    <Template
                    service={this.state.service}
                    setService={this.setState.bind(this)}
                    />
                )}
            </>
            )}
        </div>
        {this.state.service.metaData && (
         <button className="install-manager_submit">{this.state.service.installationType === 'forInstall' ? 'Install' : 'Uninstall'}</button>
        )}
      </div>
    );
  }
}

class Template extends React.Component {
  constructor(props) {
    super(props);
    this.service = this.props.service;
    this.setService = this.props.setService;
    this.core = this.service.core;
    this.api = this.core.make("oxzion/restClient");
    this.state = { gridLoading: false };
  }
  componentDidMount() {}
  async refreshGrid() {
    this.setState({ gridLoading: true });
    await new Promise((r) => setTimeout(r, 100));
    this.setState({ gridLoading: false });
  }
  async delete({ name }) {
    let { status, data } = await this.api.request(
      "v1",
      `app/${this.service.parentData.uuid}/artifact/delete/template/${name}.tpl`,
      this.service.parentData,
      "post"
    );
    status === "success" && this.refreshGrid();
  }
  render() {
    return (
      (!this.state.gridLoading && (
        <>
          <UploadArtifact
            components={OxzionGUIComponents}
            entity="template"
            postSubmitCallback={this.refreshGrid.bind(this)}
            core={this.core}
            appId={this.service.appId}
            params={{ app_uuid: this.service.parentData?.uuid }}
          />
          <OX_Grid
            osjsCore={this.core}
            data={`app/${this.service.parentData?.uuid}/artifact/list/template`}
            columnConfig={[{ title: "Template", field: "name" }]}
            actions={[
              {
                icon: "far fa-trash",
                name: "Delete",
                callback: this.delete.bind(this),
                rule: "true",
              },
            ]}
          />
        </>
      )) ||
      null
    );
  }
}
class Account extends React.Component {
  constructor(props) {
    super(props);
    this.service = this.props.service;
    this.core = this.service.core;
  }
  render() {
    return (
      <FormRender
        content={accountForm}
        core={this.core}
        postSubmitCallback={(...args) => {
          console.log(args);
        }}
        updateFormData={true}
        data={this.service.updateOrganization}
      />
    );
  }
}
class Metadata extends React.Component {
  constructor(props) {
    super(props);
    this.service = this.props.service;
    this.setService = this.props.setService;
    this.core = this.service.core;
    this.api = this.core.make("oxzion/restClient");
    this.state = {
      data: null,
    };
  }
  async componentDidMount() {
    try {
      let { status, data } = await this.api.request(
        "v1",
        `app/${this.service.parentData.uuid}/account/${this.service.selectedOrganization.uuid}/appProperties`,
        {},
        "get"
      );
      data = JSON.parse(data[0]["start_options"]);
      if (status === "success") {
        this.setState({
          data: {
            ...data,
            description: data.description.en_EN,
            title: data.title.en_EN,
          },
        });
      }
    } catch (e) {
      console.error(e);
    }
  }
  render() {
    return this.state.data ? (
      <FormRender
        content={form}
        core={this.core}
        postSubmitCallback={(...args) => {
          this.setService({ service: this.service.setMetadata(args) });
        }}
        data={this.state.data}
        updateFormData={true}
      />
    ) : null;
  }
}
class Organization extends React.Component {
  constructor(props) {
    super(props);
    this.service = this.props.service;
    this.setService = this.props.setService;
    this.actions = [
        {
          icon: "far fa-download",
          name: this.service.installationType === 'forInstall' && 'Install' || 'Uninstall',
          callback: (data) => {
            this.setService({ service: this.service.setOrganization(data) });
          },
          rule: "true",
          defaultAction: true,
        }
      ]
  }
  componentDidMount(){
      if(this.service.installationType === 'forInstall') return;
      this.actions.push(
        {
          icon: "far fa-pencil",
          name: "Edit",
          callback: (data) => {
            this.setService({
              service: this.service.updateOrganizationData(data),
            });
          },
          rule: "true",
        }
    )
  }
  render() {
    return (
      <OX_Grid
        osjsCore={this.service.core}
        data={`app/${this.service.parentData.uuid}/getAccounts/${this.service.installationType}?filter=[{%22take%22:1000,%22skip%22:0}]`}
        columnConfig={[{ title: "Account", field: "name" }]}
        actions={this.actions}
      />
    );
  }
}
